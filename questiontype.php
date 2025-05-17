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

require_once($CFG->dirroot . '/question/type/shortanswer/questiontype.php');

class qtype_mcq_chill extends question_type {
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
     * Calcule la note pour une tentative.
     */
    public function grade_response($question, $response) {
        // $response['answer'] = tableau des cases cochées (indexées comme les réponses).
        $correct = $question->options->answers; // À adapter selon la structure réelle.
        $useranswers = isset($response['answer']) ? $response['answer'] : [];
        $allornothing = !empty($question->allornothing);
        $negativemarking = isset($question->negativemarking) ? $question->negativemarking : 0;
        $total = count($correct);
        $good = 0;
        $bad = 0;
        foreach ($correct as $i => $ans) {
            $iscorrect = $ans->fraction > 0.0;
            $checked = in_array($i, $useranswers);
            if ($iscorrect && $checked) {
                $good++;
            } else if (!$iscorrect && $checked) {
                $bad++;
            }
        }
        if ($allornothing) {
            if ($bad == 0 && $good == $this->count_correct($correct)) {
                return [1.0, question_state::$gradedright];
            } else {
                return [$negativemarking / 100.0, question_state::$gradedwrong];
            }
        } else {
            $score = ($good - $bad * abs($negativemarking) / 100.0) / $this->count_correct($correct);
            $score = max(0, min(1, $score));
            return [$score, $score == 1.0 ? question_state::$gradedright : question_state::$gradedpartial];
        }
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
