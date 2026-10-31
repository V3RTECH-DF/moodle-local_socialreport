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

/**
 * The two codes the site administrator copies and pastes, so that the plugin makes no call to the outside:
 *
 * - the CONNECTION code, made here (the site address and the token of the integration account), pasted in the SocialReport, in
 *   the registration of the client;
 * - the TRACKER code, made by the SocialReport (the address of the Matomo and the id of the site there), pasted here.
 *
 * Both are a prefix that carries the format version, a dot and base64url of a small JSON object. Neither is encrypted: the
 * connection code holds the token, so it is shown once, to be pasted at once. The prefix is only so that a wrong paste is told
 * apart from a good one with a clear message.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class codes {
    /** Prefix of the connection code, with the format version. */
    const CONNECTION_PREFIX = 'srmoodle1';
    /** Prefix of the tracker code, with the format version. */
    const TRACKER_PREFIX = 'srtrack1';
    /** Longest code accepted, so that a wrong paste of a large text is not decoded. */
    const MAX_LENGTH = 2000;

    /**
     * Base64url of a string, without padding.
     *
     * @param string $raw
     * @return string
     */
    private static function b64url_encode(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * The JSON object of a code with the given prefix, or null when it is not one.
     *
     * @param string $code
     * @param string $prefix
     * @return array|null
     */
    private static function decode(string $code, string $prefix): ?array {
        $code = trim($code);
        if ($code === '' || strlen($code) > self::MAX_LENGTH || strpos($code, $prefix . '.') !== 0) {
            return null;
        }
        $body = substr($code, strlen($prefix) + 1);
        if (!preg_match('~^[A-Za-z0-9_-]+$~', $body)) {
            return null;
        }
        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * The connection code: site address and token.
     *
     * @param string $address address of the Moodle site (wwwroot)
     * @param string $token token of the integration account
     * @return string
     */
    public static function encode_connection(string $address, string $token): string {
        $json = json_encode(['u' => rtrim($address, '/'), 't' => $token], JSON_UNESCAPED_SLASHES);
        return self::CONNECTION_PREFIX . '.' . self::b64url_encode($json);
    }

    /**
     * Reads a connection code (used by the tests and by anyone who wants to check one).
     *
     * @param string $code
     * @return array|null ['address' => string, 'token' => string], or null when it is not a connection code
     */
    public static function decode_connection(string $code): ?array {
        $data = self::decode($code, self::CONNECTION_PREFIX);
        if (!$data || !is_string($data['u'] ?? null) || !is_string($data['t'] ?? null) || $data['u'] === '' || $data['t'] === '') {
            return null;
        }
        return ['address' => $data['u'], 'token' => $data['t']];
    }

    /**
     * The tracker code (what the SocialReport makes): address of the Matomo and id of the site.
     *
     * @param string $matomourl
     * @param int $siteid
     * @return string
     */
    public static function encode_tracker(string $matomourl, int $siteid): string {
        $json = json_encode(['m' => rtrim($matomourl, '/'), 's' => $siteid], JSON_UNESCAPED_SLASHES);
        return self::TRACKER_PREFIX . '.' . self::b64url_encode($json);
    }

    /**
     * Reads and VALIDATES a tracker code: the address is http or https with a host and no user or password, and the site id is
     * a positive integer. Nothing else is read from it.
     *
     * @param string $code
     * @return array|null ['url' => string, 'siteid' => int], or null when it is not a valid tracker code
     */
    public static function decode_tracker(string $code): ?array {
        $data = self::decode($code, self::TRACKER_PREFIX);
        if (!$data || !is_string($data['m'] ?? null) || !is_int($data['s'] ?? null)) {
            return null;
        }
        $siteid = $data['s'];
        $url = rtrim(trim($data['m']), '/');
        $parts = parse_url($url);
        $validurl = $parts && !empty($parts['host']) && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && strlen($url) <= 255;
        if (!$validurl || $siteid < 1 || $siteid > 1000000) {
            return null;
        }
        return ['url' => $url, 'siteid' => $siteid];
    }
}
