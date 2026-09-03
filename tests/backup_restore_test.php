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
}
