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
 * Test helpers for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test helper class for the QCM Chill question type.
 *
 * Every test question asks "Which are the odd numbers?" with the choices
 * One, Two, Three and Four (One and Three being correct):
 *
 * - 'twooffour': negative marking -50%, partial credit;
 * - 'allornothing': negative marking -25%, all-or-nothing mode;
 * - 'nopenalty': no negative marking, partial credit.
 *
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcq_chill_test_helper extends question_test_helper {
    #[\Override]
    public function get_test_questions() {
        return ['twooffour', 'allornothing', 'nopenalty'];
    }

    /**
     * The settings of each test question.
     *
     * @param string $which the name of the test question.
     * @return array with keys name, negativemarking and allornothing.
     */
    protected static function get_settings(string $which): array {
        $settings = [
            'twooffour' => ['name' => 'QCM Chill two of four', 'negativemarking' => -0.5, 'allornothing' => 0],
            'allornothing' => ['name' => 'QCM Chill all or nothing', 'negativemarking' => -0.25, 'allornothing' => 1],
            'nopenalty' => ['name' => 'QCM Chill no penalty', 'negativemarking' => 0, 'allornothing' => 0],
        ];
        return $settings[$which];
    }

    /**
     * The choices of the test questions, keyed by answer id.
     *
     * @return array answer id => [text, fraction].
     */
    protected static function get_choices(): array {
        return [
            13 => ['One', 1.0],
            14 => ['Two', 0.0],
            15 => ['Three', 1.0],
            16 => ['Four', 0.0],
        ];
    }

    /**
     * Make a question definition object for one of the test questions.
     *
     * @param string $which the name of the test question.
     * @return qtype_mcq_chill_question the question.
     */
    protected static function make_question(string $which): qtype_mcq_chill_question {
        question_bank::load_question_definition_classes('mcq_chill');
        $settings = self::get_settings($which);

        $q = new qtype_mcq_chill_question();
        test_question_maker::initialise_a_question($q);
        $q->name = $settings['name'];
        $q->questiontext = 'Which are the odd numbers?';
        $q->generalfeedback = 'The odd numbers are One and Three.';
        $q->qtype = question_bank::get_qtype('mcq_chill');

        $q->negativemarking = $settings['negativemarking'];
        $q->allornothing = $settings['allornothing'];
        $q->shuffleanswers = 1;
        // Same values as qtype_mcq_chill::initialise_question_instance().
        $q->answernumbering = 'none';
        $q->showstandardinstruction = (int) get_config('qtype_multichoice', 'showstandardinstruction');
        $q->layout = qtype_multichoice_base::LAYOUT_VERTICAL;
        $q->correctfeedback = get_string('correctfeedbackdefault', 'question');
        $q->correctfeedbackformat = FORMAT_HTML;
        $q->partiallycorrectfeedback = get_string('partiallycorrectfeedbackdefault', 'question');
        $q->partiallycorrectfeedbackformat = FORMAT_HTML;
        $q->incorrectfeedback = get_string('incorrectfeedbackdefault', 'question');
        $q->incorrectfeedbackformat = FORMAT_HTML;

        $q->answers = [];
        foreach (self::get_choices() as $id => [$text, $fraction]) {
            $answer = new question_answer($id, $text, $fraction, '', FORMAT_HTML);
            $answer->answerformat = FORMAT_PLAIN;
            $q->answers[$id] = $answer;
        }

        return $q;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options, for one of the test questions.
     *
     * @param string $which the name of the test question.
     * @return stdClass the question data.
     */
    protected static function get_question_data(string $which): stdClass {
        $settings = self::get_settings($which);

        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);
        $qdata->qtype = 'mcq_chill';
        $qdata->name = $settings['name'];
        $qdata->questiontext = 'Which are the odd numbers?';
        $qdata->generalfeedback = 'The odd numbers are One and Three.';

        $qdata->options = new stdClass();
        $qdata->options->negativemarking = $settings['negativemarking'];
        $qdata->options->allornothing = $settings['allornothing'];
        $qdata->options->shuffleanswers = 1;
        $qdata->options->answers = [];
        foreach (self::get_choices() as $id => [$text, $fraction]) {
            $qdata->options->answers[$id] = (object) [
                'id' => $id,
                'answer' => $text,
                'answerformat' => FORMAT_PLAIN,
                'fraction' => $fraction,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
            ];
        }

        return $qdata;
    }

    /**
     * Get the data that would be submitted by the editing form for one of the test questions.
     *
     * @param string $which the name of the test question.
     * @return stdClass the form data.
     */
    protected static function get_question_form_data(string $which): stdClass {
        $settings = self::get_settings($which);

        $form = new stdClass();
        $form->name = $settings['name'];
        $form->questiontext = ['text' => 'Which are the odd numbers?', 'format' => FORMAT_HTML];
        $form->defaultmark = 1.0;
        $form->generalfeedback = ['text' => 'The odd numbers are One and Three.', 'format' => FORMAT_HTML];
        // The exact key of the negative marking select.
        $form->negativemarking = $settings['negativemarking'] == 0 ? '0.0' : (string) $settings['negativemarking'];
        $form->allornothing = $settings['allornothing'];
        $form->shuffleanswers = 1;
        $form->answer = [];
        $form->fraction = [];
        foreach (self::get_choices() as [$text, $fraction]) {
            $form->answer[] = $text;
            $form->fraction[] = $fraction > 0 ? 1 : 0;
        }
        $form->noanswers = count($form->answer);
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

        return $form;
    }

    /**
     * Make the 'twooffour' test question.
     *
     * @return qtype_mcq_chill_question
     */
    public function make_mcq_chill_question_twooffour(): qtype_mcq_chill_question {
        return self::make_question('twooffour');
    }

    /**
     * Get the question data of the 'twooffour' test question.
     *
     * @return stdClass
     */
    public function get_mcq_chill_question_data_twooffour(): stdClass {
        return self::get_question_data('twooffour');
    }

    /**
     * Get the form data of the 'twooffour' test question.
     *
     * @return stdClass
     */
    public function get_mcq_chill_question_form_data_twooffour(): stdClass {
        return self::get_question_form_data('twooffour');
    }

    /**
     * Make the 'allornothing' test question.
     *
     * @return qtype_mcq_chill_question
     */
    public function make_mcq_chill_question_allornothing(): qtype_mcq_chill_question {
        return self::make_question('allornothing');
    }

    /**
     * Get the question data of the 'allornothing' test question.
     *
     * @return stdClass
     */
    public function get_mcq_chill_question_data_allornothing(): stdClass {
        return self::get_question_data('allornothing');
    }

    /**
     * Get the form data of the 'allornothing' test question.
     *
     * @return stdClass
     */
    public function get_mcq_chill_question_form_data_allornothing(): stdClass {
        return self::get_question_form_data('allornothing');
    }

    /**
     * Make the 'nopenalty' test question.
     *
     * @return qtype_mcq_chill_question
     */
    public function make_mcq_chill_question_nopenalty(): qtype_mcq_chill_question {
        return self::make_question('nopenalty');
    }

    /**
     * Get the question data of the 'nopenalty' test question.
     *
     * @return stdClass
     */
    public function get_mcq_chill_question_data_nopenalty(): stdClass {
        return self::get_question_data('nopenalty');
    }

    /**
     * Get the form data of the 'nopenalty' test question.
     *
     * @return stdClass
     */
    public function get_mcq_chill_question_form_data_nopenalty(): stdClass {
        return self::get_question_form_data('nopenalty');
    }
}
