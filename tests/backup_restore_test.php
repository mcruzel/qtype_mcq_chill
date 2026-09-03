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

use backup;
use backup_controller;
use question_bank;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Tests of the course backup and restore of QCM Chill questions.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_qtype_mcq_chill_plugin
 * @covers     \restore_qtype_mcq_chill_plugin
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_qtype_mcq_chill_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_qtype_mcq_chill_plugin::class)]
final class backup_restore_test extends \advanced_testcase {
    public function test_backup_and_restore_a_course_with_a_question(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;

        // A course with a quiz owning a QCM Chill question.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question('mcq_chill', 'allornothing', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        // Backup the course.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore it into a new course.
        $newcourse = $generator->create_course();
        $rc = new restore_controller(
            $backupid,
            $newcourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // The restored question is the QCM Chill question that is not the original one.
        $questionids = $DB->get_fieldset_select('question', 'id', "qtype = ?", ['mcq_chill']);
        $this->assertCount(2, $questionids);
        $newquestionid = (int) current(array_diff($questionids, [$question->id]));

        $this->assertEquals(1, $DB->count_records('qtype_mcq_chill_options', ['questionid' => $newquestionid]));
        $restored = question_bank::load_question($newquestionid);
        $this->assertInstanceOf(\qtype_mcq_chill_question::class, $restored);
        $this->assertEquals('QCM Chill all or nothing', $restored->name);
        $this->assertEqualsWithDelta(-0.25, $restored->negativemarking, 0.0000001);
        $this->assertEquals(1, $restored->allornothing);
        $this->assertEquals(1, $restored->shuffleanswers);
        $this->assertEquals(['One', 'Two', 'Three', 'Four'], array_values(array_column($restored->answers, 'answer')));
        $this->assertEquals([1.0, 0.0, 1.0, 0.0], array_values(array_column($restored->answers, 'fraction')));

        // The restored answers are new records, distinct from the original ones.
        $originalanswerids = $DB->get_fieldset_select('question_answers', 'id', 'question = ?', [$question->id]);
        $this->assertEmpty(array_intersect($originalanswerids, array_keys($restored->answers)));
    }

    public function test_restore_with_an_attempt_recodes_the_choice_order(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;

        // A course, a quiz with a QCM Chill question and a student who attempted it.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'preferredbehaviour' => 'deferredfeedback',
            'grade' => 10, 'sumgrades' => 2]);
        $context = \context_module::instance($quiz->cmid);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question(
            'mcq_chill',
            'twooffour',
            ['category' => $cat->id, 'shuffleanswers' => 0]
        );
        quiz_add_quiz_question($question->id, $quiz, 0, 2);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $quizobj = class_exists('\mod_quiz\quiz_settings')
                ? \mod_quiz\quiz_settings::create($quiz->id, $student->id)
                : \quiz::create($quiz->id, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = class_exists('\mod_quiz\quiz_attempt')
                ? \mod_quiz\quiz_attempt::create($attempt->id)
                : \quiz_attempt::create($attempt->id);
        // Simulated responses are given by choice text.
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['One' => 1, 'Three' => 1]]);
        if (method_exists($attemptobj, 'process_grade_submission')) {
            // Moodle 5.0 and later.
            $attemptobj->process_submit($timenow, false);
            $attemptobj->process_grade_submission($timenow);
        } else {
            $attemptobj->process_finish($timenow, false);
        }
        $this->assertEquals(2, $DB->get_field('quiz_attempts', 'sumgrades', ['id' => $attempt->id]));

        // Backup with user data, restore into a new course.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourse = $generator->create_course();
        $rc = new restore_controller(
            $backupid,
            $newcourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // The restored attempt refers to the restored question and its new answer ids.
        $newquiz = $DB->get_record('quiz', ['course' => $newcourse->id], '*', MUST_EXIST);
        $newattempts = $DB->get_records('quiz_attempts', ['quiz' => $newquiz->id]);
        $this->assertCount(1, $newattempts);
        $newattempt = reset($newattempts);
        $this->assertNotEquals($attempt->uniqueid, $newattempt->uniqueid);

        $newquba = \question_engine::load_questions_usage_by_activity($newattempt->uniqueid);
        $qa = $newquba->get_question_attempt(1);
        $this->assertNotEquals($question->id, $qa->get_question_id());
        $this->assertInstanceOf(\qtype_mcq_chill_question::class, $qa->get_question(false));

        $newanswerids = $DB->get_fieldset_select('question_answers', 'id', 'question = ?', [$qa->get_question_id()]);
        $order = explode(',', $qa->get_step(0)->get_qt_var('_order'));
        $this->assertCount(4, $order);
        $this->assertEqualsCanonicalizing($newanswerids, array_map('intval', $order));

        $this->assertEquals('One; Three', $qa->get_response_summary());
        $this->assertEqualsWithDelta(1.0, $qa->get_fraction(), 0.0000001);
        $this->assertEquals(2, $DB->get_field('quiz_attempts', 'sumgrades', ['id' => $newattempt->id]));

        // Regrading against the restored question gives the same result.
        $newquba->regrade_all_questions();
        $this->assertEqualsWithDelta(1.0, $newquba->get_question_attempt(1)->get_fraction(), 0.0000001);
    }
}
