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

namespace local_socialreport;

use local_socialreport\local\codes;
use local_socialreport\local\provisioning;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\local\provisioning::class)]
/**
 * Tests of the Moodle side the plugin sets up: the service, the role, the key generated on the click of an administrator, the
 * key revoked and the account removed.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\local\provisioning
 */
final class provisioning_test extends \advanced_testcase {
    public function test_install_creates_service_and_role_but_no_account(): void {
        global $DB;
        $this->resetAfterTest();
        $service = provisioning::service();
        $this->assertNotFalse($service);
        $this->assertSame('1', (string) $service->enabled);
        $this->assertSame('1', (string) $service->restrictedusers);
        $this->assertSame(0, (int) $service->uploadfiles);
        $this->assertSame(0, (int) $service->downloadfiles);
        $functions = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$service->id]
        );
        $this->assertEqualsCanonicalizing(['local_socialreport_get_info', 'local_socialreport_get_counts'], $functions);
        $roleid = (int) get_config('local_socialreport', 'roleid');
        $this->assertGreaterThan(0, $roleid);
        $this->assertSame([CONTEXT_SYSTEM], array_values(array_map('intval', get_role_contextlevels($roleid))));
        $given = $DB->get_fieldset_select('role_capabilities', 'capability', 'roleid = ? AND permission = ?', [$roleid, CAP_ALLOW]);
        // The least privilege: exactly these two, and none of the ones that would open data of a person.
        $this->assertEqualsCanonicalizing(['webservice/rest:use', 'local/socialreport:viewcounts'], $given);
        $forbiddenlist = ['moodle/backup:userinfo', 'moodle/reportbuilder:view', 'moodle/reportbuilder:edit',
            'moodle/user:viewdetails', 'moodle/site:config', 'moodle/course:update'];
        foreach ($forbiddenlist as $forbidden) {
            $this->assertNotContains($forbidden, $given);
        }
        $this->assertFalse(provisioning::account());
        $this->assertFalse($DB->record_exists('user', ['username' => provisioning::USERNAME]));
    }

    public function test_generate_creates_account_authorises_it_and_gives_one_token(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $token = provisioning::generate_key();
        $this->assertNotSame('', $token);
        $account = provisioning::account();
        $this->assertSame(provisioning::USERNAME, $account->username);
        $this->assertSame('webservice', $account->auth);
        $service = provisioning::service();
        $authorised = ['externalserviceid' => $service->id, 'userid' => $account->id];
        $this->assertTrue($DB->record_exists('external_services_users', $authorised));
        $tokens = ['userid' => $account->id, 'externalserviceid' => $service->id];
        $this->assertSame(1, $DB->count_records('external_tokens', $tokens));
        $roleid = (int) get_config('local_socialreport', 'roleid');
        $this->assertTrue(user_has_role_assignment($account->id, $roleid, \context_system::instance()->id));
        $status = provisioning::status();
        $this->assertTrue($status['webservices'] && $status['rest'] && $status['role']);
        $this->assertSame(1, $status['tokens']);
    }

    public function test_regenerate_replaces_the_token_and_keeps_one(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $first = provisioning::generate_key();
        $second = provisioning::generate_key();
        $this->assertNotSame($first, $second);
        $this->assertSame(1, $DB->count_records('external_tokens', ['userid' => provisioning::account()->id]));
        $this->assertFalse($DB->record_exists('external_tokens', ['token' => $first]));
        $this->assertTrue($DB->record_exists('external_tokens', ['token' => $second]));
    }

    public function test_connection_code_carries_the_site_address_and_the_token(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $token = provisioning::generate_key();
        $decoded = codes::decode_connection(provisioning::connection_code($token));
        $this->assertSame(rtrim($CFG->wwwroot, '/'), $decoded['address']);
        $this->assertSame($token, $decoded['token']);
    }

    public function test_revoke_removes_the_tokens_and_keeps_the_account(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        provisioning::generate_key();
        $this->assertSame(1, provisioning::revoke_key());
        $this->assertSame(0, $DB->count_records('external_tokens', ['userid' => provisioning::account()->id]));
        $this->assertNotFalse(provisioning::account());
        // Negative control: revoking again finds nothing to remove.
        $this->assertSame(0, provisioning::revoke_key());
    }

    public function test_remove_account_deletes_it_and_a_new_key_makes_another(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        provisioning::generate_key();
        $this->assertTrue(provisioning::remove_account());
        $this->assertFalse(provisioning::account());
        $this->assertFalse(provisioning::remove_account());
        provisioning::generate_key();
        $this->assertNotFalse(provisioning::account());
    }

    public function test_only_a_site_administrator_generates_a_key(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        provisioning::generate_key();
    }

    public function test_uninstall_removes_account_role_and_tracker(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        provisioning::generate_key();
        \local_socialreport\local\tracker::save_code(codes::encode_tracker('https://m.example.org', 4));
        $roleid = (int) get_config('local_socialreport', 'roleid');
        provisioning::uninstall();
        $this->assertFalse(provisioning::account());
        $this->assertFalse($DB->record_exists('role', ['id' => $roleid]));
        $this->assertFalse(\local_socialreport\local\tracker::is_configured());
    }

    public function test_a_role_with_the_same_short_name_is_not_touched(): void {
        global $DB;
        $this->resetAfterTest();
        delete_role((int) get_config('local_socialreport', 'roleid'));
        unset_config('roleid', 'local_socialreport');
        $otherid = create_role('Another role', provisioning::ROLE, 'made by hand');
        $this->assertSame(0, provisioning::ensure_role());
        $this->assertSame('Another role', $DB->get_field('role', 'name', ['id' => $otherid]));
    }
}
