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

namespace qtype_ddto_chill;

use qtype_ddto_chill_question;
use question_attempt_step;
use question_classified_response;
use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/ddto_chill/question.php');

/**
 * Unit tests for the Drag-drop into text Chill question definition class.
 *
 * Gaps: p1 expects "cat" (choice 1), p2 expects "mat" (choice 2).
 * Distractors: dog (3), hat (4).
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_ddto_chill_question
 */
final class question_test extends \advanced_testcase {
    /**
     * Make a test question and start an attempt at it, without shuffling.
     *
     * @param string $which the name of the test question.
     * @return qtype_ddto_chill_question the question.
     */
    protected function make_started_question(string $which): qtype_ddto_chill_question {
        $question = test_question_maker::make_question('ddto_chill', $which);
        $question->shuffleanswers = 0;
        $question->start_attempt(new question_attempt_step(), 1);
        return $question;
    }

    /**
     * Build a response placing the given choice numbers in the two gaps.
     *
     * @param int $p1 choice number in gap 1, 0 if empty.
     * @param int $p2 choice number in gap 2, 0 if empty.
     * @return array the response.
     */
    protected function response(int $p1, int $p2): array {
        $response = [];
        if ($p1) {
            $response['p1'] = $p1;
        }
        if ($p2) {
            $response['p2'] = $p2;
        }
        return $response;
    }

    /**
     * Cases for test_grade_response.
     *
     * @return array[]
     */
    public static function grade_response_provider(): array {
        return [
            'partial: both correct' => ['catmat', 1, 2, 1.0, question_state::$gradedright],
            'partial: first correct' => ['catmat', 1, 0, 0.5, question_state::$gradedpartial],
            'partial: both correct as text via swapped identical would not apply' => ['catmat', 1, 2, 1.0, question_state::$gradedright],
            'partial: one correct one wrong' => ['catmat', 1, 3, 0.0, question_state::$gradedwrong],
            'partial: both wrong' => ['catmat', 3, 4, -1.0, question_state::$gradedwrong],
            'partial: one wrong' => ['catmat', 3, 0, -0.5, question_state::$gradedwrong],
            'partial: nothing' => ['catmat', 0, 0, 0.0, question_state::$gradedwrong],
            'all or nothing: both correct' => ['allornothing', 1, 2, 1.0, question_state::$gradedright],
            'all or nothing: one correct' => ['allornothing', 1, 0, 0.0, question_state::$gradedwrong],
            'all or nothing: one wrong' => ['allornothing', 1, 3, -0.25, question_state::$gradedwrong],
            'all or nothing: both wrong' => ['allornothing', 3, 4, -0.5, question_state::$gradedwrong],
            'no penalty: both correct' => ['nopenalty', 1, 2, 1.0, question_state::$gradedright],
            'no penalty: one correct one wrong' => ['nopenalty', 1, 3, 0.5, question_state::$gradedpartial],
            'no penalty: both wrong' => ['nopenalty', 3, 4, 0.0, question_state::$gradedwrong],
        ];
    }

    /**
     * Test the grading of responses.
     *
     * @dataProvider grade_response_provider
     * @param string $which the test question.
     * @param int $p1 choice in gap 1.
     * @param int $p2 choice in gap 2.
     * @param float $expectedfraction the expected fraction.
     * @param question_state $expectedstate the expected state.
     */
    public function test_grade_response(
        string $which,
        int $p1,
        int $p2,
        float $expectedfraction,
        question_state $expectedstate
    ): void {
        $question = $this->make_started_question($which);
        [$fraction, $state] = $question->grade_response($this->response($p1, $p2));
        $this->assertEqualsWithDelta($expectedfraction, $fraction, 0.0000001);
        $this->assertEquals($expectedstate, $state);
    }

    /**
     * Cases for test_compute_fraction.
     *
     * @return array[]
     */
    public static function compute_fraction_provider(): array {
        return [
            'two gaps, one correct' => [1, 0, 2, -0.5, false, 0.5],
            'floor at -100%' => [0, 3, 2, -0.5, false, -1.0],
            'ceiling at 100%' => [2, 0, 2, -0.5, false, 1.0],
            'all-or-nothing fully correct' => [2, 0, 2, -1.0, true, 1.0],
            'all-or-nothing missing gap' => [1, 0, 2, -1.0, true, 0.0],
            'all-or-nothing with one wrong at -100%' => [2, 1, 2, -1.0, true, -1.0],
            'no gaps at all' => [0, 0, 0, -0.5, false, 0.0],
        ];
    }

    /**
     * Test the grading formula itself.
     *
     * @dataProvider compute_fraction_provider
     * @param int $numcorrectselected correctly filled gaps.
     * @param int $numwrongselected incorrectly filled gaps.
     * @param int $numcorrect number of gaps.
     * @param float $negativemarking the negative marking.
     * @param bool $allornothing the all-or-nothing mode.
     * @param float $expected the expected fraction.
     */
    public function test_compute_fraction(
        int $numcorrectselected,
        int $numwrongselected,
        int $numcorrect,
        float $negativemarking,
        bool $allornothing,
        float $expected
    ): void {
        $this->assertEqualsWithDelta($expected, qtype_ddto_chill_question::compute_fraction(
            $numcorrectselected,
            $numwrongselected,
            $numcorrect,
            $negativemarking,
            $allornothing
        ), 0.0000001);
    }

    public function test_get_min_fraction(): void {
        $this->assertEqualsWithDelta(-1.0, $this->make_started_question('catmat')->get_min_fraction(), 0.0000001);
        $this->assertEqualsWithDelta(-0.5, $this->make_started_question('allornothing')->get_min_fraction(), 0.0000001);
        $this->assertEqualsWithDelta(0.0, $this->make_started_question('nopenalty')->get_min_fraction(), 0.0000001);
    }

    public function test_get_expected_data(): void {
        $question = $this->make_started_question('catmat');
        $this->assertEquals(['p1' => PARAM_INT, 'p2' => PARAM_INT], $question->get_expected_data());
    }

    public function test_is_complete_response(): void {
        $question = $this->make_started_question('catmat');
        $this->assertFalse($question->is_complete_response([]));
        $this->assertFalse($question->is_complete_response($this->response(1, 0)));
        $this->assertTrue($question->is_complete_response($this->response(1, 2)));
        $this->assertTrue($question->is_gradable_response($this->response(3, 0)));
        $this->assertFalse($question->is_gradable_response([]));
    }

    public function test_get_correct_response(): void {
        $question = $this->make_started_question('catmat');
        $this->assertEquals(['p1' => 1, 'p2' => 2], $question->get_correct_response());
        [$fraction] = $question->grade_response($question->get_correct_response());
        $this->assertEqualsWithDelta(1.0, $fraction, 0.0000001);
    }

    public function test_summarise_response(): void {
        $question = $this->make_started_question('catmat');
        $this->assertEquals('cat; mat', $question->summarise_response($this->response(1, 2)));
        $this->assertEquals('dog; –', $question->summarise_response($this->response(3, 0)));
    }

    public function test_identical_word_gaps_are_interchangeable(): void {
        $question = $this->make_started_question('catmat');
        $question->choices[2]->text = 'cat';
        $question->places[2] = 2;
        [$fraction] = $question->grade_response(['p1' => 2, 'p2' => 1]);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.0000001);
    }

    public function test_classify_response(): void {
        $question = $this->make_started_question('catmat');
        $classified = $question->classify_response($this->response(1, 3));
        $this->assertEquals(new question_classified_response(1, 'cat', 0.5), $classified[1]);
        $this->assertEquals(new question_classified_response(3, 'dog', -0.5), $classified[2]);
    }

    public function test_clear_wrong_from_response(): void {
        $question = $this->make_started_question('catmat');
        $this->assertEquals(
            ['p1' => 1, 'p2' => 0],
            $question->clear_wrong_from_response(['p1' => 1, 'p2' => 3])
        );
    }

    public function test_shuffling_keeps_the_grading_right(): void {
        $question = test_question_maker::make_question('ddto_chill', 'catmat');
        $question->shuffleanswers = 1;
        $step = new question_attempt_step();
        $question->start_attempt($step, 1);

        $order = explode(',', $step->get_qt_var('_choiceorder'));
        $this->assertEqualsCanonicalizing(['1', '2', '3', '4'], $order);

        [$fraction, $state] = $question->grade_response(['p1' => 1, 'p2' => 2]);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.0000001);
        $this->assertEquals(question_state::$gradedright, $state);
    }
}
