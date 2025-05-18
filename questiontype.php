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
 * Question type definition for QCM Chill
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

class qtype_mcq_chill extends qtype_multichoice {
    /**
     * Enregistre les options spécifiques de la question (points négatifs, tout ou rien).
     */
    public function save_question_options($question) {
        global $DB;
        parent::save_question_options($question);
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $question->id]);
        $data = [
            'questionid' => $question->id,
            'negativemarking' => isset($question->negativemarking) ? $question->negativemarking : 0,
            'allornothing' => isset($question->allornothing) ? $question->allornothing : 0,
        ];
        if ($options) {
            $DB->update_record('qtype_mcq_chill_options', $data);
        } else {
            $DB->insert_record('qtype_mcq_chill_options', $data);
        }
    }

    /**
     * Charge les options spécifiques de la question.
     */
    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $question->id]);
        if ($options) {
            $question->negativemarking = $options->negativemarking;
            $question->allornothing = $options->allornothing;
        } else {
            $question->negativemarking = 0;
            $question->allornothing = 0;
        }
    }

    /**
     * Additional DB fields to save with the question.
     */
    public function extra_question_fields() {
        return ['qtype_mcq_chill_options', 'negativemarking', 'allornothing'];
    }

    /**
     * Calcule la note pour une tentative.
     */
    public function grade_response($question, $response) {
        $answers = $question->options->answers;
        $useranswers = isset($response['answer']) ? (array)$response['answer'] : [];
        $negativemarking = isset($question->negativemarking) ? $question->negativemarking : 0;
        $allornothing = !empty($question->allornothing);

        $good = 0;
        $bad = 0;
        foreach ($answers as $idx => $ans) {
            $iscorrect = $ans->fraction > 0.0;
            $checked = in_array($idx, $useranswers);
            if ($checked && $iscorrect) {
                $good++;
            } else if ($checked && !$iscorrect) {
                $bad++;
            }
        }

        $totalcorrect = $this->count_correct($answers);
        if ($allornothing) {
            if ($bad == 0 && $good == $totalcorrect) {
                return [1.0, question_state::$gradedright];
            }
            $grade = $negativemarking / 100.0;
            return [$grade, question_state::$gradedwrong];
        }

        $score = 0;
        if ($totalcorrect > 0) {
            $score = $good / $totalcorrect;
        }
        $score -= $bad * abs($negativemarking) / 100.0;
        $score = max(0, min(1, $score));
        $state = question_state::$gradedpartial;
        if ($score == 1.0) {
            $state = question_state::$gradedright;
        } else if ($score == 0) {
            $state = question_state::$gradedwrong;
        }
        return [$score, $state];
    }

    /**
     * Compte le nombre de bonnes réponses.
     */
    protected function count_correct($answers) {
        $count = 0;
        foreach ($answers as $ans) {
            if ($ans->fraction > 0.0) {
                $count++;
            }
        }
        return $count;
    }
}

