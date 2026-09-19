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
 * Test helpers for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test helper class for the Drag-drop into text Chill question type.
 *
 * Every test question uses the sentence "The cat sat on the mat" with gaps
 * on "cat" and "mat" and the distractors "dog" and "hat":
 *
 * - 'catmat': negative marking -50%, partial credit;
 * - 'allornothing': negative marking -25%, all-or-nothing mode;
 * - 'nopenalty': no negative marking, partial credit.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddto_chill_test_helper extends question_test_helper {
    #[\Override]
    public function get_test_questions() {
        return ['catmat', 'allornothing', 'nopenalty'];
    }

    /**
     * The settings of each test question.
     *
     * @param string $which the name of the test question.
     * @return array with keys name, negativemarking and allornothing.
     */
    protected static function get_settings(string $which): array {
        $settings = [
            'catmat' => ['name' => 'DDTO Chill cat mat', 'negativemarking' => -0.5, 'allornothing' => 0],
            'allornothing' => ['name' => 'DDTO Chill all or nothing', 'negativemarking' => -0.25, 'allornothing' => 1],
            'nopenalty' => ['name' => 'DDTO Chill no penalty', 'negativemarking' => 0, 'allornothing' => 0],
        ];
        return $settings[$which];
    }

    /**
     * The choices of the test questions, keyed by choice number.
     *
     * @return array choice number => [text, fraction].
     */
    protected static function get_choices(): array {
        return [
            1 => ['cat', 1.0],
            2 => ['mat', 1.0],
            3 => ['dog', 0.0],
            4 => ['hat', 0.0],
        ];
    }

    /**
     * Make a question definition object for one of the test questions.
     *
     * @param string $which the name of the test question.
     * @return qtype_ddto_chill_question the question.
     */
    protected static function make_question(string $which): qtype_ddto_chill_question {
        question_bank::load_question_definition_classes('ddto_chill');
        $settings = self::get_settings($which);

        $q = new qtype_ddto_chill_question();
        test_question_maker::initialise_a_question($q);
        $q->name = $settings['name'];
        $q->questiontext = 'The [[1]] sat on the [[2]]';
        $q->generalfeedback = 'The cat sat on the mat.';
        $q->qtype = question_bank::get_qtype('ddto_chill');

        $q->negativemarking = $settings['negativemarking'];
        $q->allornothing = $settings['allornothing'];
        $q->shuffleanswers = 1;

        $q->choices = [];
        $q->places = [];
        foreach (self::get_choices() as $no => [$text, $fraction]) {
            $q->choices[$no] = (object) [
                'id' => $no,
                'text' => $text,
                'choicegroup' => qtype_ddto_chill_question::DEFAULT_GROUP,
                'fraction' => $fraction,
            ];
        }
        $q->places = [1 => 1, 2 => 2];

        return $q;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     *
     * @param string $which the name of the test question.
     * @return stdClass the question data.
     */
    protected static function get_question_data(string $which): stdClass {
        $settings = self::get_settings($which);

        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);
        $qdata->qtype = 'ddto_chill';
        $qdata->name = $settings['name'];
        $qdata->questiontext = 'The [[1]] sat on the [[2]]';
        $qdata->generalfeedback = 'The cat sat on the mat.';

        $qdata->options = new stdClass();
        $qdata->options->negativemarking = $settings['negativemarking'];
        $qdata->options->allornothing = $settings['allornothing'];
        $qdata->options->shuffleanswers = 1;
        $qdata->options->answers = [];
        $qdata->options->choices = [];
        foreach (self::get_choices() as $no => [$text, $fraction]) {
            $qdata->options->choices[$no] = (object) [
                'id' => $no,
                'questionid' => 0,
                'text' => $text,
                'choicegroup' => qtype_ddto_chill::DEFAULT_GROUP,
                'fraction' => $fraction,
            ];
        }

        return $qdata;
    }

    /**
     * Get the data that would be submitted by the editing form.
     *
     * @param string $which the name of the test question.
     * @return stdClass the form data.
     */
    protected static function get_question_form_data(string $which): stdClass {
        $settings = self::get_settings($which);

        $form = new stdClass();
        $form->name = $settings['name'];
        $form->questiontext = ['text' => 'The [[1]] sat on the [[2]]', 'format' => FORMAT_HTML];
        $form->defaultmark = 1.0;
        $form->generalfeedback = ['text' => 'The cat sat on the mat.', 'format' => FORMAT_HTML];
        $form->sourcetext = 'The cat sat on the mat';
        // Words: The(0) cat(1) sat(2) on(3) the(4) mat(5).
        $form->gapselection = '1,5';
        $form->negativemarking = $settings['negativemarking'] == 0 ? '0.0' : (string) $settings['negativemarking'];
        $form->allornothing = $settings['allornothing'];
        $form->shuffleanswers = 1;
        $form->answer = ['dog', 'hat'];
        $form->noanswers = 2;
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

        return $form;
    }

    /**
     * Make the 'catmat' test question.
     *
     * @return qtype_ddto_chill_question
     */
    public function make_ddto_chill_question_catmat(): qtype_ddto_chill_question {
        return self::make_question('catmat');
    }

    /**
     * Get the question data of the 'catmat' test question.
     *
     * @return stdClass
     */
    public function get_ddto_chill_question_data_catmat(): stdClass {
        return self::get_question_data('catmat');
    }

    /**
     * Get the form data of the 'catmat' test question.
     *
     * @return stdClass
     */
    public function get_ddto_chill_question_form_data_catmat(): stdClass {
        return self::get_question_form_data('catmat');
    }

    /**
     * Make the 'allornothing' test question.
     *
     * @return qtype_ddto_chill_question
     */
    public function make_ddto_chill_question_allornothing(): qtype_ddto_chill_question {
        return self::make_question('allornothing');
    }

    /**
     * Get the question data of the 'allornothing' test question.
     *
     * @return stdClass
     */
    public function get_ddto_chill_question_data_allornothing(): stdClass {
        return self::get_question_data('allornothing');
    }

    /**
     * Get the form data of the 'allornothing' test question.
     *
     * @return stdClass
     */
    public function get_ddto_chill_question_form_data_allornothing(): stdClass {
        return self::get_question_form_data('allornothing');
    }

    /**
     * Make the 'nopenalty' test question.
     *
     * @return qtype_ddto_chill_question
     */
    public function make_ddto_chill_question_nopenalty(): qtype_ddto_chill_question {
        return self::make_question('nopenalty');
    }

    /**
     * Get the question data of the 'nopenalty' test question.
     *
     * @return stdClass
     */
    public function get_ddto_chill_question_data_nopenalty(): stdClass {
        return self::get_question_data('nopenalty');
    }

    /**
     * Get the form data of the 'nopenalty' test question.
     *
     * @return stdClass
     */
    public function get_ddto_chill_question_form_data_nopenalty(): stdClass {
        return self::get_question_form_data('nopenalty');
    }
}
