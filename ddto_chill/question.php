<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question definition class for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a Drag-drop into text Chill question during an attempt.
 *
 * Gaps in the question text are numbered [[1]], [[2]], … and each gap n
 * expects the associated choice (the n-th stored choice). Extra choices
 * are distractors. Grading follows the QCM Chill model, counting correctly
 * and incorrectly filled gaps instead of ticked checkboxes:
 *
 * - each correctly filled gap earns an equal share of the mark
 *   (1 / number of gaps);
 * - each incorrectly filled gap costs the negative marking fraction;
 * - empty gaps earn nothing and are not penalised;
 * - in all-or-nothing mode the mark is only awarded when every gap is
 *   filled correctly, otherwise the response scores zero minus the
 *   negative marking of the incorrectly filled gaps;
 * - the resulting fraction is bounded between -1 and 1.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddto_chill_question extends question_graded_automatically {
    /** @var float the lowest fraction a response can score. */
    const MIN_FRACTION = -1.0;

    /** @var float the highest fraction a response can score. */
    const MAX_FRACTION = 1.0;

    /** @var int group used for every choice in v1. */
    const DEFAULT_GROUP = 1;

    /** @var float penalty for each incorrectly filled gap, as a fraction of the mark, between -1 and 0. */
    public $negativemarking = 0.0;

    /** @var int 1 when the mark is only awarded for a fully correct response, 0 otherwise. */
    public $allornothing = 0;

    /** @var int 1 when the choice bank is shuffled for each attempt. */
    public $shuffleanswers = 1;

    /**
     * Choices keyed by 1-based choice number.
     *
     * Each value is an object with keys text, choicegroup and fraction.
     *
     * @var stdClass[]
     */
    public $choices = [];

    /**
     * Map of gap number (1-based) to the expected choice number.
     *
     * @var int[]
     */
    public $places = [];

    /**
     * Display order of the choice numbers for this attempt.
     *
     * @var int[]
     */
    public $choiceorder = [];

    #[\Override]
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('qtype_ddto_chill');
    }

    #[\Override]
    public function start_attempt(question_attempt_step $step, $variant) {
        $order = array_keys($this->choices);
        if (!empty($this->shuffleanswers) && count($order) > 1) {
            shuffle($order);
        }
        $step->set_qt_var('_choiceorder', implode(',', $order));
        $this->choiceorder = $order;
    }

    #[\Override]
    public function apply_attempt_state(question_attempt_step $step) {
        $raw = $step->get_qt_var('_choiceorder');
        if ($raw === null || $raw === '') {
            $this->choiceorder = array_keys($this->choices);
            return;
        }
        $order = [];
        foreach (explode(',', $raw) as $no) {
            $no = (int) $no;
            if (isset($this->choices[$no])) {
                $order[] = $no;
            }
        }
        foreach (array_keys($this->choices) as $no) {
            if (!in_array($no, $order, true)) {
                $order[] = $no;
            }
        }
        $this->choiceorder = $order;
    }

    #[\Override]
    public function get_expected_data() {
        $data = [];
        foreach (array_keys($this->places) as $place) {
            $data[$this->field($place)] = PARAM_INT;
        }
        return $data;
    }

    /**
     * The response field name of a gap.
     *
     * @param int $place the 1-based gap number.
     * @return string
     */
    public function field(int $place): string {
        return 'p' . $place;
    }

    #[\Override]
    public function get_correct_response() {
        $response = [];
        foreach ($this->places as $place => $choiceno) {
            $response[$this->field($place)] = $choiceno;
        }
        return $response;
    }

    #[\Override]
    public function is_complete_response(array $response) {
        foreach (array_keys($this->places) as $place) {
            if (!$this->is_filled($response, $place)) {
                return false;
            }
        }
        return !empty($this->places);
    }

    #[\Override]
    public function is_gradable_response(array $response) {
        foreach (array_keys($this->places) as $place) {
            if ($this->is_filled($response, $place)) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function get_validation_error(array $response) {
        if ($this->is_complete_response($response)) {
            return '';
        }
        return get_string('pleasefillallgaps', 'qtype_ddto_chill');
    }

    #[\Override]
    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach (array_keys($this->places) as $place) {
            $field = $this->field($place);
            $prev = (int) ($prevresponse[$field] ?? 0);
            $new = (int) ($newresponse[$field] ?? 0);
            if ($prev !== $new) {
                return false;
            }
        }
        return true;
    }

    #[\Override]
    public function summarise_response(array $response) {
        $bits = [];
        foreach ($this->places as $place => $unused) {
            $choiceno = (int) ($response[$this->field($place)] ?? 0);
            if ($choiceno && isset($this->choices[$choiceno])) {
                $bits[] = $this->choices[$choiceno]->text;
            } else {
                $bits[] = '–';
            }
        }
        if (empty($bits)) {
            return null;
        }
        return implode('; ', $bits);
    }

    #[\Override]
    public function classify_response(array $response) {
        $numgaps = count($this->places);
        $classified = [];
        foreach ($this->places as $place => $rightno) {
            $choiceno = (int) ($response[$this->field($place)] ?? 0);
            if (!$choiceno || !isset($this->choices[$choiceno])) {
                $classified[$place] = question_classified_response::no_response();
                continue;
            }
            $correct = $this->is_correct_placement($place, $choiceno);
            if ($correct) {
                $fraction = $numgaps > 0 ? 1 / $numgaps : 0;
            } else {
                $fraction = -min(1.0, max(0.0, -(float) $this->negativemarking));
            }
            $classified[$place] = new question_classified_response(
                $choiceno,
                $this->choices[$choiceno]->text,
                $fraction
            );
        }
        return $classified;
    }

    #[\Override]
    public function grade_response(array $response) {
        [$numcorrect, $numwrong] = $this->count_filled_gaps($response);
        $fraction = self::compute_fraction(
            $numcorrect,
            $numwrong,
            count($this->places),
            (float) $this->negativemarking,
            !empty($this->allornothing)
        );
        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    #[\Override]
    public function get_min_fraction() {
        $numgaps = count($this->places);
        return self::compute_fraction(
            0,
            $numgaps,
            $numgaps,
            (float) $this->negativemarking,
            !empty($this->allornothing)
        );
    }

    #[\Override]
    public function get_num_parts_right(array $response) {
        [$numcorrect] = $this->count_filled_gaps($response);
        return [$numcorrect, count($this->places)];
    }

    #[\Override]
    public function clear_wrong_from_response(array $response) {
        foreach ($this->places as $place => $unused) {
            $field = $this->field($place);
            $choiceno = (int) ($response[$field] ?? 0);
            if ($choiceno && !$this->is_correct_placement($place, $choiceno)) {
                $response[$field] = 0;
            }
        }
        return $response;
    }

    #[\Override]
    public function compute_final_grade($responses, $totaltries) {
        $fraction = 0.0;
        foreach ($responses as $response) {
            [$fraction] = $this->grade_response($response);
        }
        $tries = count($responses);
        return max(0, $fraction - ($tries - 1) * $this->penalty);
    }

    /**
     * Count the correctly and incorrectly filled gaps in a response.
     *
     * Empty gaps are counted in neither total.
     *
     * @param array $response the response, as returned by {@see question_attempt_step::get_qt_data()}.
     * @return int[] two integers: correctly filled gaps, incorrectly filled gaps.
     */
    public function count_filled_gaps(array $response): array {
        $numcorrect = 0;
        $numwrong = 0;
        foreach (array_keys($this->places) as $place) {
            if (!$this->is_filled($response, $place)) {
                continue;
            }
            $choiceno = (int) $response[$this->field($place)];
            if ($this->is_correct_placement($place, $choiceno)) {
                $numcorrect++;
            } else {
                $numwrong++;
            }
        }
        return [$numcorrect, $numwrong];
    }

    /**
     * Whether a gap has a choice placed in it.
     *
     * @param array $response the response.
     * @param int $place the gap number.
     * @return bool
     */
    public function is_filled(array $response, int $place): bool {
        $choiceno = (int) ($response[$this->field($place)] ?? 0);
        return $choiceno > 0 && isset($this->choices[$choiceno]);
    }

    /**
     * Whether the given choice is a correct placement for the gap.
     *
     * Matching is by choice text so two gaps that expect the same word remain
     * interchangeable even though each has its own stored choice number.
     *
     * @param int $place the gap number.
     * @param int $choiceno the placed choice number.
     * @return bool
     */
    public function is_correct_placement(int $place, int $choiceno): bool {
        if (!isset($this->places[$place]) || !isset($this->choices[$choiceno])) {
            return false;
        }
        $expected = $this->places[$place];
        if (!isset($this->choices[$expected])) {
            return false;
        }
        return $this->choices[$choiceno]->text === $this->choices[$expected]->text;
    }

    /**
     * Compute the fraction scored by a response, according to the Chill grading rules.
     *
     * This is the single place where the grading model is defined; it is also
     * used by the question type to compute the random guess score. The formula
     * is the same as {@see qtype_mcq_chill_question::compute_fraction()}, with
     * correctly / incorrectly filled gaps standing in for ticked choices.
     *
     * @param int $numcorrectselected number of correctly filled gaps.
     * @param int $numwrongselected number of incorrectly filled gaps.
     * @param int $numcorrect total number of gaps.
     * @param float $negativemarking penalty for each incorrectly filled gap, between -1 and 0.
     * @param bool $allornothing whether the all-or-nothing mode is enabled.
     * @return float the fraction, between -1 and 1.
     */
    public static function compute_fraction(
        int $numcorrectselected,
        int $numwrongselected,
        int $numcorrect,
        float $negativemarking,
        bool $allornothing
    ): float {
        $penalty = $numwrongselected * min(1.0, max(0.0, -$negativemarking));

        if ($allornothing) {
            $fullycorrect = $numcorrect > 0 && $numcorrectselected === $numcorrect && $numwrongselected === 0;
            $earned = $fullycorrect ? 1.0 : 0.0;
        } else if ($numcorrect > 0) {
            $earned = $numcorrectselected / $numcorrect;
        } else {
            $earned = 0.0;
        }

        return max(self::MIN_FRACTION, min(self::MAX_FRACTION, $earned - $penalty));
    }
}
