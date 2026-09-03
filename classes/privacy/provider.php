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

namespace qtype_mcq_chill\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for qtype_mcq_chill.
 *
 * The plugin stores no personal data of its own: student responses are kept
 * by the core question engine. It only records, as user preferences, the
 * settings last used by a teacher so that they become the defaults of the
 * next question they create.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('qtype_mcq_chill_defaultmark', 'privacy:preference:defaultmark');
        $collection->add_user_preference('qtype_mcq_chill_negativemarking', 'privacy:preference:negativemarking');
        $collection->add_user_preference('qtype_mcq_chill_allornothing', 'privacy:preference:allornothing');
        $collection->add_user_preference('qtype_mcq_chill_shuffleanswers', 'privacy:preference:shuffleanswers');
        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preference = get_user_preferences('qtype_mcq_chill_defaultmark', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:defaultmark', 'qtype_mcq_chill');
            writer::export_user_preference('qtype_mcq_chill', 'defaultmark', $preference, $desc);
        }

        $preference = get_user_preferences('qtype_mcq_chill_negativemarking', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:negativemarking', 'qtype_mcq_chill');
            writer::export_user_preference(
                'qtype_mcq_chill',
                'negativemarking',
                transform::percentage($preference),
                $desc
            );
        }

        $preference = get_user_preferences('qtype_mcq_chill_allornothing', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:allornothing', 'qtype_mcq_chill');
            writer::export_user_preference('qtype_mcq_chill', 'allornothing', transform::yesno($preference), $desc);
        }

        $preference = get_user_preferences('qtype_mcq_chill_shuffleanswers', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:shuffleanswers', 'qtype_mcq_chill');
            writer::export_user_preference('qtype_mcq_chill', 'shuffleanswers', transform::yesno($preference), $desc);
        }
    }
}
