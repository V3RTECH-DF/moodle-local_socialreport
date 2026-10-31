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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\local\relay::class)]
/**
 * Tests of the relay of the tracker: the destination is locked to the saved Matomo, only allowed parameters pass, the limits
 * hold, and nothing like a cookie goes either way.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\local\relay
 */
final class relay_test extends \advanced_testcase {
    /** @var array A valid configuration. */
    private const CONFIG = ['url' => 'https://matomo.example.org/', 'siteid' => 3, 'enabled' => true];

    /** @var string The IP of the visitor in the tests. */
    private const IP = '203.0.113.9';

    /** @var array[] The calls the fake transport received. */
    private array $calls = [];

    /**
     * A transport that records the call and answers with the given status and body.
     *
     * @param int $status
     * @param string $body
     * @return callable
     */
    private function transport(int $status = 200, string $body = ''): callable {
        $this->calls = [];
        return function (
            string $method,
            string $url,
            array $headers,
            string $data,
            int $timeout,
            int $max
        ) use (
            $status,
            $body
        ): array {
            $this->calls[] = compact('method', 'url', 'headers', 'data', 'timeout', 'max');
            return ['status' => $status, 'body' => $body];
        };
    }

    /**
     * Passes a visit on with no token, from the visitor of the tests.
     *
     * @param callable $transport
     * @param string $method
     * @param string $query
     * @param string $body
     * @param array $server
     * @return string
     */
    private function fwd(callable $transport, string $method, string $query, string $body, array $server): string {
        return relay::forward_hit(self::CONFIG, '', $method, $query, $body, $server, self::IP, $transport);
    }

    /**
     * A body that passes as the tracker.
     *
     * @return string
     */
    private function fake_tracker(): string {
        return '/* matomo */ ' . str_repeat('var a=1;', 1000);
    }

    public function test_the_destination_is_always_the_saved_matomo_and_nothing_in_the_request_changes_it(): void {
        $t = $this->transport();
        $hostile = 'idsite=99&url=https%3A%2F%2Fx&rec=0&matomo_url=https%3A%2F%2Fevil.example&host=evil.example&token_auth=stolen';
        $result = relay::forward_hit(self::CONFIG, '', 'GET', $hostile, '', ['HTTP_HOST' => 'evil.example'], '203.0.113.9', $t);
        $this->assertSame('passed', $result);
        $this->assertCount(1, $this->calls);
        $this->assertSame('https://matomo.example.org/matomo.php', $this->calls[0]['url']);
        $this->assertSame('POST', $this->calls[0]['method']);
        parse_str($this->calls[0]['data'], $sent);
        $this->assertSame('3', $sent['idsite']);
        $this->assertArrayNotHasKey('matomo_url', $sent);
        $this->assertArrayNotHasKey('host', $sent);
        $this->assertArrayNotHasKey('token_auth', $sent);
        $this->assertStringNotContainsString('evil', $this->calls[0]['data'] . implode('', $this->calls[0]['headers']));
    }

    public function test_only_allowed_parameters_pass_and_the_ones_that_need_authority_do_not(): void {
        $this->assertTrue(relay::is_allowed_param('url'));
        $this->assertTrue(relay::is_allowed_param('e_c'));
        $this->assertTrue(relay::is_allowed_param('dimension12'));
        // Negative controls: what only the owner of a token may send, and what is not a tracker parameter.
        $forbidden = ['cip', 'cdt', 'cid', 'uid', 'country', 'city', 'lat', 'recMode', 'new_visit', 'token_auth', 'idsite', 'rec'];
        foreach (array_merge($forbidden, ['bots', 'x']) as $bad) {
            $this->assertFalse(relay::is_allowed_param($bad), $bad);
        }
        $sent = ['url' => 'u', 'cip' => '1.2.3.4', 'country' => 'xx', 'uid' => 'someone', 'nested' => ['a']];
        $clean = relay::sanitize_params($sent, 3, []);
        $this->assertSame(['url' => 'u', 'idsite' => '3', 'rec' => '1'], $clean);
        $this->assertNull(relay::sanitize_params(['cip' => '1.2.3.4', 'token_auth' => 'x'], 3, []));
    }

    public function test_a_request_with_nothing_of_a_visit_is_dropped_without_calling_the_matomo(): void {
        $t = $this->transport();
        $this->assertSame('dropped', $this->fwd($t, 'GET', 'cip=1.2.3.4&token_auth=x', '', []));
        $this->assertSame('dropped', $this->fwd($t, 'POST', '', '{"requests":[1,"?cip=1"]}', []));
        $this->assertCount(0, $this->calls);
    }

    public function test_do_not_track_and_global_privacy_control_are_dropped_and_other_visitors_are_not(): void {
        $t = $this->transport();
        $this->assertSame('dropped', $this->fwd($t, 'GET', 'url=a', '', ['HTTP_SEC_GPC' => '1']));
        $this->assertSame('dropped', $this->fwd($t, 'GET', 'url=a', '', ['HTTP_DNT' => '1']));
        $this->assertCount(0, $this->calls);
        // Negative controls: the signal off, or absent, is measured.
        $this->assertSame('passed', $this->fwd($t, 'GET', 'url=a', '', ['HTTP_SEC_GPC' => '0']));
        $this->assertSame('passed', $this->fwd($t, 'GET', 'url=a', '', ['HTTP_DNT' => '0']));
        $this->assertCount(2, $this->calls);
    }

    public function test_no_cookie_goes_to_the_matomo_and_the_only_headers_are_the_known_ones(): void {
        $t = $this->transport();
        $server = [
            'HTTP_COOKIE' => 'MoodleSession=segredo; _pk_id=abc', 'HTTP_USER_AGENT' => "Mozilla/5.0\r\nX-Evil: 1",
            'HTTP_ACCEPT_LANGUAGE' => 'pt-BR', 'HTTP_AUTHORIZATION' => 'Bearer segredo',
        ];
        relay::forward_hit(self::CONFIG, '', 'GET', 'url=a', '', $server, '203.0.113.9', $t);
        $headers = $this->calls[0]['headers'];
        foreach ($headers as $h) {
            $this->assertDoesNotMatchRegularExpression('/^(cookie|authorization)/i', $h);
            $this->assertStringNotContainsString('segredo', $h);
            $this->assertStringNotContainsString("\n", $h);
            $this->assertStringNotContainsString("\r", $h);
        }
        $this->assertContains('X-Forwarded-For: 203.0.113.9', $headers);
        $this->assertContains('Accept-Language: pt-BR', $headers);
        $this->assertContains('User-Agent: Mozilla/5.0 X-Evil: 1', $headers);   // The line break became a space: one header.
        $this->assertStringNotContainsString('MoodleSession', $this->calls[0]['data']);
    }

    public function test_the_ip_reaches_the_matomo_and_a_forged_one_does_not(): void {
        $t = $this->transport();
        $this->fwd($t, 'GET', 'url=a&cip=6.6.6.6', '', []);
        parse_str($this->calls[0]['data'], $sent);
        $this->assertArrayNotHasKey('cip', $sent);   // Without a token the Matomo would ignore it anyway: it is not even sent.
        $this->assertContains('X-Forwarded-For: 203.0.113.9', $this->calls[0]['headers']);
        // With the token, the server sets cip itself, and the one of the browser is gone.
        relay::forward_hit(self::CONFIG, 'tok', 'GET', 'url=a&cip=6.6.6.6', '', ['HTTP_USER_AGENT' => 'UA'], '203.0.113.9', $t);
        parse_str($this->calls[1]['data'], $sent);
        $this->assertSame('203.0.113.9', $sent['cip']);
        $this->assertSame('tok', $sent['token_auth']);
        $this->assertSame('UA', $sent['ua']);
        // An invalid IP is not passed as a header.
        relay::forward_hit(self::CONFIG, '', 'GET', 'url=a', '', [], "1.2.3.4\r\nX: y", $t);
        $this->assertEmpty(preg_grep('/^X-Forwarded-For/', $this->calls[2]['headers']));
    }

    public function test_the_token_goes_only_to_the_matomo_and_is_never_in_what_the_visitor_gets(): void {
        $this->resetAfterTest();
        tracker::save_code(codes::encode_tracker('https://matomo.example.org', 3));
        tracker::save_token('TOKEN-SECRETO');
        $this->setUser(0);
        $this->assertStringNotContainsString('TOKEN-SECRETO', tracker::html_for_current_page(false));
        $this->assertStringNotContainsString('TOKEN-SECRETO', tracker::snippet('/a', '/b', 3));
        $t = $this->transport();
        relay::forward_hit(tracker::config(), tracker::token(), 'GET', 'url=a', '', [], '203.0.113.9', $t);
        $this->assertStringContainsString('TOKEN-SECRETO', $this->calls[0]['data']);
        $this->assertStringNotContainsString('TOKEN-SECRETO', implode('', $this->calls[0]['headers']) . $this->calls[0]['url']);
    }

    public function test_a_bulk_request_is_cut_at_the_limit_and_each_visit_is_cleaned(): void {
        $requests = [];
        for ($i = 0; $i < relay::MAX_BULK + 20; $i++) {
            $requests[] = '?idsite=99&url=p' . $i . '&cip=6.6.6.6';
        }
        $body = json_encode(['requests' => $requests, 'token_auth' => 'stolen']);
        $payload = relay::build_payload([], $body, 3, 'tok', self::IP, 'UA', '');
        $this->assertSame('application/json', $payload['content_type']);
        $data = json_decode($payload['body'], true);
        $this->assertCount(relay::MAX_BULK, $data['requests']);
        $this->assertSame('tok', $data['token_auth']);
        parse_str(ltrim($data['requests'][0], '?'), $first);
        $this->assertSame('3', $first['idsite']);
        $this->assertSame('203.0.113.9', $first['cip']);
        $this->assertSame('p0', $first['url']);
    }

    public function test_the_tracker_is_fetched_from_the_saved_matomo_only_with_limits_and_cached(): void {
        $this->resetAfterTest();
        $t = $this->transport(200, $this->fake_tracker());
        $this->assertSame($this->fake_tracker(), relay::get_script(self::CONFIG, $t));
        $this->assertSame('GET', $this->calls[0]['method']);
        $this->assertSame('https://matomo.example.org/matomo.js', $this->calls[0]['url']);
        $this->assertSame(relay::TIMEOUT_SCRIPT, $this->calls[0]['timeout']);
        $this->assertSame(relay::MAX_SCRIPT, $this->calls[0]['max']);
        // Cached: the second call does not ask the Matomo.
        $this->assertSame($this->fake_tracker(), relay::get_script(self::CONFIG, $t));
        $this->assertCount(1, $this->calls);
    }

    public function test_the_tracker_refuses_what_is_not_the_tracker_and_serves_the_old_copy_when_the_matomo_fails(): void {
        $this->resetAfterTest();
        // Nothing cached and the Matomo answers an error page: no tracker.
        $this->assertNull(relay::get_script(self::CONFIG, $this->transport(200, '<html>' . str_repeat('matomo', 2000))));
        $this->assertNull(relay::get_script(self::CONFIG, $this->transport(500, $this->fake_tracker())));
        $this->assertNull(relay::get_script(self::CONFIG, $this->transport(200, 'matomo tiny')));
        $this->assertNull(relay::get_script(self::CONFIG, $this->transport(200, str_repeat('matomo', relay::MAX_SCRIPT))));
        // After a good copy and an expired time, a failing Matomo gives the old copy (the negative control above gave null).
        $cache = \cache::make('local_socialreport', 'script');
        $cache->delete('failedat');
        $cache->set('script', [
            'body' => $this->fake_tracker(),
            'source' => 'https://matomo.example.org/matomo.js',
            'fetchedat' => time() - relay::SCRIPT_TTL - 5,
        ]);
        $this->assertSame($this->fake_tracker(), relay::get_script(self::CONFIG, $this->transport(503, '')));
        // A copy made for another Matomo address is never served for this one.
        $this->assertNull(relay::get_script(['url' => 'https://other.example.org'] + self::CONFIG, $this->transport(503, '')));
    }

    public function test_a_failure_of_the_matomo_is_reported_and_the_request_is_not_repeated(): void {
        $t = $this->transport(500, '');
        $this->assertSame('failed', $this->fwd($t, 'GET', 'url=a', '', []));
        $this->assertSame(relay::TIMEOUT_HIT, $this->calls[0]['timeout']);
        $this->assertCount(1, $this->calls);
    }
}
