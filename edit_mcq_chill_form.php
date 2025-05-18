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
    /**
     * Build the QCM Chill editing form.
     * Only keep the question text, answers with checkboxes, negative marking
     * and the all-or-nothing option.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function definition_inner($mform) {
        // Start from a clean form instead of the multichoice one.
        // Question name and text are added by the base question_edit_form.

        // Repeat answer fields with a checkbox indicating correct choices.
        $repeated = [];
        $repeated[] = $mform->createElement('text', 'answer[{no}]',
            get_string('choicetext', 'qtype_mcq_chill'), ['size' => 40]);
        $repeated[] = $mform->createElement('advcheckbox', 'fraction[{no}]', '',
            get_string('correctanswer', 'qtype_mcq_chill'), [], [1, 0]);

        $repeatedoptions = [];
        $repeatedoptions['fraction[{no}]']['default'] = 0;

        $this->repeat_elements($repeated, 4, $repeatedoptions, 'noanswers',
            'addanswers', 1, get_string('addmoreanswerblanks', 'qtype_multichoice'));

        // Dropdown for the negative mark applied to each wrong checkbox.
        $options = [];
        for ($i = -100; $i <= 0; $i++) {
            $options[$i] = $i . '%';
        }
        $mform->addElement('select', 'negativemarking',
            get_string('negativemarking', 'qtype_mcq_chill'), $options);
        $mform->setDefault('negativemarking', 0);

        // All or nothing option with help.
        $mform->addElement('advcheckbox', 'allornothing',
            get_string('allornothing', 'qtype_mcq_chill'));
        $mform->addHelpButton('allornothing', 'allornothing', 'qtype_mcq_chill');
    }

    public function set_data($question) {
        if (isset($question->options)) {
            $question->negativemarking = $question->options->negativemarking;
            $question->allornothing = $question->options->allornothing;
            if (!empty($question->options->answers)) {
                $i = 0;
                foreach ($question->options->answers as $ans) {
                    $question->answer[$i] = $ans->answer;
                    $question->fraction[$i] = $ans->fraction > 0 ? 1 : 0;
                    $i++;
                }
            }
        }
        parent::set_data($question);
    }

    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        if (isset($question->options)) {
            $question->negativemarking = $question->options->negativemarking;
            $question->allornothing = $question->options->allornothing;
            if (!empty($question->options->answers)) {
                $i = 0;
                foreach ($question->options->answers as $ans) {
                    $question->answer[$i] = $ans->answer;
                    $question->fraction[$i] = $ans->fraction > 0 ? 1 : 0;
                    $i++;
                }
            }
        }
        return $question;
    }
}

