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
 * Backup support for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup Drag-drop into text Chill questions.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_ddto_chill_plugin extends backup_qtype_plugin {
    /**
     * Returns the qtype information to attach to question element.
     *
     * @return backup_plugin_element
     */
    #[\Override]
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../qtype', 'ddto_chill');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $options = new backup_nested_element('ddto_chill_options', ['id'], [
            'questionid', 'negativemarking', 'allornothing', 'shuffleanswers',
        ]);
        $pluginwrapper->add_child($options);
        $options->set_source_table('qtype_ddto_chill_options', ['questionid' => backup::VAR_PARENTID]);

        $choices = new backup_nested_element('ddto_chill_choices');
        $choice = new backup_nested_element('ddto_chill_choice', ['id'], [
            'questionid', 'text', 'choicegroup', 'fraction',
        ]);
        $pluginwrapper->add_child($choices);
        $choices->add_child($choice);
        $choice->set_source_table('qtype_ddto_chill_choices', ['questionid' => backup::VAR_PARENTID], 'id ASC');

        return $plugin;
    }
}
