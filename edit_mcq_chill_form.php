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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/multichoice/edit_multichoice_form.php');

class qtype_mcq_chill_edit_form extends qtype_multichoice_edit_form {
    protected function definition_inner($mform) {
        parent::definition_inner($mform);

        $options = [];
        for ($i = -100; $i <= 0; $i++) {
            $options[$i] = $i.'%';
        }
        $mform->addElement('select', 'negativemarking', get_string('negativemarking', 'qtype_mcq_chill'), $options);
        $mform->setDefault('negativemarking', 0);

        $mform->addElement('advcheckbox', 'allornothing', get_string('allornothing', 'qtype_mcq_chill'));
        $mform->addHelpButton('allornothing', 'allornothing', 'qtype_mcq_chill');
    }

    public function set_data($question) {
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

