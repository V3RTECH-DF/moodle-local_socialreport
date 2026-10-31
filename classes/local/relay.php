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
 * The tracker served by the Moodle itself: the browser of the visitor talks only to the Moodle (two small scripts,
 * s.php and h.php), and it is the SERVER of the Moodle that fetches the tracker from the Matomo and passes the visits on, so
 * that blockers of advertisements and the protections of the browsers, which stop by address (the Matomo host, matomo.js,
 * matomo.php), do not hide the visits. The same rule as the V3RAnalytics plugin for WordPress.
 *
 * It is NOT an open proxy:
 *  - the destination is always the Matomo address of the tracker code saved on the plugin page, never anything the request says
 *    (no address, no site id, no path comes from the request);
 *  - only a list of parameters that the standard Matomo tracker sends is passed on; the rest is dropped, and the site id is forced;
 *  - the size of the request, the visits in a bulk request, the size of the tracker and the time of each call are limited;
 *  - no redirect is followed, and the answer of the Matomo is never copied to the browser (the browser gets a fixed empty answer);
 *  - no cookie goes either way: none of the browser is passed on, none of the Matomo reaches the browser, and the scripts set none.
 *
 * Privacy: a visitor whose browser sends Do Not Track or Global Privacy Control is dropped on the server too.
 *
 * The IP of the visitor: the Matomo would see only the IP of the Moodle server. It is passed on in the headers X-Forwarded-For
 * and User-Agent. For the Matomo to trust it, the optional write token (see tracker::token()) adds "cip", which only a token
 * allows; the token stays on the server and is never written in a page or script.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class relay {
    /** Largest body accepted from the browser, in bytes. */
    const MAX_BODY = 65536;
    /** Largest query string accepted from the browser, in bytes. */
    const MAX_QUERY = 8192;
    /** Most visits passed on from one bulk request. */
    const MAX_BULK = 50;
    /** Largest tracker accepted from the Matomo, in bytes (the real one is below 200 KB). */
    const MAX_SCRIPT = 524288;
    /** Smallest size of something that can be the tracker. */
    const MIN_SCRIPT = 5000;
    /** Seconds a copy of the tracker is used without asking the Matomo again. */
    const SCRIPT_TTL = 43200;
    /** Seconds to wait before asking the Matomo again after a failure. */
    const RETRY_AFTER = 60;
    /** Seconds allowed to fetch the tracker. */
    const TIMEOUT_SCRIPT = 8;
    /** Seconds allowed to pass a visit on. */
    const TIMEOUT_HIT = 4;
    /** Seconds the browser keeps the tracker. */
    const BROWSER_TTL = 86400;

    /**
     * Whitelist: only what the standard JavaScript tracker sends. Whatever needs authority (cip, cdt, cdo, cid, uid, country,
     * region, city, coordinates, bot, recMode, new_visit...) is left out on purpose. Anchored regular expressions.
     */
    const ALLOWED_PARAMS = [
        '/^(url|urlref|action_name|_id|_idn|_refts|_viewts|_ects|_rcn|_rck|_ref|_cvar|cvar|r|h|m|s|res|cookie|send_image)$/',
        '/^(ca|gt_ms|qt|pv_id|uadata)$/',
        '/^pf_[a-z0-9]+$/',
        '/^(e_c|e_a|e_n|e_v|e_ts)$/',
        '/^(c_n|c_p|c_t|c_i)$/',
        '/^(ping|link|download|search|search_cat|search_count|idgoal|revenue)$/',
        '/^ec_(id|items|st|tx|sh|dt)$/',
        '/^dimension[0-9]{1,3}$/',
        '/^(pdf|realp|wma|fla|java|ag|gears|dir|cd)$/',
    ];

    /** The 1x1 transparent GIF, base64. */
    const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Whether a parameter name may be passed on.
     *
     * @param string $key
     * @return bool
     */
    public static function is_allowed_param(string $key): bool {
        foreach (self::ALLOWED_PARAMS as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the browser of the visitor asked not to be measured (Global Privacy Control or Do Not Track).
     *
     * @param array $server the server variables
     * @return bool
     */
    public static function visitor_opted_out(array $server): bool {
        return trim((string) ($server['HTTP_SEC_GPC'] ?? '')) === '1' || trim((string) ($server['HTTP_DNT'] ?? '')) === '1';
    }

    /**
     * Keeps from the parameters only what is allowed, forces the site id, and puts back what only the server may say.
     *
     * @param array $params what the browser sent
     * @param int $siteid the site id configured on the plugin page
     * @param array $serverside cip, ua, lang (empty ones are skipped)
     * @return array|null null when nothing of a visit is left
     */
    public static function sanitize_params(array $params, int $siteid, array $serverside): ?array {
        $clean = [];
        foreach ($params as $key => $value) {
            $key = (string) $key;
            if (is_array($value) || !self::is_allowed_param($key)) {
                continue;
            }
            $clean[$key] = (string) $value;
        }
        if ($clean === []) {
            return null;
        }
        $clean['idsite'] = (string) $siteid;
        $clean['rec'] = '1';
        foreach (['cip', 'ua', 'lang'] as $k) {
            if ((string) ($serverside[$k] ?? '') !== '') {
                $clean[$k] = (string) $serverside[$k];
            }
        }
        return $clean;
    }

    /**
     * Builds what is sent to the Matomo, or null when there is no valid visit.
     *
     * @param array $query the parameters of the URL
     * @param string $body the raw body: a form, or JSON in bulk ({"requests": ["?..."]})
     * @param int $siteid the site id configured on the plugin page
     * @param string $token the optional write token (empty = none)
     * @param string $ip the IP of the visitor
     * @param string $ua the User-Agent of the visitor
     * @param string $lang the Accept-Language of the visitor
     * @return array|null ['content_type' => string, 'body' => string]
     */
    public static function build_payload(
        array $query,
        string $body,
        int $siteid,
        string $token,
        string $ip,
        string $ua,
        string $lang
    ): ?array {
        // Without a token the Matomo ignores cip, ua and lang as parameters: the headers carry them (see headers()).
        $serverside = $token !== '' ? ['cip' => $ip, 'ua' => $ua, 'lang' => $lang] : [];
        $trim = ltrim($body);
        $bulk = null;
        if ($trim !== '' && $trim[0] === '{') {
            $decoded = json_decode($trim, true);
            if (is_array($decoded) && isset($decoded['requests']) && is_array($decoded['requests'])) {
                $bulk = $decoded['requests'];
            }
        }
        if ($bulk !== null) {
            $out = [];
            foreach (array_slice($bulk, 0, self::MAX_BULK) as $entry) {
                if (!is_string($entry)) {
                    continue;
                }
                parse_str(ltrim($entry, '?'), $params);
                $clean = self::sanitize_params(is_array($params) ? $params : [], $siteid, $serverside);
                if ($clean !== null) {
                    $out[] = '?' . http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
                }
            }
            if ($out === []) {
                return null;
            }
            $data = ['requests' => $out];
            if ($token !== '') {
                $data['token_auth'] = $token;
            }
            return ['content_type' => 'application/json', 'body' => json_encode($data)];
        }
        $params = $query;
        if ($trim !== '') {
            parse_str($trim, $frombody);
            if (is_array($frombody)) {
                $params = array_merge($params, $frombody);
            }
        }
        $clean = self::sanitize_params($params, $siteid, $serverside);
        if ($clean === null) {
            return null;
        }
        if ($token !== '') {
            $clean['token_auth'] = $token;
        }
        $clean['send_image'] = '0';
        return [
            'content_type' => 'application/x-www-form-urlencoded',
            'body' => http_build_query($clean, '', '&', PHP_QUERY_RFC3986),
        ];
    }

    /**
     * The headers of the call to the Matomo: only these, never a cookie of the browser nor of the Moodle.
     *
     * @param string $contenttype
     * @param string $ip the IP of the visitor
     * @param string $ua
     * @param string $lang
     * @return array list of "Name: value"
     */
    public static function headers(string $contenttype, string $ip, string $ua, string $lang): array {
        $headers = ['Content-Type: ' . $contenttype];
        // A header value cannot hold a line break (header injection through a crafted User-Agent).
        $clean = function (string $v): string {
            return trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $v));
        };
        if ($ua !== '') {
            $headers[] = 'User-Agent: ' . $clean($ua);
        }
        if ($lang !== '') {
            $headers[] = 'Accept-Language: ' . $clean($lang);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $headers[] = 'X-Forwarded-For: ' . $ip;
        }
        return $headers;
    }

    /**
     * Whether a body can be the Matomo tracker (and not an error page saved by mistake).
     *
     * @param string $body
     * @return bool
     */
    public static function looks_like_tracker(string $body): bool {
        $size = strlen($body);
        return $size >= self::MIN_SCRIPT && $size <= self::MAX_SCRIPT && stripos($body, '<html') === false
            && stripos($body, 'matomo') !== false;
    }

    /**
     * The only place that talks to the outside. Moodle's own curl class: it applies the blocked hosts and allowed ports of the
     * site, the proxy of the site, and sends no cookie (cookie support off). No redirect is followed.
     *
     * @param string $method GET or POST
     * @param string $url
     * @param array $headers list of "Name: value"
     * @param string $body
     * @param int $timeout seconds
     * @param int $maxbytes largest answer accepted
     * @return array ['status' => int, 'body' => string]
     */
    public static function transport(
        string $method,
        string $url,
        array $headers,
        string $body,
        int $timeout,
        int $maxbytes
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['cache' => false, 'cookie' => false]);
        $curl->setHeader($headers);
        $options = [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => $timeout,
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_MAXFILESIZE' => $maxbytes,
        ];
        $answer = $method === 'POST' ? $curl->post($url, $body, $options) : $curl->get($url, [], $options);
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);
        if ($curl->get_errno() !== 0 || !is_string($answer) || strlen($answer) > $maxbytes) {
            return ['status' => 0, 'body' => ''];
        }
        return ['status' => $status, 'body' => $answer];
    }

    /**
     * The tracker, fetched from the Matomo of the plugin page and kept in the application cache for a while. When the Matomo does
     * not answer, the old copy is served (measuring with an old tracker is better than not measuring); with no copy, null.
     *
     * @param array $config tracker::config()
     * @param callable|null $transport replaces transport() in the tests
     * @return string|null
     */
    public static function get_script(array $config, ?callable $transport = null): ?string {
        $transport = $transport ?? [self::class, 'transport'];
        $source = rtrim($config['url'], '/') . '/matomo.js';
        $cache = \cache::make('local_socialreport', 'script');
        $stored = $cache->get('script');
        $stored = (is_array($stored) && ($stored['source'] ?? '') === $source && isset($stored['body'], $stored['fetchedat']))
            ? $stored : null;
        if ($stored !== null && time() - (int) $stored['fetchedat'] < self::SCRIPT_TTL) {
            return $stored['body'];
        }
        $failedat = $cache->get('failedat');
        if ($failedat && time() - (int) $failedat < self::RETRY_AFTER) {
            return $stored['body'] ?? null;
        }
        $answer = $transport('GET', $source, ['Accept: */*'], '', self::TIMEOUT_SCRIPT, self::MAX_SCRIPT);
        if ($answer['status'] === 200 && self::looks_like_tracker($answer['body'])) {
            $cache->set('script', ['body' => $answer['body'], 'source' => $source, 'fetchedat' => time()]);
            $cache->delete('failedat');
            return $answer['body'];
        }
        $cache->set('failedat', time());
        return $stored['body'] ?? null;
    }

    /**
     * Passes a visit on to the Matomo of the plugin page.
     *
     * @param array $config tracker::config()
     * @param string $token the optional write token
     * @param string $method GET or POST (the method of the browser)
     * @param string $querystring the raw query string of the browser
     * @param string $body the raw body of the browser
     * @param array $server the server variables (User-Agent, Accept-Language, Sec-GPC, DNT)
     * @param string $ip the IP of the visitor
     * @param callable|null $transport replaces transport() in the tests
     * @return string 'passed', 'dropped' (nothing of a visit, or the visitor asked not to be measured) or 'failed'
     */
    public static function forward_hit(
        array $config,
        string $token,
        string $method,
        string $querystring,
        string $body,
        array $server,
        string $ip,
        ?callable $transport = null
    ): string {
        $transport = $transport ?? [self::class, 'transport'];
        if (self::visitor_opted_out($server)) {
            return 'dropped';
        }
        parse_str($querystring, $query);
        $ua = (string) ($server['HTTP_USER_AGENT'] ?? '');
        $lang = (string) ($server['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $payload = self::build_payload(is_array($query) ? $query : [], $body, $config['siteid'], $token, $ip, $ua, $lang);
        if ($payload === null) {
            return 'dropped';
        }
        $headers = self::headers($payload['content_type'], $ip, $ua, $lang);
        $destination = rtrim($config['url'], '/') . '/matomo.php';
        $answer = $transport('POST', $destination, $headers, $payload['body'], self::TIMEOUT_HIT, 4096);
        return ($answer['status'] >= 200 && $answer['status'] < 300) ? 'passed' : 'failed';
    }

    /**
     * Whether the tracker is on and configured (the scripts answer nothing otherwise).
     *
     * @return array|null the configuration, or null when the scripts must answer "not here"
     */
    private static function active_config(): ?array {
        $config = tracker::config();
        return ($config['enabled'] && tracker::is_configured($config)) ? $config : null;
    }

    /**
     * Sends a fixed answer and ends. Never copies anything from the Matomo. No cookie is set: the scripts run without a session
     * (NO_MOODLE_COOKIES).
     *
     * @param int $status
     * @param string $type
     * @param array $headers extra headers
     * @param string $body
     */
    private static function send(int $status, string $type, array $headers, string $body): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $type);
            header('X-Content-Type-Options: nosniff');
            foreach ($headers as $line) {
                header($line);
            }
        }
        echo $body;
        exit;
    }

    /**
     * s.php: serves the tracker.
     */
    public static function serve_script(): void {
        $config = self::active_config();
        $body = $config === null ? null : self::get_script($config);
        if ($body === null) {
            $type = 'application/javascript; charset=utf-8';
            self::send($config === null ? 404 : 503, $type, ['Cache-Control: no-store'], '/* none */');
        }
        $etag = '"' . md5($body) . '"';
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            self::send(304, 'application/javascript; charset=utf-8', ['ETag: ' . $etag], '');
        }
        self::send(200, 'application/javascript; charset=utf-8', [
            'Cache-Control: public, max-age=' . self::BROWSER_TTL,
            'ETag: ' . $etag,
        ], $body);
    }

    /**
     * h.php: receives a visit and passes it on. The answer to the browser is fixed and empty whatever happens (a failure of the
     * Matomo is not an error for the visitor).
     */
    public static function serve_hit(): void {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $wantsgif = $method === 'GET' && ($_GET['send_image'] ?? '1') !== '0';
        $empty = function () use ($wantsgif): void {
            if ($wantsgif) {
                self::send(200, 'image/gif', ['Cache-Control: no-store'], base64_decode(self::GIF));
            }
            self::send(204, 'text/plain; charset=utf-8', ['Cache-Control: no-store'], '');
        };
        $config = self::active_config();
        if ($config === null || !in_array($method, ['GET', 'POST'])) {
            $empty();
        }
        $querystring = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if (strlen($querystring) > self::MAX_QUERY) {
            self::send(414, 'text/plain; charset=utf-8', ['Cache-Control: no-store'], '');
        }
        $body = '';
        if ($method === 'POST') {
            $body = (string) stream_get_contents(fopen('php://input', 'r'), self::MAX_BODY + 1);
            if (strlen($body) > self::MAX_BODY) {
                self::send(413, 'text/plain; charset=utf-8', ['Cache-Control: no-store'], '');
            }
        }
        // The browser must not wait for the Matomo: the answer is the same either way, and a failure here is only a lost visit.
        self::forward_hit($config, tracker::token(), $method, $querystring, $body, $_SERVER, getremoteaddr());
        $empty();
    }
}
