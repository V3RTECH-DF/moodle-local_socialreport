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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\local\codes::class)]
/**
 * Tests of the connection code and of the tracker code.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\local\codes
 */
final class codes_test extends \basic_testcase {
    public function test_connection_code_round_trip(): void {
        $code = codes::encode_connection('https://cursos.example.org/', 'abcdef0123456789abcdef0123456789');
        $this->assertStringStartsWith('srmoodle1.', $code);
        $this->assertDoesNotMatchRegularExpression('~[+/=\s]~', $code);
        $this->assertSame(
            ['address' => 'https://cursos.example.org', 'token' => 'abcdef0123456789abcdef0123456789'],
            codes::decode_connection($code)
        );
    }

    public function test_connection_code_refuses_what_is_not_one(): void {
        $good = codes::encode_connection('https://cursos.example.org', 'abcdef0123456789abcdef0123456789');
        $badcodes = ['', 'srmoodle2.' . substr($good, 10), 'srtrack1.' . substr($good, 10), substr($good, 0, 20),
            $good . '!', 'srmoodle1.' . str_repeat('A', 3000), 'srmoodle1.e30'];
        foreach ($badcodes as $bad) {
            $this->assertNull(codes::decode_connection($bad), $bad);
        }
        // Negative control: the good one is still accepted, with blanks around it.
        $this->assertNotNull(codes::decode_connection("  $good\n"));
    }

    public function test_tracker_code_round_trip_and_validation(): void {
        $code = codes::encode_tracker('https://matomo.example.org/', 3);
        $this->assertStringStartsWith('srtrack1.', $code);
        $this->assertSame(['url' => 'https://matomo.example.org', 'siteid' => 3], codes::decode_tracker($code));
    }

    public function test_tracker_code_refuses_bad_address_and_site(): void {
        $bad = [
            codes::encode_tracker('javascript:alert(1)', 3),
            codes::encode_tracker('ftp://matomo.example.org', 3),
            codes::encode_tracker('https://user:pass@matomo.example.org', 3),
            codes::encode_tracker('https://matomo.example.org/?x=1', 3),
            codes::encode_tracker('https://matomo.example.org', 0),
            codes::encode_tracker('https://matomo.example.org', 2000000),
            codes::encode_connection('https://cursos.example.org', 'abcdef0123456789abcdef0123456789'),
            'srtrack1.' . rtrim(strtr(base64_encode('{"m":"https://m.example.org","s":"3"}'), '+/', '-_'), '='),
        ];
        foreach ($bad as $code) {
            $this->assertNull(codes::decode_tracker($code), $code);
        }
        // Negative control: http (a Matomo in a private network) and a path are accepted.
        $this->assertNotNull(codes::decode_tracker(codes::encode_tracker('http://matomo.example.org/matomo', 12)));
    }
}
