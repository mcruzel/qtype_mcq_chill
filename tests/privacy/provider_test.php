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
use core_privacy\local\request\writer;

/**
 * Privacy provider tests for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_mcq_chill\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    public function test_get_metadata(): void {
        $collection = new collection('qtype_mcq_chill');
        $actual = provider::get_metadata($collection);
        $this->assertEquals($collection, $actual);
        $this->assertCount(4, $actual->get_collection());
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
    #[\PHPUnit\Framework\Attributes\DataProvider('user_preference_provider')]
    public function test_export_user_preferences(string $name, $value, string $expected): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        set_user_preference("qtype_mcq_chill_{$name}", $value, $user);
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $preferences = $writer->get_user_preferences('qtype_mcq_chill');
        $this->assertTrue(property_exists($preferences, $name));
        $this->assertEquals($expected, $preferences->$name->value);
        $this->assertEquals(
            get_string("privacy:preference:{$name}", 'qtype_mcq_chill'),
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
            'default mark 2' => ['defaultmark', 2, '2'],
            'negative marking -50%' => ['negativemarking', -0.5, '-50%'],
            'all or nothing yes' => ['allornothing', 1, get_string('yes')],
            'all or nothing no' => ['allornothing', 0, get_string('no')],
            'shuffle yes' => ['shuffleanswers', 1, get_string('yes')],
        ];
    }
}
