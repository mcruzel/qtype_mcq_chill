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

use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Walkthrough tests of the QCM Chill question type with various question behaviours.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_mcq_chill_question
 * @covers     \qtype_mcq_chill_renderer
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_mcq_chill_question::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_mcq_chill_renderer::class)]
final class walkthrough_test extends \qbehaviour_walkthrough_test_base {
    public function test_deferredfeedback_fully_correct(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'deferredfeedback', 2);

        // Check the initial state and the rendering.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_question_text_expectation($question),
            $this->get_contains_mc_checkbox_expectation('choice0', true, false),
            $this->get_contains_mc_checkbox_expectation('choice1', true, false),
            $this->get_contains_mc_checkbox_expectation('choice2', true, false),
            $this->get_contains_mc_checkbox_expectation('choice3', true, false),
            $this->get_does_not_contain_feedback_expectation()
        );

        // Select the two correct choices and finish.
        $this->process_submission(['choice0' => '1', 'choice2' => '1']);
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);

        $this->quba->finish_all_questions();

        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(2);
        $this->check_current_output(
            $this->get_contains_mc_checkbox_expectation('choice0', false, true),
            $this->get_contains_mc_checkbox_expectation('choice1', false, false),
            $this->get_contains_mc_checkbox_expectation('choice2', false, true),
            $this->get_contains_mc_checkbox_expectation('choice3', false, false),
            $this->get_contains_correct_expectation(),
            $this->get_contains_general_feedback_expectation($question)
        );
        $this->assertEquals('One; Three', $this->quba->get_response_summary($this->slot));
    }

    public function test_deferredfeedback_partially_correct(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'deferredfeedback', 2);
        $this->process_submission(['choice0' => '1']);
        $this->quba->finish_all_questions();

        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(1);
        $this->check_current_output($this->get_contains_partcorrect_expectation());
    }

    public function test_deferredfeedback_negative_mark(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'deferredfeedback', 2);
        $this->process_submission(['choice1' => '1', 'choice3' => '1']);
        $this->quba->finish_all_questions();

        // Two wrong choices at -50% each: minus the whole mark of the question.
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(-2);
        $this->check_current_output($this->get_contains_incorrect_expectation());
        $this->assertEquals(-1, $this->quba->get_question_attempt($this->slot)->get_min_fraction());
    }

    public function test_deferredfeedback_no_response(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'deferredfeedback', 2);
        $this->quba->finish_all_questions();

        $this->check_current_state(question_state::$gaveup);
        $this->check_current_mark(null);
    }

    public function test_deferredfeedback_all_or_nothing(): void {
        $question = test_question_maker::make_question('mcq_chill', 'allornothing');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'deferredfeedback', 4);
        $this->process_submission(['choice0' => '1', 'choice1' => '1', 'choice2' => '1']);
        $this->quba->finish_all_questions();

        // Everything right but one wrong choice at -25%.
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(-1);
    }

    public function test_immediatefeedback(): void {
        $question = test_question_maker::make_question('mcq_chill', 'nopenalty');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'immediatefeedback', 1);

        // Submitting nothing is invalid.
        $this->process_submission(['-submit' => 1]);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_validation_error_expectation(),
            $this->get_does_not_contain_feedback_expectation()
        );

        // Submit one correct and one wrong choice: 0.5, no penalty.
        $this->process_submission(['choice0' => '1', 'choice1' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(0.5);
        $this->check_current_output(
            $this->get_contains_mc_checkbox_expectation('choice0', false, true),
            $this->get_contains_mc_checkbox_expectation('choice1', false, true),
            $this->get_contains_partcorrect_expectation()
        );
    }

    public function test_interactive_behaviour(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;
        $question->penalty = 0;

        $this->start_attempt_at_question($question, 'interactive', 3);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output($this->get_contains_submit_button_expectation(true));

        // With no hint there is a single try.
        $this->process_submission(['choice0' => '1', 'choice2' => '1', 'choice3' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(1.5);
    }

    public function test_interactive_never_goes_below_zero(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;
        $question->penalty = 0;

        $this->start_attempt_at_question($question, 'interactive', 2);
        $this->process_submission(['choice1' => '1', 'choice3' => '1', '-submit' => 1]);

        // The core interactive behaviour floors the fraction at zero.
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_current_output($this->get_contains_incorrect_expectation());
    }

    public function test_adaptive_never_goes_below_zero(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;
        $question->penalty = 0;

        $this->start_attempt_at_question($question, 'adaptive', 2);
        $this->process_submission(['choice1' => '1', 'choice3' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);

        // A later, better submission keeps the best fraction so far.
        $this->process_submission(['choice0' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(1);
        $this->quba->finish_all_questions();
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(1);
    }

    public function test_interactive_with_a_hint_clears_the_wrong_choices(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;
        $question->penalty = 0;
        $question->hints = [
            new \question_hint_with_parts(1, 'Think odd.', FORMAT_HTML, true, true),
        ];

        $this->start_attempt_at_question($question, 'interactive', 2);
        $this->check_current_output($this->get_tries_remaining_expectation(2));

        // One correct and one wrong choice: a try is left, the wrong choice is cleared on retry.
        $this->process_submission(['choice0' => '1', 'choice1' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        // The page carries the cleaned response as hidden fields, posted with the "Try again" button.
        $prefix = $this->quba->get_field_prefix($this->slot);
        $this->check_current_output(
            $this->get_contains_try_again_button_expectation(true),
            $this->get_contains_hint_expectation('Think odd.'),
            $this->get_contains_num_parts_correct(1),
            new \question_contains_tag_with_attributes(
                'input',
                ['type' => 'hidden', 'name' => $prefix . 'choice0', 'value' => '1']
            ),
            new \question_contains_tag_with_attributes(
                'input',
                ['type' => 'hidden', 'name' => $prefix . 'choice1', 'value' => '0']
            )
        );

        $this->process_submission(['choice0' => '1', 'choice1' => '0', '-tryagain' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
            $this->get_contains_mc_checkbox_expectation('choice0', true, true),
            $this->get_contains_mc_checkbox_expectation('choice1', true, false),
            $this->get_tries_remaining_expectation(1)
        );

        $this->process_submission(['choice0' => '1', 'choice2' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(2);
    }

    public function test_regrade_with_a_new_version(): void {
        $question = test_question_maker::make_question('mcq_chill', 'twooffour');
        $question->shuffleanswers = 0;

        $this->start_attempt_at_question($question, 'deferredfeedback', 2);
        $this->process_submission(['choice0' => '1', 'choice1' => '1']);
        $this->quba->finish_all_questions();
        $this->check_current_mark(0);

        // A new version of the question without negative marking.
        $newversion = test_question_maker::make_question('mcq_chill', 'nopenalty');
        $newversion->shuffleanswers = 0;
        $newversion->answers = [
            23 => $newversion->answers[13],
            24 => $newversion->answers[14],
            25 => $newversion->answers[15],
            26 => $newversion->answers[16],
        ];
        $this->quba->regrade_question($this->slot, true, null, $newversion);

        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(1);
        $this->render();
        $this->assertStringNotContainsString(get_string('deletedchoice', 'qtype_multichoice'), $this->currentoutput);
    }
}
