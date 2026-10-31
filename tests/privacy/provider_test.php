<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_socialreport\privacy;

use core_privacy\local\metadata\collection;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\privacy\provider::class)]
/**
 * The plugin stores no personal data, and declares the call to the Matomo.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    public function test_the_call_to_the_matomo_is_declared_with_strings_that_exist(): void {
        $items = provider::get_metadata(new collection('local_socialreport'))->get_collection();
        $this->assertCount(1, $items);
        $link = $items[0];
        $this->assertInstanceOf(\core_privacy\local\metadata\types\external_location::class, $link);
        $this->assertSame('matomo', $link->get_name());
        $this->assertNotSame('[[privacy:metadata:matomo]]', get_string($link->get_summary(), 'local_socialreport'));
        foreach ($link->get_privacy_fields() as $string) {
            $this->assertNotSame('[[' . $string . ']]', get_string($string, 'local_socialreport'));
        }
        $this->assertArrayHasKey('ipaddress', $link->get_privacy_fields());
    }

    public function test_the_plugin_is_compliant_and_holds_no_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('local_socialreport'));
        $this->assertCount(0, provider::get_contexts_for_userid($user->id));
    }
}
