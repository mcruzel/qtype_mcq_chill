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
 * Restore support for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class for Drag-drop into text Chill questions.
 *
 * Choice numbers stored in attempt data are 1-based indexes (not database
 * ids), so they do not need recoding as long as choices are restored in the
 * original order.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_ddto_chill_plugin extends restore_qtype_plugin {
    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return restore_path_element[]
     */
    #[\Override]
    protected function define_question_plugin_structure() {
        $paths = [];
        $paths[] = new restore_path_element('ddto_chill_options', $this->get_pathfor('/ddto_chill_options'));
        $paths[] = new restore_path_element('ddto_chill_choice', $this->get_pathfor('/ddto_chill_choices/ddto_chill_choice'));
        return $paths;
    }

    /**
     * Process the options record.
     *
     * @param array|stdClass $data the data read from the backup.
     */
    public function process_ddto_chill_options($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        unset($data->id);
        $data->questionid = $this->get_new_parentid('question');
        $newitemid = $DB->insert_record('qtype_ddto_chill_options', $data);
        $this->set_mapping('qtype_ddto_chill_options', $oldid, $newitemid);
    }

    /**
     * Process one choice record.
     *
     * @param array|stdClass $data the data read from the backup.
     */
    public function process_ddto_chill_choice($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        unset($data->id);
        $data->questionid = $this->get_new_parentid('question');
        $newitemid = $DB->insert_record('qtype_ddto_chill_choices', $data);
        $this->set_mapping('qtype_ddto_chill_choices', $oldid, $newitemid);
    }
}
