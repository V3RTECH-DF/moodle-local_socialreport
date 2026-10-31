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

namespace local_socialreport\external;

use local_socialreport\local\provisioning;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\external\get_counts::class)]
/**
 * Tests of the web service functions: only the account with the capability reads, and the answer has the documented shape.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\external\get_counts
 * @covers     \local_socialreport\external\get_info
 */
final class get_counts_test extends \advanced_testcase {
    /**
     * The arguments of a valid call.
     *
     * @return string[]
     */
    private function args(): array {
        return ['2026-10-01', '2026-10-28', '2026-09-01', '2026-09-30', '2025-10-01', '2025-10-28', '2026-07-30'];
    }

    /**
     * Makes the account with the role of the plugin and signs in as it.
     */
    private function sign_in_as_integration(): void {
        $this->setAdminUser();
        provisioning::generate_key();
        $this->setUser(provisioning::account());
    }

    public function test_the_integration_account_reads_counts_with_the_documented_shape(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course(['shortname' => 'Curso']);
        $this->sign_in_as_integration();
        $raw = get_counts::execute(...$this->args());
        $result = get_counts::clean_returnvalue(get_counts::execute_returns(), $raw);
        $this->assertSame(get_info::API_VERSION, $result['apiversion']);
        $this->assertSame('Curso', $result['courses'][0]['shortname']);
        $this->assertSame([], $result['daily']);
    }

    public function test_a_user_without_the_capability_is_refused(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        get_counts::execute(...$this->args());
    }

    public function test_a_role_without_the_capability_is_refused_too(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        get_counts::execute(...$this->args());
    }

    public function test_get_info_reports_the_version_and_the_tracker(): void {
        $this->resetAfterTest();
        $this->sign_in_as_integration();
        $info = get_info::clean_returnvalue(get_info::execute_returns(), get_info::execute());
        $this->assertSame('local_socialreport', $info['component']);
        $this->assertSame(get_info::API_VERSION, $info['apiversion']);
        $this->assertFalse($info['tracker']['configured']);
        \local_socialreport\local\tracker::save_code(\local_socialreport\local\codes::encode_tracker('https://m.example.org', 4));
        $info = get_info::execute();
        $this->assertTrue($info['tracker']['configured']);
        $this->assertSame(4, $info['tracker']['siteid']);
    }

    public function test_bad_date_is_an_invalid_parameter(): void {
        $this->resetAfterTest();
        $this->sign_in_as_integration();
        $args = $this->args();
        $args[0] = '01/10/2026';
        $this->expectException(\invalid_parameter_exception::class);
        get_counts::execute(...$args);
    }
}
