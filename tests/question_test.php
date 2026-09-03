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

use qtype_mcq_chill_question;
use question_attempt_step;
use question_classified_response;
use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/mcq_chill/question.php');

/**
 * Unit tests for the QCM Chill question definition class.
 *
 * The test questions have four choices, in this order once the attempt has
 * started without shuffling: One (correct), Two (wrong), Three (correct)
 * and Four (wrong). A response is given as the list of the selected choice
 * indexes.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_mcq_chill_question
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_mcq_chill_question::class)]
final class question_test extends \advanced_testcase {
    /**
     * Make a test question and start an attempt at it, without shuffling the choices.
     *
     * @param string $which the name of the test question.
     * @return qtype_mcq_chill_question the question.
     */
    protected function make_started_question(string $which): qtype_mcq_chill_question {
        $question = test_question_maker::make_question('mcq_chill', $which);
        $question->shuffleanswers = 0;
        $question->start_attempt(new question_attempt_step(), 1);
        return $question;
    }

    /**
     * Build a response selecting the given choices.
     *
     * @param int[] $selected the indexes of the selected choices.
     * @return array the response.
     */
    protected function response(array $selected): array {
        $response = [];
        foreach ($selected as $index) {
            $response['choice' . $index] = '1';
        }
        return $response;
    }

    /**
     * Cases for test_grade_response.
     *
     * @return array[] test question, selected choices, expected fraction, expected state.
     */
    public static function grade_response_provider(): array {
        return [
            // Partial credit, -50% for each wrong choice selected.
            'partial: both correct' => ['twooffour', [0, 2], 1.0, question_state::$gradedright],
            'partial: one correct' => ['twooffour', [0], 0.5, question_state::$gradedpartial],
            'partial: both correct and one wrong' => ['twooffour', [0, 2, 1], 0.5, question_state::$gradedpartial],
            'partial: one correct and one wrong' => ['twooffour', [2, 3], 0.0, question_state::$gradedwrong],
            'partial: everything selected' => ['twooffour', [0, 1, 2, 3], 0.0, question_state::$gradedwrong],
            'partial: one wrong' => ['twooffour', [1], -0.5, question_state::$gradedwrong],
            'partial: both wrong' => ['twooffour', [1, 3], -1.0, question_state::$gradedwrong],
            'partial: one correct and both wrong' => ['twooffour', [0, 1, 3], -0.5, question_state::$gradedwrong],
            'partial: nothing selected' => ['twooffour', [], 0.0, question_state::$gradedwrong],
            // All or nothing, -25% for each wrong choice selected.
            'all or nothing: both correct' => ['allornothing', [0, 2], 1.0, question_state::$gradedright],
            'all or nothing: one correct' => ['allornothing', [0], 0.0, question_state::$gradedwrong],
            'all or nothing: both correct and one wrong' => ['allornothing', [0, 2, 1], -0.25, question_state::$gradedwrong],
            'all or nothing: both wrong' => ['allornothing', [1, 3], -0.5, question_state::$gradedwrong],
            'all or nothing: everything selected' => ['allornothing', [0, 1, 2, 3], -0.5, question_state::$gradedwrong],
            // Partial credit, no negative marking.
            'no penalty: both correct' => ['nopenalty', [0, 2], 1.0, question_state::$gradedright],
            'no penalty: one correct and both wrong' => ['nopenalty', [0, 1, 3], 0.5, question_state::$gradedpartial],
            'no penalty: both wrong' => ['nopenalty', [1, 3], 0.0, question_state::$gradedwrong],
            'no penalty: everything selected' => ['nopenalty', [0, 1, 2, 3], 1.0, question_state::$gradedright],
        ];
    }

    /**
     * Test the grading of responses.
     *
     * @dataProvider grade_response_provider
     * @param string $which the test question.
     * @param int[] $selected the selected choices.
     * @param float $expectedfraction the expected fraction.
     * @param question_state $expectedstate the expected state.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('grade_response_provider')]
    public function test_grade_response(
        string $which,
        array $selected,
        float $expectedfraction,
        question_state $expectedstate
    ): void {
        $question = $this->make_started_question($which);
        [$fraction, $state] = $question->grade_response($this->response($selected));
        $this->assertEqualsWithDelta($expectedfraction, $fraction, 0.0000001);
        $this->assertEquals($expectedstate, $state);
    }

    /**
     * Cases for test_compute_fraction.
     *
     * @return array[] correct selected, wrong selected, number correct, negative marking, all or nothing, expected.
     */
    public static function compute_fraction_provider(): array {
        return [
            'three correct, one selected' => [1, 0, 3, -0.5, false, 1 / 3],
            'floor at -100%' => [0, 3, 2, -0.5, false, -1.0],
            'ceiling at 100%' => [2, 0, 2, -0.5, false, 1.0],
            'penalty larger than 100% is capped' => [0, 1, 2, -1.5, false, -1.0],
            'positive negative marking is ignored' => [1, 1, 2, 0.5, false, 0.0],
            'no correct choice at all' => [0, 0, 0, -0.5, false, 0.0],
            'no correct choice in all-or-nothing mode' => [0, 0, 0, -0.5, true, 0.0],
            'all-or-nothing with missing correct choice' => [1, 0, 2, -1.0, true, 0.0],
            'all-or-nothing fully correct' => [2, 0, 2, -1.0, true, 1.0],
            'all-or-nothing with one wrong at -100%' => [2, 1, 2, -1.0, true, -1.0],
        ];
    }

    /**
     * Test the grading formula itself.
     *
     * @dataProvider compute_fraction_provider
     * @param int $numcorrectselected number of correct choices selected.
     * @param int $numwrongselected number of wrong choices selected.
     * @param int $numcorrect number of correct choices.
     * @param float $negativemarking the negative marking.
     * @param bool $allornothing the all-or-nothing mode.
     * @param float $expected the expected fraction.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('compute_fraction_provider')]
    public function test_compute_fraction(
        int $numcorrectselected,
        int $numwrongselected,
        int $numcorrect,
        float $negativemarking,
        bool $allornothing,
        float $expected
    ): void {
        $this->assertEqualsWithDelta($expected, qtype_mcq_chill_question::compute_fraction(
            $numcorrectselected,
            $numwrongselected,
            $numcorrect,
            $negativemarking,
            $allornothing
        ), 0.0000001);
    }

    public function test_get_min_fraction(): void {
        $this->assertEqualsWithDelta(-1.0, $this->make_started_question('twooffour')->get_min_fraction(), 0.0000001);
        $this->assertEqualsWithDelta(-0.5, $this->make_started_question('allornothing')->get_min_fraction(), 0.0000001);
        $this->assertEqualsWithDelta(0.0, $this->make_started_question('nopenalty')->get_min_fraction(), 0.0000001);
    }

    public function test_get_max_fraction(): void {
        $this->assertEquals(1, $this->make_started_question('twooffour')->get_max_fraction());
    }

    public function test_get_expected_data(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals([
            'choice0' => PARAM_BOOL,
            'choice1' => PARAM_BOOL,
            'choice2' => PARAM_BOOL,
            'choice3' => PARAM_BOOL,
        ], $question->get_expected_data());
    }

    public function test_is_complete_response(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertFalse($question->is_complete_response([]));
        $this->assertFalse($question->is_complete_response(['choice0' => '0', 'choice1' => '0']));
        $this->assertTrue($question->is_complete_response(['choice1' => '1']));
        $this->assertTrue($question->is_gradable_response(['choice1' => '1']));
    }

    public function test_get_validation_error(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals(
            get_string('pleaseselectatleastoneanswer', 'qtype_multichoice'),
            $question->get_validation_error([])
        );
        $this->assertEquals('', $question->get_validation_error($this->response([1])));
    }

    public function test_get_correct_response(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals(['choice0' => 1, 'choice2' => 1], $question->get_correct_response());
        [$fraction] = $question->grade_response($question->get_correct_response());
        $this->assertEqualsWithDelta(1.0, $fraction, 0.0000001);
    }

    public function test_count_selected_choices(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals([0, 0], $question->count_selected_choices([]));
        $this->assertEquals([2, 0], $question->count_selected_choices($this->response([0, 2])));
        $this->assertEquals([1, 2], $question->count_selected_choices($this->response([2, 1, 3])));
        $this->assertEquals([0, 0], $question->count_selected_choices(['choice0' => '0', 'choice1' => 0]));
    }

    public function test_get_num_parts_right(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals([1, 4], $question->get_num_parts_right($this->response([0, 1])));
        $this->assertEquals([2, 4], $question->get_num_parts_right($this->response([0, 2])));
    }

    public function test_summarise_response(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertNull($question->summarise_response([]));
        $this->assertEquals('One; Three', $question->summarise_response($this->response([0, 2])));
        $this->assertEquals('Two; Four', $question->summarise_response($this->response([1, 3])));
    }

    public function test_un_summarise_response(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals(['choice0' => '1', 'choice2' => '1'], $question->un_summarise_response('One; Three'));
    }

    public function test_classify_response(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals([
            13 => new question_classified_response(13, 'One', 1),
            14 => new question_classified_response(14, 'Two', 0),
        ], $question->classify_response($this->response([0, 1])));
        $this->assertEquals([], $question->classify_response([]));
    }

    public function test_clear_wrong_from_response(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals(
            ['choice0' => '1', 'choice1' => 0, 'choice3' => 0],
            $question->clear_wrong_from_response($this->response([0, 1, 3]))
        );
    }

    public function test_get_question_summary(): void {
        $question = $this->make_started_question('twooffour');
        $this->assertEquals('Which are the odd numbers?: One; Two; Three; Four', $question->get_question_summary());
    }

    public function test_shuffling_keeps_the_grading_right(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 1;
        $step = new question_attempt_step();
        $question->start_attempt($step, 1);

        $order = explode(',', $step->get_qt_var('_order'));
        $this->assertEqualsCanonicalizing([13, 14, 15, 16], $order);

        // Select the two correct choices wherever they ended up.
        $response = [];
        foreach ($order as $index => $ansid) {
            if (in_array($ansid, [13, 15])) {
                $response['choice' . $index] = '1';
            }
        }
        [$fraction, $state] = $question->grade_response($response);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.0000001);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    public function test_grading_with_a_deleted_choice(): void {
        // A choice removed after the attempt started counts as a wrong choice when selected.
        $question = $this->make_started_question('twooffour');
        $step = new question_attempt_step();
        $question->shuffleanswers = 0;
        $question->start_attempt($step, 1);
        unset($question->answers[14]);
        $question->apply_attempt_state($step);

        $this->assertArrayHasKey(14, $question->answers);
        [$fraction] = $question->grade_response($this->response([0, 1, 2]));
        $this->assertEqualsWithDelta(0.5, $fraction, 0.0000001);
    }
}
