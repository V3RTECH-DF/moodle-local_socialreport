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

namespace local_socialreport\local;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\local\tracker::class)]
/**
 * Tests of the Matomo tracker: it is injected only with a valid configuration, and never for an administrator.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\local\tracker
 */
final class tracker_test extends \advanced_testcase {
    /** @var array A valid configuration. */
    private const CONFIG = ['url' => 'https://matomo.example.org', 'siteid' => 3, 'enabled' => true];

    public function test_nothing_is_configured_on_a_fresh_site(): void {
        $this->resetAfterTest();
        $this->assertFalse(tracker::is_configured());
        $this->assertFalse(tracker::config()['enabled']);
        $this->assertSame('', tracker::html_for_current_page());
    }

    public function test_save_code_configures_and_turns_on(): void {
        $this->resetAfterTest();
        $this->assertTrue(tracker::save_code(codes::encode_tracker('https://matomo.example.org/', 7)));
        $this->assertSame(['url' => 'https://matomo.example.org', 'siteid' => 7, 'enabled' => true], tracker::config());
        $this->assertTrue(tracker::is_configured());
    }

    public function test_invalid_code_changes_nothing(): void {
        $this->resetAfterTest();
        tracker::save_code(codes::encode_tracker('https://matomo.example.org', 7));
        $this->assertFalse(tracker::save_code('lixo'));
        $this->assertFalse(tracker::save_code(codes::encode_tracker('javascript:x', 9)));
        $this->assertSame(7, tracker::config()['siteid']);
    }

    public function test_clear_removes_everything(): void {
        $this->resetAfterTest();
        tracker::save_code(codes::encode_tracker('https://matomo.example.org', 7));
        tracker::clear();
        $this->assertFalse(tracker::is_configured());
        $this->assertFalse(tracker::config()['enabled']);
    }

    public function test_should_inject_only_with_valid_configuration(): void {
        $this->assertTrue(tracker::should_inject(self::CONFIG, false, '/course/view.php', false));
        // Negative controls: each missing piece, alone, stops it.
        $this->assertFalse(tracker::should_inject(['enabled' => false] + self::CONFIG, false, '/course/view.php', false));
        $this->assertFalse(tracker::should_inject(['url' => ''] + self::CONFIG, false, '/course/view.php', false));
        $this->assertFalse(tracker::should_inject(['siteid' => 0] + self::CONFIG, false, '/course/view.php', false));
    }

    public function test_should_not_inject_for_administrator_admin_pages_or_background_requests(): void {
        $this->assertFalse(tracker::should_inject(self::CONFIG, true, '/course/view.php', false));
        $this->assertFalse(tracker::should_inject(self::CONFIG, false, '/admin/index.php', false));
        $this->assertFalse(tracker::should_inject(self::CONFIG, false, '/local/socialreport/key.php', false));
        $this->assertFalse(tracker::should_inject(self::CONFIG, false, '/course/view.php', true));
        // The ordinary visitor of a page whose name only looks like the administration is still measured.
        $this->assertTrue(tracker::should_inject(self::CONFIG, false, '/mod/administration/view.php', false));
    }

    public function test_snippet_respects_privacy_signals_and_uses_no_cookie(): void {
        $html = tracker::snippet('/local/socialreport/s.php', '/local/socialreport/h.php', 3);
        $this->assertStringContainsString("disableCookies", $html);
        $this->assertStringContainsString('doNotTrack', $html);
        $this->assertStringContainsString('globalPrivacyControl', $html);
        $this->assertStringContainsString("'setSiteId', 3", $html);
    }

    public function test_snippet_points_only_to_this_moodle_never_to_the_matomo(): void {
        $this->resetAfterTest();
        tracker::save_code(codes::encode_tracker('https://matomo.example.org', 3));
        tracker::save_token('segredo-do-token');
        $this->setUser(0);
        $html = tracker::html_for_current_page(false);
        $this->assertStringContainsString('/local/socialreport/s.php', $html);
        $this->assertStringContainsString('/local/socialreport/h.php', $html);
        $this->assertStringNotContainsString('matomo.example.org', $html);
        $this->assertStringNotContainsString('matomo.php', $html);
        $this->assertStringNotContainsString('segredo-do-token', $html);
    }

    public function test_snippet_cannot_be_broken_out_of_by_the_address(): void {
        $html = tracker::snippet('/x/</script><script>alert(1)</script>', '/y', 3);
        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_token_is_saved_cleared_and_removed_with_the_tracker(): void {
        $this->resetAfterTest();
        $this->assertSame('', tracker::token());
        tracker::save_token('  abc  ');
        $this->assertSame('abc', tracker::token());
        tracker::save_token('');
        $this->assertSame('', tracker::token());
        tracker::save_token('abc');
        tracker::clear();
        $this->assertSame('', tracker::token());
    }

    public function test_html_for_current_page_for_a_visitor_and_an_administrator(): void {
        global $PAGE;
        $this->resetAfterTest();
        tracker::save_code(codes::encode_tracker('https://matomo.example.org', 3));
        $PAGE->set_url('/course/view.php');
        $this->setUser(0);
        $this->assertStringContainsString('/local/socialreport/s.php', tracker::html_for_current_page(false));
        $this->setAdminUser();
        $this->assertSame('', tracker::html_for_current_page(false));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertStringContainsString('/local/socialreport/s.php', tracker::html_for_current_page(false));
        // A background request (PHPUnit is a cli script) is never measured.
        $this->assertSame('', tracker::html_for_current_page());
    }
}
