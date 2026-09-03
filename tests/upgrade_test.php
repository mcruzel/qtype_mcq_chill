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

use xmldb_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/question/type/mcq_chill/db/upgrade.php');

/**
 * Tests of the upgrade from version 0.2 of the plugin.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_qtype_mcq_chill_upgrade
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_qtype_mcq_chill_upgrade')]
final class upgrade_test extends \advanced_testcase {
    public function test_upgrade_from_version_0_2(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dbman = $DB->get_manager();

        // Three questions created with the current version.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $converted = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);
        $fromoldmultichoice = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);
        $withoutoptions = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);

        // Recreate the situation left by version 0.2: a table without id column, negative marking
        // as a percentage, and rows left in the multiple choice options table.
        $table = new xmldb_table('qtype_mcq_chill_options');
        $dbman->drop_table($table);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('negativemarking', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('allornothing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['questionid']);
        $dbman->create_table($table);
        // The old table has no id column, so the DML insert_record() helper cannot be used.
        $sql = 'INSERT INTO {qtype_mcq_chill_options} (questionid, negativemarking, allornothing) VALUES (?, ?, ?)';
        $DB->execute($sql, [$converted->id, -75, 1]);
        $DB->execute($sql, [999999, -10, 0]);
        $DB->insert_record('qtype_multichoice_options', (object) [
            'questionid' => $fromoldmultichoice->id, 'single' => 0, 'shuffleanswers' => 0, 'answernumbering' => 'abc',
            'showstandardinstruction' => 0, 'correctfeedback' => '', 'correctfeedbackformat' => FORMAT_HTML,
            'partiallycorrectfeedback' => '', 'partiallycorrectfeedbackformat' => FORMAT_HTML,
            'incorrectfeedback' => '', 'incorrectfeedbackformat' => FORMAT_HTML, 'shownumcorrect' => 0,
        ]);

        set_config('version', 2025051900, 'qtype_mcq_chill');
        $this->assertTrue(xmldb_qtype_mcq_chill_upgrade(2025051900));
        $this->assertEquals(2026090300, get_config('qtype_mcq_chill', 'version'));

        // The table has its standard structure again and the temporary copy of the old rows is gone.
        $this->assertTrue($dbman->field_exists($table, 'id'));
        $this->assertTrue($dbman->field_exists($table, 'shuffleanswers'));
        $this->assertFalse($dbman->table_exists(new xmldb_table('qtype_mcq_chill_opts_new')));

        // The old values were converted.
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $converted->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(-0.75, $options->negativemarking, 0.0000001);
        $this->assertEquals(1, $options->allornothing);
        $this->assertEquals(1, $options->shuffleanswers);

        // The row of a question that no longer exists was dropped.
        $this->assertFalse($DB->record_exists('qtype_mcq_chill_options', ['questionid' => 999999]));

        // The multiple choice row was converted then removed.
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $fromoldmultichoice->id], '*', MUST_EXIST);
        $this->assertEquals(0, $options->shuffleanswers);
        $this->assertEquals(0.0, $options->negativemarking);
        $this->assertFalse($DB->record_exists('qtype_multichoice_options', ['questionid' => $fromoldmultichoice->id]));

        // A question without any settings row got default settings.
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $withoutoptions->id], '*', MUST_EXIST);
        $this->assertEquals(0.0, $options->negativemarking);
        $this->assertEquals(0, $options->allornothing);
        $this->assertEquals(1, $options->shuffleanswers);

        // Every question can still be loaded and graded.
        foreach ([$converted, $fromoldmultichoice, $withoutoptions] as $question) {
            $loaded = \question_bank::load_question($question->id);
            $this->assertInstanceOf(\qtype_mcq_chill_question::class, $loaded);
            $this->assertCount(4, $loaded->answers);
        }

        // Running the step again changes nothing.
        set_config('version', 2025051900, 'qtype_mcq_chill');
        $this->assertTrue(xmldb_qtype_mcq_chill_upgrade(2025051900));
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $converted->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(-0.75, $options->negativemarking, 0.0000001);
        $this->assertEquals(3, $DB->count_records('qtype_mcq_chill_options'));
    }

    public function test_upgrade_resumes_after_an_interruption(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dbman = $DB->get_manager();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);
        $question = $generator->create_question('mcq_chill', 'twooffour', ['category' => $cat->id]);

        // Interrupted after the old table was dropped but before the new one was renamed:
        // the converted rows sit in the temporary table.
        $table = new xmldb_table('qtype_mcq_chill_options');
        $dbman->rename_table($table, 'qtype_mcq_chill_opts_new');
        set_config('version', 2025051900, 'qtype_mcq_chill');
        $this->assertTrue(xmldb_qtype_mcq_chill_upgrade(2025051900));
        $this->assertTrue($dbman->table_exists($table));
        $this->assertFalse($dbman->table_exists(new xmldb_table('qtype_mcq_chill_opts_new')));
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $question->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(-0.5, $options->negativemarking, 0.0000001);

        // Interrupted while the temporary table was being filled: the old table is still there
        // and the temporary table is rebuilt from it.
        $dbman->drop_table($table);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('negativemarking', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('allornothing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['questionid']);
        $dbman->create_table($table);
        $DB->execute(
            'INSERT INTO {qtype_mcq_chill_options} (questionid, negativemarking, allornothing) VALUES (?, ?, ?)',
            [$question->id, -25, 1]
        );
        $leftover = new xmldb_table('qtype_mcq_chill_opts_new');
        $leftover->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $leftover->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $leftover->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($leftover);

        set_config('version', 2025051900, 'qtype_mcq_chill');
        $this->assertTrue(xmldb_qtype_mcq_chill_upgrade(2025051900));
        $this->assertFalse($dbman->table_exists($leftover));
        $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $question->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(-0.25, $options->negativemarking, 0.0000001);
        $this->assertEquals(1, $options->allornothing);
        $this->assertEquals(1, $DB->count_records('qtype_mcq_chill_options'));
    }
}
