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
 * Form for editing QCM Chill questions.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_mcq_chill_edit_form extends question_edit_form {
    protected function definition_inner($mform) {
        // Champ pour les réponses.
        $repeatsatstart = 4;
        $this->repeat_elements([
            $mform->createElement('text', 'answer', get_string('choicetext', 'qtype_mcq_chill')),
            $mform->createElement('advcheckbox', 'correct', get_string('correctanswer', 'qtype_mcq_chill')),
        ], $repeatsatstart, [], 'numanswers', 'addanswers', 1, get_string('addmorechoices', 'qtype_multichoice', 1));

        // Sélecteur de points négatifs.
        $mform->addElement('float', 'negativemarking', get_string('negativemarking', 'qtype_mcq_chill'), ['min' => -100, 'max' => 0, 'step' => 1]);
        $mform->setDefault('negativemarking', 0);
        $mform->addRule('negativemarking', null, 'numeric', null, 'client');

        // Case tout ou rien.
        $mform->addElement('advcheckbox', 'allornothing', get_string('allornothing', 'qtype_mcq_chill'));
        $mform->addHelpButton('allornothing', 'allornothing', 'qtype_mcq_chill');
    }

    public function set_data($question) {
        // Pré-remplir les champs personnalisés si édition.
        if (isset($question->options)) {
            $question->negativemarking = $question->options->negativemarking;
            $question->allornothing = $question->options->allornothing;
        }
        parent::set_data($question);
    }

    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        if (isset($question->options)) {
            $question->negativemarking = $question->options->negativemarking;
            $question->allornothing = $question->options->allornothing;
        }
        return $question;
    }
}
