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
 * The Matomo tracker in the pages of Moodle, the same job the V3RAnalytics plugin does on WordPress: the SocialReport reads
 * the origin of the visits (where the enrolments came from) from the Matomo of the organisation, and Moodle does not know that,
 * only the tracker in the pages does.
 *
 * The plugin needs two things that are not secret, both in the tracker code the SocialReport shows: the address of the Matomo
 * and the id of the site there. The browser of the visitor never talks to the Matomo: the pages load the tracker from this
 * Moodle (s.php) and send the visits to it (h.php), and the server passes them on (see relay). A write token of the Matomo is
 * optional (it only makes the IP of the visitor trustworthy to the Matomo); it stays on the server, in the plugin settings, and
 * is never written in a page or script.
 *
 * Privacy: the tracker runs without cookies (disableCookies), and the browser decides before loading it: a visitor who sends
 * "Do Not Track", "Global Privacy Control" or its old Microsoft form is not measured. A site administrator is never measured
 * (nor are the pages of the administration), so that the work on the site does not count as visits.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracker {
    /** Component of the settings. */
    const COMPONENT = 'local_socialreport';

    /**
     * The tracker settings: address, site id and whether it is on. Empty address = not configured.
     *
     * @return array ['url' => string, 'siteid' => int, 'enabled' => bool]
     */
    public static function config(): array {
        return [
            'url' => (string) get_config(self::COMPONENT, 'matomourl'),
            'siteid' => (int) get_config(self::COMPONENT, 'matomositeid'),
            'enabled' => (bool) get_config(self::COMPONENT, 'trackerenabled'),
        ];
    }

    /**
     * The optional write token of the Matomo, for the server to pass on the IP of the visitor. Never shown, never in a page.
     *
     * @return string empty when none
     */
    public static function token(): string {
        return (string) get_config(self::COMPONENT, 'matomotoken');
    }

    /**
     * Saves (or, empty, removes) the optional write token.
     *
     * @param string $token
     */
    public static function save_token(string $token): void {
        $token = trim($token);
        if ($token === '') {
            unset_config('matomotoken', self::COMPONENT);
        } else {
            set_config('matomotoken', $token, self::COMPONENT);
        }
    }

    /**
     * Whether the settings are enough to inject the tracker.
     *
     * @param array|null $config
     * @return bool
     */
    public static function is_configured(?array $config = null): bool {
        $config = $config ?? self::config();
        return $config['url'] !== '' && $config['siteid'] > 0;
    }

    /**
     * Saves a tracker code pasted by the administrator, and turns the tracker on.
     *
     * @param string $code
     * @return bool false when the code is not valid (nothing is changed)
     */
    public static function save_code(string $code): bool {
        $data = codes::decode_tracker($code);
        if ($data === null) {
            return false;
        }
        set_config('matomourl', $data['url'], self::COMPONENT);
        set_config('matomositeid', $data['siteid'], self::COMPONENT);
        set_config('trackerenabled', 1, self::COMPONENT);
        return true;
    }

    /**
     * Removes the tracker settings: the pages stop carrying the tracker at once.
     */
    public static function clear(): void {
        unset_config('matomourl', self::COMPONENT);
        unset_config('matomositeid', self::COMPONENT);
        unset_config('trackerenabled', self::COMPONENT);
        unset_config('matomotoken', self::COMPONENT);
    }

    /**
     * Turns the tracker on or off, keeping the address and the site id.
     *
     * @param bool $enabled
     */
    public static function set_enabled(bool $enabled): void {
        set_config('trackerenabled', $enabled ? 1 : 0, self::COMPONENT);
    }

    /**
     * Whether this request is one to measure. Pure: everything it needs comes in the arguments, so it is easy to test.
     *
     * @param array $config settings (see config())
     * @param bool $siteadmin the user is a site administrator
     * @param string $path path of the page (from the site root)
     * @param bool $background the request is a script that is not a page (ajax, web service, cli)
     * @return bool
     */
    public static function should_inject(array $config, bool $siteadmin, string $path, bool $background): bool {
        if (!$config['enabled'] || !self::is_configured($config) || $background || $siteadmin) {
            return false;
        }
        return strpos($path, '/admin/') !== 0 && strpos($path, '/local/socialreport/') !== 0;
    }

    /**
     * The script of the tracker. It points to this Moodle, never to the Matomo. Every value goes through json_encode with the tag
     * escapes, so nothing configured can close the script element.
     *
     * @param string $scripturl where the tracker is loaded from (the s.php of this plugin)
     * @param string $hiturl where the visits are sent (the h.php of this plugin)
     * @param int $siteid
     * @return string
     */
    public static function snippet(string $scripturl, string $hiturl, int $siteid): string {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $script = json_encode($scripturl, $flags);
        $hit = json_encode($hiturl, $flags);
        $id = json_encode($siteid, $flags);
        return "<!-- SocialReport tracker -->\n<script>\n" .
            "(function (w, n, d) {\n" .
            "  if (n.globalPrivacyControl === true || n.doNotTrack === '1' || w.doNotTrack === '1' || n.msDoNotTrack === '1') {\n" .
            "    return;\n" .
            "  }\n" .
            "  var _paq = w._paq = w._paq || [];\n" .
            "  _paq.push(['disableCookies']);\n" .
            "  _paq.push(['trackPageView']);\n" .
            "  _paq.push(['enableLinkTracking']);\n" .
            "  _paq.push(['setTrackerUrl', {$hit}]);\n" .
            "  _paq.push(['setSiteId', {$id}]);\n" .
            "  var g = d.createElement('script'), s = d.getElementsByTagName('script')[0];\n" .
            "  g.async = true; g.src = {$script}; s.parentNode.insertBefore(g, s);\n" .
            "})(window, navigator, document);\n" .
            "</script>\n<!-- /SocialReport tracker -->\n";
    }

    /**
     * The path (from the root of the host, so it is the same origin as the page) of a script of this plugin.
     *
     * @param string $script s.php or h.php
     * @return string
     */
    public static function public_path(string $script): string {
        global $CFG;
        return rtrim((string) parse_url($CFG->wwwroot, PHP_URL_PATH), '/') . '/local/socialreport/' . $script;
    }

    /**
     * Whether this request is a script that is not a page (cli, ajax, web service).
     *
     * @return bool
     */
    private static function is_background_request(): bool {
        return (defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
            || (defined('WS_SERVER') && WS_SERVER);
    }

    /**
     * The HTML to add to the head of the page being built: empty when the visit is not to be measured.
     *
     * @param bool|null $background whether the request is not a page; null = find out (the tests pass false, because
     *                              PHPUnit itself runs as a cli script)
     * @return string
     */
    public static function html_for_current_page(?bool $background = null): string {
        global $PAGE;
        $config = self::config();
        if (!$config['enabled'] || !self::is_configured($config)) {
            return '';
        }
        $path = ($PAGE && $PAGE->has_set_url()) ? (string) $PAGE->url->get_path() : '';
        $background = $background ?? self::is_background_request();
        if (!self::should_inject($config, is_siteadmin(), $path, $background)) {
            return '';
        }
        return self::snippet(self::public_path('s.php'), self::public_path('h.php'), $config['siteid']);
    }
}
