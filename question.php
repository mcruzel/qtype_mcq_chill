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
 * Question definition class for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/multichoice/question.php');

/**
 * Represents a QCM Chill question during an attempt.
 *
 * A QCM Chill question is a multiple-answer question. The response handling
 * and the rendering are inherited from the core multiple choice (multiple
 * answers) question; only the grading differs:
 *
 * - each correct choice selected earns an equal share of the mark
 *   (1 / number of correct choices);
 * - each wrong choice selected costs the negative marking fraction;
 * - in all-or-nothing mode the mark is only awarded when every correct
 *   choice and no wrong choice is selected, otherwise the response scores
 *   zero minus the negative marking of the wrong choices selected;
 * - the resulting fraction is bounded between -1 and 1.
 *
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcq_chill_question extends qtype_multichoice_multi_question {
    /** @var float the lowest fraction a response can score (minus the whole mark of the question). */
    const MIN_FRACTION = -1.0;

    /** @var float the highest fraction a response can score. */
    const MAX_FRACTION = 1.0;

    /** @var float penalty for each wrong choice selected, as a fraction of the mark, between -1 and 0. */
    public $negativemarking = 0.0;

    /** @var int 1 when the mark is only awarded for a fully correct response, 0 otherwise. */
    public $allornothing = 0;

    #[\Override]
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('qtype_mcq_chill');
    }

    #[\Override]
    public function get_min_fraction() {
        // The worst response selects every wrong choice and no correct one.
        $numcorrect = $this->get_num_correct_choices();
        $numwrong = count($this->answers) - $numcorrect;
        return self::compute_fraction(
            0,
            $numwrong,
            $numcorrect,
            (float) $this->negativemarking,
            !empty($this->allornothing)
        );
    }

    #[\Override]
    public function grade_response(array $response) {
        [$numcorrectselected, $numwrongselected] = $this->count_selected_choices($response);
        $fraction = self::compute_fraction(
            $numcorrectselected,
            $numwrongselected,
            $this->get_num_correct_choices(),
            (float) $this->negativemarking,
            !empty($this->allornothing)
        );
        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    #[\Override]
    public function classify_response(array $response) {
        // Report the real contribution of each selected choice to the mark, consistently
        // with qtype_mcq_chill::get_possible_responses(), rather than the stored 1/0 fraction.
        $numcorrect = $this->get_num_correct_choices();
        $selected = [];
        foreach ($this->order as $key => $ansid) {
            if (!empty($response[$this->field($key)])) {
                $selected[$ansid] = true;
            }
        }

        $choices = [];
        foreach ($this->answers as $ansid => $ans) {
            if (!isset($selected[$ansid])) {
                continue;
            }
            if ($ans->fraction > 0) {
                $fraction = $numcorrect > 0 ? 1 / $numcorrect : 0;
            } else {
                $fraction = -min(1.0, abs((float) $this->negativemarking));
            }
            $choices[$ansid] = new question_classified_response(
                $ansid,
                $this->html_to_text($ans->answer, $ans->answerformat),
                $fraction
            );
        }
        return $choices;
    }

    /**
     * Count the correct and the wrong choices selected in a response.
     *
     * @param array $response the response, as returned by {@see question_attempt_step::get_qt_data()}.
     * @return int[] two integers: the number of correct choices selected and the number of wrong choices selected.
     */
    public function count_selected_choices(array $response): array {
        $numcorrectselected = 0;
        $numwrongselected = 0;
        foreach ($this->order as $key => $ansid) {
            if (empty($response[$this->field($key)])) {
                continue;
            }
            if ($this->answers[$ansid]->fraction > 0) {
                $numcorrectselected++;
            } else {
                $numwrongselected++;
            }
        }
        return [$numcorrectselected, $numwrongselected];
    }

    /**
     * Compute the fraction scored by a response, according to the QCM Chill grading rules.
     *
     * This is the single place where the grading model is defined; it is also
     * used by the question type to compute the random guess score.
     *
     * @param int $numcorrectselected number of correct choices selected.
     * @param int $numwrongselected number of wrong choices selected.
     * @param int $numcorrect total number of correct choices in the question.
     * @param float $negativemarking penalty for each wrong choice selected, between -1 and 0.
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
        $penalty = $numwrongselected * min(1.0, abs($negativemarking));

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
