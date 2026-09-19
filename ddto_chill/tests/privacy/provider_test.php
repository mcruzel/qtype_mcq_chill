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

namespace qtype_ddto_chill\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Privacy provider tests for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_ddto_chill\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    public function test_get_metadata(): void {
        $collection = new collection('qtype_ddto_chill');
        $actual = provider::get_metadata($collection);
        $this->assertEquals($collection, $actual);
        $names = [];
        foreach ($actual->get_collection() as $item) {
            $this->assertInstanceOf(\core_privacy\local\metadata\types\user_preference::class, $item);
            $names[] = $item->get_name();
        }
        $this->assertEqualsCanonicalizing(['qtype_ddto_chill_defaultmark', 'qtype_ddto_chill_negativemarking',
            'qtype_ddto_chill_allornothing', 'qtype_ddto_chill_shuffleanswers'], $names);
    }

    public function test_export_user_preferences_no_pref(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Test the export of each user preference.
     *
     * @dataProvider user_preference_provider
     * @param string $name the name of the user preference.
     * @param mixed $value the value stored in the database.
     * @param string $expected the expected transformed value.
     */
    public function test_export_user_preferences(string $name, $value, string $expected): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        set_user_preference("qtype_ddto_chill_{$name}", $value, $user);
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $preferences = $writer->get_user_preferences('qtype_ddto_chill');
        $this->assertTrue(property_exists($preferences, $name));
        $this->assertEquals($expected, $preferences->$name->value);
        $this->assertEquals(
            get_string("privacy:preference:{$name}", 'qtype_ddto_chill'),
            $preferences->$name->description
        );
    }

    /**
     * Valid user preferences of the plugin.
     *
     * @return array[]
     */
    public static function user_preference_provider(): array {
        return [
            'default mark' => ['defaultmark', 1, '1'],
            'negative marking' => ['negativemarking', -0.5, '-50%'],
            'all or nothing yes' => ['allornothing', 1, get_string('yes')],
            'shuffle yes' => ['shuffleanswers', 1, get_string('yes')],
        ];
    }
}
