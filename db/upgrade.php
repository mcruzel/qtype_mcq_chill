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
 * Upgrade steps for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database.
 *
 * @param int $oldversion the version being upgraded from.
 * @return bool always true.
 */
function xmldb_qtype_mcq_chill_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090300) {
        // Version 0.2 created qtype_mcq_chill_options without an id column, keyed on questionid,
        // with the negative marking stored as a percentage (-100 to 0). Rebuild the table with the
        // standard structure and convert the values to fractions (-1 to 0).
        $table = new xmldb_table('qtype_mcq_chill_options');
        $oldrows = [];
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, new xmldb_field('id'))) {
            foreach ($DB->get_records('qtype_mcq_chill_options') as $row) {
                $oldrows[$row->questionid] = $row;
            }
            $dbman->drop_table($table);
        }

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('negativemarking', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('allornothing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('shuffleanswers', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('questionid', XMLDB_KEY_FOREIGN_UNIQUE, ['questionid'], 'question', ['id']);
            $dbman->create_table($table);
        }

        $field = new xmldb_field('shuffleanswers', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'allornothing');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        foreach ($oldrows as $old) {
            if (!$DB->record_exists('question', ['id' => $old->questionid])) {
                continue;
            }
            $new = new stdClass();
            $new->questionid = $old->questionid;
            $new->negativemarking = max(-1, min(0, ((float) $old->negativemarking) / 100));
            $new->allornothing = empty($old->allornothing) ? 0 : 1;
            $new->shuffleanswers = 1;
            $DB->insert_record('qtype_mcq_chill_options', $new);
        }

        // Version 0.2 inherited from qtype_multichoice and left rows in its options table.
        // Keep the shuffling setting and remove the rows, which belong to questions of this type.
        $sql = "SELECT mo.id, mo.questionid, mo.shuffleanswers
                  FROM {qtype_multichoice_options} mo
                  JOIN {question} q ON q.id = mo.questionid
                 WHERE q.qtype = :qtype";
        foreach ($DB->get_records_sql($sql, ['qtype' => 'mcq_chill']) as $mcoptions) {
            $options = $DB->get_record('qtype_mcq_chill_options', ['questionid' => $mcoptions->questionid]);
            if ($options) {
                $options->shuffleanswers = empty($mcoptions->shuffleanswers) ? 0 : 1;
                $DB->update_record('qtype_mcq_chill_options', $options);
            } else {
                $options = new stdClass();
                $options->questionid = $mcoptions->questionid;
                $options->negativemarking = 0;
                $options->allornothing = 0;
                $options->shuffleanswers = empty($mcoptions->shuffleanswers) ? 0 : 1;
                $DB->insert_record('qtype_mcq_chill_options', $options);
            }
            $DB->delete_records('qtype_multichoice_options', ['id' => $mcoptions->id]);
        }

        // Make sure every question of this type has a settings record.
        $sql = "SELECT q.id
                  FROM {question} q
             LEFT JOIN {qtype_mcq_chill_options} o ON o.questionid = q.id
                 WHERE q.qtype = :qtype AND o.id IS NULL";
        foreach ($DB->get_fieldset_sql($sql, ['qtype' => 'mcq_chill']) as $questionid) {
            $options = new stdClass();
            $options->questionid = $questionid;
            $options->negativemarking = 0;
            $options->allornothing = 0;
            $options->shuffleanswers = 1;
            $DB->insert_record('qtype_mcq_chill_options', $options);
        }

        upgrade_plugin_savepoint(true, 2026090300, 'qtype', 'mcq_chill');
    }

    return true;
}
