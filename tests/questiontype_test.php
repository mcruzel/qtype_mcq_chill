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

namespace qtype_mcq_chill;

use qtype_mcq_chill;
use qtype_mcq_chill_edit_form;
use qtype_mcq_chill_test_helper;
use question_bank;
use question_possible_response;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/mcq_chill/questiontype.php');
require_once($CFG->dirroot . '/question/type/mcq_chill/edit_mcq_chill_form.php');

/**
 * Unit tests for the QCM Chill question type class.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_mcq_chill
 * @covers     \qtype_mcq_chill_edit_form
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_mcq_chill::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_mcq_chill_edit_form::class)]
final class questiontype_test extends \advanced_testcase {
    /** @var qtype_mcq_chill the question type being tested. */
    protected $qtype;

    protected function setUp(): void {
        parent::setUp();
        $this->qtype = question_bank::get_qtype('mcq_chill');
    }

    protected function tearDown(): void {
        $this->qtype = null;
        parent::tearDown();
    }

    /**
     * Build the editing form of an existing question, as the editing page does (with its options).
     *
     * @param \stdClass $cat the question category.
     * @param \stdClass $questiondata the question data, as loaded by question_bank::load_question_data().
     * @return qtype_mcq_chill_edit_form the form.
     */
    protected function get_editing_form_of(\stdClass $cat, \stdClass $questiondata): qtype_mcq_chill_edit_form {
        global $PAGE;
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $questiondata->formoptions = (object) [
            'canmove' => true, 'cansaveasnew' => true, 'canedit' => true, 'repeatelements' => true,
        ];
        $questiondata->beingcopied = false;
        $contexts = new \core_question\local\bank\question_edit_contexts(\context::instance_by_id($cat->contextid));
        return $this->qtype->create_editing_form('question.php', $questiondata, $cat, $contexts, true);
    }

    public function test_name(): void {
        $this->assertEquals('mcq_chill', $this->qtype->name());
    }

    public function test_can_analyse_responses(): void {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_extra_question_fields(): void {
        $this->assertEquals(
            ['qtype_mcq_chill_options', 'negativemarking', 'allornothing', 'shuffleanswers'],
            $this->qtype->extra_question_fields()
        );
        $this->assertEquals('questionid', $this->qtype->questionid_column_name());
    }

    /**
     * Cases for test_get_random_guess_score.
     *
     * @return array[]
     */
    public static function random_guess_score_provider(): array {
        // Average of the fraction over the 15 non-empty combinations of four choices, computed by hand.
        return [
            'partial credit, -50%' => ['twooffour', 0.0],
            'all or nothing, -25%' => ['allornothing', -3.0 / 15],
            'partial credit, no penalty' => ['nopenalty', 8.0 / 15],
        ];
    }

    /**
     * Test the random guess score.
     *
     * @dataProvider random_guess_score_provider
     * @param string $which the test question.
     * @param float $expected the expected score.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('random_guess_score_provider')]
    public function test_get_random_guess_score(string $which, float $expected): void {
        $qdata = test_question_maker::get_question_data('mcq_chill', $which);
        $this->assertEqualsWithDelta($expected, $this->qtype->get_random_guess_score($qdata), 0.0000001);
    }

    public function test_get_random_guess_score_broken_question(): void {
        $qdata = test_question_maker::get_question_data('mcq_chill', 'twooffour');
        $qdata->options->answers = [];
        $this->assertNull($this->qtype->get_random_guess_score($qdata));
    }

    public function test_get_random_guess_score_too_many_choices(): void {
        $qdata = test_question_maker::get_question_data('mcq_chill', 'twooffour');
        for ($i = 17; $i <= 30; $i++) {
            $qdata->options->answers[$i] = (object) ['id' => $i, 'answer' => "Choice {$i}", 'answerformat' => FORMAT_HTML,
                'fraction' => 0.0, 'feedback' => '', 'feedbackformat' => FORMAT_HTML];
        }
        $this->assertNull($this->qtype->get_random_guess_score($qdata));
    }

    /**
     * Cases for test_get_possible_responses.
     *
     * @return array[]
     */
    public static function possible_responses_provider(): array {
        return [
            'partial credit, -50%' => ['twooffour', [13 => 0.5, 14 => -0.5, 15 => 0.5, 16 => -0.5]],
            'all or nothing, -25%' => ['allornothing', [13 => 0.5, 14 => -0.25, 15 => 0.5, 16 => -0.25]],
            'no penalty' => ['nopenalty', [13 => 0.5, 14 => 0.0, 15 => 0.5, 16 => 0.0]],
        ];
    }

    /**
     * Test the possible responses of the response analysis.
     *
     * @dataProvider possible_responses_provider
     * @param string $which the test question.
     * @param array $expectedfractions answer id => fraction.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('possible_responses_provider')]
    public function test_get_possible_responses(string $which, array $expectedfractions): void {
        $qdata = test_question_maker::get_question_data('mcq_chill', $which);
        $expected = [];
        foreach ($expectedfractions as $ansid => $fraction) {
            $expected[$ansid] = [$ansid => new question_possible_response($qdata->options->answers[$ansid]->answer, $fraction)];
        }
        $this->assertEquals($expected, $this->qtype->get_possible_responses($qdata));
    }

    /**
     * Cases for test_clean_negative_marking.
     *
     * @return array[]
     */
    public static function clean_negative_marking_provider(): array {
        return [
            'valid fraction' => ['-0.5', -0.5],
            'float' => [-0.3333333, -0.3333333],
            'zero' => ['0.0', 0.0],
            'below the floor' => [-1.5, -1.0],
            'positive value' => [0.5, 0.0],
            'not numeric' => ['abc', 0.0],
            'null' => [null, 0.0],
            'too precise' => [-0.33333333333, -0.3333333],
        ];
    }

    /**
     * Test the normalisation of the negative marking.
     *
     * @dataProvider clean_negative_marking_provider
     * @param mixed $value the raw value.
     * @param float $expected the cleaned value.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('clean_negative_marking_provider')]
    public function test_clean_negative_marking($value, float $expected): void {
        $this->assertEqualsWithDelta($expected, qtype_mcq_chill::clean_negative_marking($value), 0.0000001);
    }

    public function test_clean_flag(): void {
        $this->assertSame(1, qtype_mcq_chill::clean_flag(null, 1));
        $this->assertSame(0, qtype_mcq_chill::clean_flag(null, 0));
        $this->assertSame(0, qtype_mcq_chill::clean_flag('0', 1));
        $this->assertSame(1, qtype_mcq_chill::clean_flag('1', 0));
        $this->assertSame(1, qtype_mcq_chill::clean_flag(true, 0));
        $this->assertSame(0, qtype_mcq_chill::clean_flag('false', 1));
        $this->assertSame(0, qtype_mcq_chill::clean_flag('no', 1));
        $this->assertSame(0, qtype_mcq_chill::clean_flag('off', 1));
        $this->assertSame(1, qtype_mcq_chill::clean_flag('yes', 0));
        $this->assertSame(1, qtype_mcq_chill::clean_flag('true', 0));
    }

    /**
     * Cases for test_question_saving.
     *
     * @return array[]
     */
    public static function question_saving_provider(): array {
        return [
            ['twooffour'],
            ['allornothing'],
            ['nopenalty'],
        ];
    }

    /**
     * Test saving a question through the editing form, then loading it back.
     *
     * @dataProvider question_saving_provider
     * @param string $which the test question.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('question_saving_provider')]
    public function test_question_saving(string $which): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $questiondata = test_question_maker::get_question_data('mcq_chill', $which);
        $formdata = test_question_maker::get_question_form_data('mcq_chill', $which);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);

        $formdata->category = "{$cat->id},{$cat->contextid}";
        qtype_mcq_chill_edit_form::mock_submit((array) $formdata);

        $form = qtype_mcq_chill_test_helper::get_question_editing_form($cat, $questiondata);
        $this->assertTrue($form->is_validated());

        $fromform = $form->get_data();
        $this->assertSame($formdata->negativemarking, $fromform->negativemarking);
        $returnedfromsave = $this->qtype->save_question($questiondata, $fromform);
        $actualquestionsdata = question_load_questions([$returnedfromsave->id]);
        $actualquestiondata = end($actualquestionsdata);

        foreach ($questiondata as $property => $value) {
            if (
                !in_array($property, ['id', 'timemodified', 'timecreated', 'options', 'hints', 'stamp',
                    'versionid', 'questionbankentryid', 'idnumber', 'category', 'contextid'])
            ) {
                $this->assertEquals($value, $actualquestiondata->$property, "Property {$property}");
            }
        }

        foreach ($questiondata->options as $optionname => $value) {
            if ($optionname != 'answers') {
                $this->assertEquals($value, $actualquestiondata->options->$optionname, "Option {$optionname}");
            }
        }

        $this->assertCount(count($questiondata->options->answers), $actualquestiondata->options->answers);
        foreach ($questiondata->options->answers as $answer) {
            $actualanswer = array_shift($actualquestiondata->options->answers);
            foreach ($answer as $ansproperty => $ansvalue) {
                if (!in_array($ansproperty, ['id', 'question'])) {
                    $this->assertEquals($ansvalue, $actualanswer->$ansproperty, "Answer property {$ansproperty}");
                }
            }
        }

        // The question can be instantiated and graded.
        $question = question_bank::load_question($returnedfromsave->id);
        $this->assertInstanceOf(\qtype_mcq_chill_question::class, $question);
        $this->assertEqualsWithDelta($questiondata->options->negativemarking, $question->negativemarking, 0.0000001);
        $this->assertEquals($questiondata->options->allornothing, $question->allornothing);
        $this->assertEquals(1, $question->shuffleanswers);
        $this->assertEquals('none', $question->answernumbering);
        $this->assertSame((int) get_config('qtype_multichoice', 'showstandardinstruction'), $question->showstandardinstruction);
        $this->assertEquals(get_string('correctfeedbackdefault', 'question'), $question->correctfeedback);
        $this->assertEquals(get_string('partiallycorrectfeedbackdefault', 'question'), $question->partiallycorrectfeedback);
        $this->assertEquals(get_string('incorrectfeedbackdefault', 'question'), $question->incorrectfeedback);
        $this->assertCount(4, $question->answers);
    }

    public function test_new_question_form_uses_the_saved_defaults(): void {
        global $PAGE;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('questiondefaultssave', 1);

        $fromform = test_question_maker::get_question_form_data('mcq_chill', 'allornothing');
        $fromform->negativemarking = '-1.0';
        $fromform->shuffleanswers = 0;
        $this->qtype->save_defaults_for_new_questions($fromform);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $questiondata = new \stdClass();
        $questiondata->qtype = 'mcq_chill';
        $questiondata->createdby = 0;
        $questiondata->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $form = qtype_mcq_chill_test_helper::get_question_editing_form($cat, $questiondata);
        $html = $form->render();

        $this->assertMatchesRegularExpression('~<option value="-1.0"\s+selected[^>]*>-100%</option>~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*type="checkbox"[^>]*name="allornothing"[^>]*checked~', $html);
        $this->assertDoesNotMatchRegularExpression('~<input[^>]*type="checkbox"[^>]*name="shuffleanswers"[^>]*checked~', $html);
    }

    public function test_form_keeps_a_non_standard_negative_marking(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id, 'negativemarking' => '-0.15']);

        // The stored value is offered and selected in the form of the existing question.
        $questiondata = question_bank::load_question_data($question->id);
        $form = $this->get_editing_form_of($cat, $questiondata);
        $form->set_data($questiondata);
        $html = $form->render();
        $this->assertMatchesRegularExpression('~<option value="-0.15"\s+selected[^>]*>-15%</option>~', $html);

        // Saving the form keeps it.
        $formdata = test_question_maker::get_question_form_data('mcq_chill', 'twooffour');
        $formdata->category = "{$cat->id},{$cat->contextid}";
        $formdata->negativemarking = '-0.15';
        qtype_mcq_chill_edit_form::mock_submit((array) $formdata);
        $form = $this->get_editing_form_of($cat, question_bank::load_question_data($question->id));
        $this->assertTrue($form->is_validated());
        $saved = $this->qtype->save_question($questiondata, $form->get_data());
        $this->assertEqualsWithDelta(-0.15, question_bank::load_question($saved->id)->negativemarking, 0.0000001);
    }

    public function test_data_preprocessing_for_the_form(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);

        // Prepare the question as the editing page does, then run the form pre-processing.
        $questiondata = question_bank::load_question_data($question->id);
        $form = qtype_mcq_chill_test_helper::get_question_editing_form($cat, $questiondata);
        $method = new \ReflectionMethod(qtype_mcq_chill_edit_form::class, 'data_preprocessing');
        $method->setAccessible(true);
        $prepared = $method->invoke($form, clone $questiondata);

        $this->assertSame('-0.5', $prepared->negativemarking);
        $this->assertEquals(0, $prepared->allornothing);
        $this->assertEquals(1, $prepared->shuffleanswers);
        $this->assertEquals(['One', 'Two', 'Three', 'Four'], $prepared->answer);
        $this->assertEquals([1, 0, 1, 0], $prepared->fraction);
    }

    public function test_get_question_options_without_options_record(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $created = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);

        $DB->delete_records('qtype_mcq_chill_options', ['questionid' => $created->id]);

        $question = $DB->get_record('question', ['id' => $created->id], '*', MUST_EXIST);
        $this->assertTrue($this->qtype->get_question_options($question));
        $this->assertDebuggingCalled('Question ID ' . $question->id . ' was missing an options record. Using default.');
        $this->assertEquals(0.0, $question->options->negativemarking);
        $this->assertEquals(0, $question->options->allornothing);
        $this->assertEquals(1, $question->options->shuffleanswers);
        $this->assertCount(4, $question->options->answers);
    }

    public function test_save_question_options_normalises_the_settings(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('mcq_chill', 'twooffour', [
            'category' => $cat->id,
            'negativemarking' => '-0.75',
            'allornothing' => 'yes',
            'shuffleanswers' => '0',
            'answer' => ['One', '  ', 'Three', 'Four', ''],
            'fraction' => ['1', '1', '1', '0', '0'],
        ]);

        $questiondata = question_bank::load_question_data($question->id);
        $this->assertEquals(-0.75, $questiondata->options->negativemarking);
        $this->assertEquals(1, $questiondata->options->allornothing);
        $this->assertEquals(0, $questiondata->options->shuffleanswers);

        // Blank choices are dropped even when flagged as correct.
        $answers = array_values($questiondata->options->answers);
        $this->assertCount(3, $answers);
        $this->assertEquals(['One', 'Three', 'Four'], array_column($answers, 'answer'));
        $this->assertEquals([1.0, 1.0, 0.0], array_column($answers, 'fraction'));
        $this->assertEquals([FORMAT_PLAIN, FORMAT_PLAIN, FORMAT_PLAIN], array_column($answers, 'answerformat'));
    }

    public function test_delete_question(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);

        $this->assertEquals(1, $DB->count_records('qtype_mcq_chill_options', ['questionid' => $question->id]));
        $this->assertEquals(4, $DB->count_records('question_answers', ['question' => $question->id]));

        question_delete_question($question->id);

        $this->assertEquals(0, $DB->count_records('qtype_mcq_chill_options', ['questionid' => $question->id]));
        $this->assertEquals(0, $DB->count_records('question_answers', ['question' => $question->id]));
        $this->assertFalse($DB->record_exists('question', ['id' => $question->id]));
    }

    public function test_save_defaults_for_new_questions(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $fromform = test_question_maker::get_question_form_data('mcq_chill', 'allornothing');
        $this->qtype->save_defaults_for_new_questions($fromform);

        $this->assertEquals('-0.25', $this->qtype->get_default_value('negativemarking', '0.0'));
        $this->assertEquals('1', $this->qtype->get_default_value('allornothing', '0'));
        $this->assertEquals('1', $this->qtype->get_default_value('shuffleanswers', '0'));
        $this->assertEquals('1', $this->qtype->get_default_value('defaultmark', '0'));
    }

    public function test_form_validation(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $questiondata = test_question_maker::get_question_data('mcq_chill', 'twooffour');
        $form = qtype_mcq_chill_test_helper::get_question_editing_form($cat, $questiondata);

        $data = (array) test_question_maker::get_question_form_data('mcq_chill', 'twooffour');
        $data['category'] = "{$cat->id},{$cat->contextid}";
        $data['idnumber'] = '';

        // Valid data.
        $this->assertEmpty($form->validation($data, []));

        // Not enough choices.
        $invalid = $data;
        $invalid['answer'] = ['One', '', '', ''];
        $invalid['fraction'] = [1, 0, 0, 0];
        $errors = $form->validation($invalid, []);
        $this->assertArrayHasKey('answergroup[1]', $errors);

        // No correct choice.
        $invalid = $data;
        $invalid['fraction'] = [0, 0, 0, 0];
        $errors = $form->validation($invalid, []);
        $this->assertEquals(get_string('errnocorrectanswer', 'qtype_mcq_chill'), $errors['answergroup[0]']);

        // A blank choice flagged as correct.
        $invalid = $data;
        $invalid['answer'] = ['One', '   ', 'Three', 'Four'];
        $invalid['fraction'] = [1, 1, 1, 0];
        $errors = $form->validation($invalid, []);
        $this->assertEquals(get_string('errcorrectblank', 'qtype_mcq_chill'), $errors['answergroup[1]']);
    }

    public function test_form_rendering(): void {
        global $PAGE;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('mcq_chill', 'allornothing', ['category' => $cat->id]);

        $questiondata = question_bank::load_question_data($question->id);
        $form = qtype_mcq_chill_test_helper::get_question_editing_form($cat, $questiondata);
        $form->set_data($questiondata);
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $html = $form->render();

        // One text field and one "correct answer" checkbox per choice, with the stored values.
        foreach (['One', 'Two', 'Three', 'Four'] as $key => $choice) {
            $this->assertMatchesRegularExpression(
                '~<input[^>]*name="answer\[' . $key . '\]"[^>]*value="' . $choice . '"~',
                $html
            );
            $this->assertMatchesRegularExpression('~<input[^>]*type="checkbox"[^>]*name="fraction\[' . $key . '\]"~', $html);
        }
        $this->assertMatchesRegularExpression('~<input[^>]*name="fraction\[0\]"[^>]*checked~', $html);
        $this->assertDoesNotMatchRegularExpression('~<input[^>]*name="fraction\[1\]"[^>]*checked~', $html);

        // The grading settings, with the stored values selected.
        $this->assertStringContainsString(get_string('gradingoptions', 'qtype_mcq_chill'), $html);
        $this->assertMatchesRegularExpression('~<option value="-0.25"\s+selected[^>]*>-25%</option>~', $html);
        $this->assertMatchesRegularExpression('~<option value="0.0"[^>]*>' . get_string('none') . '</option>~', $html);
        $this->assertMatchesRegularExpression('~<option value="-1.0"[^>]*>-100%</option>~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*type="checkbox"[^>]*name="allornothing"[^>]*checked~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*type="checkbox"[^>]*name="shuffleanswers"[^>]*checked~', $html);

        // Nothing from the full multiple choice form leaks in.
        $this->assertStringNotContainsString('name="single"', $html);
        $this->assertStringNotContainsString('name="answernumbering"', $html);
        $this->assertStringNotContainsString('name="correctfeedback', $html);
        $this->assertStringNotContainsString('name="hint[0]', $html);
    }

    public function test_negative_marking_key(): void {
        $this->assertSame('0.0', qtype_mcq_chill_edit_form::negative_marking_key(0.0));
        $this->assertSame('-1.0', qtype_mcq_chill_edit_form::negative_marking_key(-1.0));
        $this->assertSame('-0.3333333', qtype_mcq_chill_edit_form::negative_marking_key(-0.3333333));
        $this->assertSame('-0.5', qtype_mcq_chill_edit_form::negative_marking_key(-0.5));
        $this->assertSame('-0.15', qtype_mcq_chill_edit_form::negative_marking_key(-0.15));
        $this->assertSame('-0.1234567', qtype_mcq_chill_edit_form::negative_marking_key(-0.1234567));
    }

    public function test_get_negative_marking_options(): void {
        $options = qtype_mcq_chill_edit_form::get_negative_marking_options();
        $this->assertEquals(get_string('none'), reset($options));
        $this->assertEquals('0.0', key($options));
        $this->assertEquals('-100%', end($options));
        $this->assertEquals('-1.0', key($options));
        foreach (array_keys($options) as $key) {
            $this->assertLessThanOrEqual(0, (float) $key);
        }

        // A stored value outside the standard list is added, at the right place.
        $options = qtype_mcq_chill_edit_form::get_negative_marking_options(-0.15);
        $this->assertArrayHasKey('-0.15', $options);
        $this->assertEquals('-15%', $options['-0.15']);
        $keys = array_keys($options);
        $this->assertLessThan(array_search('-0.15', $keys), array_search('-0.1', $keys));
        $this->assertGreaterThan(array_search('-0.15', $keys), array_search('-0.2', $keys));

        // A standard value is not duplicated.
        $this->assertCount(
            count(qtype_mcq_chill_edit_form::get_negative_marking_options()),
            qtype_mcq_chill_edit_form::get_negative_marking_options(-0.5)
        );
    }

    public function test_xml_import(): void {
        $xml = '  <question type="mcq_chill">
    <name>
      <text>QCM Chill two of four</text>
    </name>
    <questiontext format="html">
      <text>Which are the odd numbers?</text>
    </questiontext>
    <generalfeedback format="html">
      <text>The odd numbers are One and Three.</text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <negativemarking>-0.5</negativemarking>
    <allornothing>0</allornothing>
    <shuffleanswers>1</shuffleanswers>
    <answer fraction="100" format="html">
      <text>One</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
    <answer fraction="0" format="html">
      <text>Two</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
    <answer fraction="100" format="html">
      <text>Three</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
    <answer fraction="0" format="html">
      <text>Four</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
  </question>';
        $xmldata = $this->parse_xml($xml);

        $importer = new \qformat_xml();
        $q = $importer->try_importing_using_qtypes($xmldata['question'], null, null, 'mcq_chill');

        $this->assertEquals('mcq_chill', $q->qtype);
        $this->assertEquals('QCM Chill two of four', $q->name);
        $this->assertEquals('Which are the odd numbers?', $q->questiontext);
        $this->assertEquals(FORMAT_HTML, $q->questiontextformat);
        $this->assertEquals(1, $q->defaultmark);
        $this->assertEquals('-0.5', $q->negativemarking);
        $this->assertEquals('0', $q->allornothing);
        $this->assertEquals('1', $q->shuffleanswers);
        $this->assertEquals(['One', 'Two', 'Three', 'Four'], $q->answer);
        $this->assertEquals([1, 0, 1, 0], $q->fraction);
    }

    public function test_xml_import_without_settings(): void {
        $xml = '<question type="mcq_chill">
    <name><text>Bare</text></name>
    <questiontext format="html"><text>Pick the vowels.</text></questiontext>
    <answer fraction="100"><text>A</text></answer>
    <answer fraction="0"><text>B</text></answer>
  </question>';
        $importer = new \qformat_xml();
        $q = $importer->try_importing_using_qtypes($this->parse_xml($xml)['question'], null, null, 'mcq_chill');

        // Missing settings keep their default values once saved.
        $this->assertNull($q->negativemarking);
        $this->assertNull($q->allornothing);
        $this->assertNull($q->shuffleanswers);
        $this->assertSame(['A', 'B'], $q->answer);
    }

    /**
     * Cases for test_xml_import_of_an_unusable_question.
     *
     * @return array[]
     */
    public static function unusable_xml_provider(): array {
        return [
            'no choice at all' => ['', 'notenoughchoices'],
            'one choice' => ['<answer fraction="100"><text>A</text></answer>', 'notenoughchoices'],
            'blank choices' => ['<answer fraction="100"><text>A</text></answer><answer fraction="0"><text> </text></answer>',
                'notenoughchoices'],
            'no correct choice' => ['<answer fraction="0"><text>A</text></answer><answer fraction="0"><text>B</text></answer>',
                'errnocorrectanswer'],
        ];
    }

    /**
     * An unusable question is reported to the importer and skipped.
     *
     * @dataProvider unusable_xml_provider
     * @param string $answersxml the answer elements.
     * @param string $expectederror the expected error string identifier.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusable_xml_provider')]
    public function test_xml_import_of_an_unusable_question(string $answersxml, string $expectederror): void {
        $xml = '<question type="mcq_chill">
    <name><text>Unusable</text></name>
    <questiontext format="html"><text>Pick something.</text></questiontext>
    ' . $answersxml . '
  </question>';
        $importer = new \qformat_xml();
        $this->expectOutputRegex('~' . preg_quote(get_string($expectederror, 'qtype_mcq_chill', 2), '~') . '~');
        $q = $importer->try_importing_using_qtypes($this->parse_xml($xml)['question'], null, null, 'mcq_chill');
        $this->assertFalse($q);
        // The importer may add its own generic error once every question type has declined.
        $this->assertGreaterThanOrEqual(1, $importer->importerrors);
    }

    public function test_xml_import_then_save(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $xml = '<question type="mcq_chill">
    <name><text>Imported</text></name>
    <questiontext format="html"><text>Pick the vowels.</text></questiontext>
    <generalfeedback format="html"><text></text></generalfeedback>
    <defaultgrade>2</defaultgrade>
    <penalty>0</penalty>
    <hidden>0</hidden>
    <negativemarking>-0.3333333</negativemarking>
    <allornothing>1</allornothing>
    <shuffleanswers>0</shuffleanswers>
    <answer fraction="100" format="html"><text>A</text></answer>
    <answer fraction="0" format="plain_text"><text>a &lt; b</text></answer>
    <answer fraction="100" format="html"><text><![CDATA[<b>E</b>]]></text></answer>
  </question>';
        $xmldata = $this->parse_xml($xml);
        $importer = new \qformat_xml();
        $fromform = $importer->try_importing_using_qtypes($xmldata['question'], null, null, 'mcq_chill');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $fromform->category = "{$cat->id},{$cat->contextid}";
        $fromform->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

        $question = new \stdClass();
        $question->qtype = 'mcq_chill';
        $question->createdby = 0;
        $question->idnumber = null;
        $question->status = $fromform->status;
        $saved = $this->qtype->save_question($question, $fromform);

        $loaded = question_bank::load_question($saved->id);
        $this->assertEqualsWithDelta(-0.3333333, $loaded->negativemarking, 0.0000001);
        $this->assertEquals(1, $loaded->allornothing);
        $this->assertEquals(0, $loaded->shuffleanswers);
        // Choices are plain text: the HTML choice is reduced to its text.
        $this->assertEquals(['A', 'a < b', 'E'], array_column($loaded->answers, 'answer'));
        $this->assertEquals([FORMAT_PLAIN, FORMAT_PLAIN, FORMAT_PLAIN], array_column($loaded->answers, 'answerformat'));
        $this->assertEquals([1.0, 0.0, 1.0], array_column($loaded->answers, 'fraction'));
    }

    public function test_save_question_options_notices(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $created = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);

        $question = new \stdClass();
        $question->id = $created->id;
        $question->context = \context::instance_by_id($cat->contextid);
        $question->negativemarking = '-0.5';
        $question->answer = ['One', 'Two'];
        $question->fraction = [1, 0];

        // Valid data: no notice.
        $result = $this->qtype->save_question_options($question);
        $this->assertFalse(property_exists($result, 'notice'));

        // Not enough choices.
        $question->answer = ['One', '   '];
        $result = $this->qtype->save_question_options($question);
        $this->assertEquals(get_string('notenoughchoices', 'qtype_mcq_chill', 2), $result->notice);

        // No correct choice.
        $question->answer = ['One', 'Two'];
        $question->fraction = [0, 0];
        $result = $this->qtype->save_question_options($question);
        $this->assertEquals(get_string('errnocorrectanswer', 'qtype_mcq_chill'), $result->notice);

        // Negative marking outside the allowed range: bounded, reported to developers only.
        $question->fraction = [1, 0];
        $question->negativemarking = '-50';
        $result = $this->qtype->save_question_options($question);
        $this->assertDebuggingCalled(get_string('negativemarkingoutofrange', 'qtype_mcq_chill', '-50'));
        $this->assertFalse(property_exists($result, 'notice'));
        $this->assertEquals(-1.0, question_bank::load_question_data($created->id)->options->negativemarking);
    }

    public function test_xml_export(): void {
        $qdata = test_question_maker::get_question_data('mcq_chill', 'twooffour');
        $qdata->id = 123;
        $qdata->contextid = \context_system::instance()->id;

        $exporter = new \qformat_xml();
        $xml = $exporter->writequestion($qdata);

        $expectedxml = '<!-- question: 123  -->
  <question type="mcq_chill">
    <name>
      <text>QCM Chill two of four</text>
    </name>
    <questiontext format="html">
      <text>Which are the odd numbers?</text>
    </questiontext>
    <generalfeedback format="html">
      <text>The odd numbers are One and Three.</text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <idnumber></idnumber>
    <negativemarking>-0.5</negativemarking>
    <allornothing>0</allornothing>
    <shuffleanswers>1</shuffleanswers>
    <answer fraction="100" format="plain_text">
      <text>One</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
    <answer fraction="0" format="plain_text">
      <text>Two</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
    <answer fraction="100" format="plain_text">
      <text>Three</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
    <answer fraction="0" format="plain_text">
      <text>Four</text>
      <feedback format="html">
        <text></text>
      </feedback>
    </answer>
  </question>
';

        $this->assert_same_xml($expectedxml, $xml);
    }

    /**
     * Parse a Moodle XML fragment into the array structure used by the XML question format.
     *
     * @param string $xml the XML.
     * @return array the parsed structure.
     */
    protected function parse_xml(string $xml): array {
        global $CFG;
        if (class_exists(\core\xml_parser::class)) {
            // Moodle 5.1 and later.
            return (new \core\xml_parser())->parse($xml);
        }
        require_once($CFG->libdir . '/xmlize.php');
        return xmlize($xml);
    }

    /**
     * Assert that two XML strings are the same, ignoring whitespace differences.
     *
     * @param string $expectedxml the expected XML.
     * @param string $xml the actual XML.
     */
    protected function assert_same_xml(string $expectedxml, string $xml): void {
        $this->assertEquals(str_replace("\r\n", "\n", $expectedxml), str_replace("\r\n", "\n", $xml));
    }
}
