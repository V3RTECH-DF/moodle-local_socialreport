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
 * The Moodle side of the SocialReport connection, set up by the plugin: the system role "SocialReport" (created on install and
 * kept on every upgrade), and, only when a site administrator asks for it on the plugin page, the integration account, its
 * role assignment, its authorisation in the "SocialReport" external service and its token.
 *
 * The external service itself is declared in db/services.php (Moodle creates it, enabled, with the two functions of the plugin
 * and nothing else, and removes it with its tokens when the plugin is uninstalled).
 *
 * Nothing about a person is kept: the plugin remembers only the ids of the role and of the account it created.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provisioning {
    /** Short name of the external service (db/services.php). */
    const SERVICE = 'local_socialreport';
    /** Name of the external service (the key of $services in db/services.php). */
    const SERVICE_NAME = 'SocialReport (local_socialreport)';
    /** Short name of the system role the plugin creates. */
    const ROLE = 'local_socialreport';
    /** User name of the integration account the plugin creates. */
    const USERNAME = 'local_socialreport';
    /** Fictitious address of the account: it never receives e-mail (emailstop), and .invalid never resolves (RFC 2606). */
    const EMAIL = 'local_socialreport@example.invalid';

    /**
     * The least the two functions of the service need, at system level:
     *
     * - webservice/rest:use: the REST protocol of the key.
     * - local/socialreport:viewcounts: local_socialreport_get_counts and local_socialreport_get_info.
     *
     * NOT here, on purpose: moodle/backup:userinfo, anything of the report builder, any read of a course, of a user or of a
     * grade. The role can read counts and nothing else.
     */
    const CAPABILITIES = [
        'webservice/rest:use',
        'local/socialreport:viewcounts',
    ];

    /**
     * The capabilities of the role.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return self::CAPABILITIES;
    }

    /**
     * Creates the role if it is not there (or was deleted), and gives it exactly capabilities(), at system level only.
     * Called on install, on upgrade and on every key generated. A role with the same short name that the plugin did not create
     * is left untouched.
     *
     * @return int the role id, or 0 when another role holds the short name
     */
    public static function ensure_role(): int {
        global $DB;
        // The capabilities of this plugin must exist before they are given: install.php and upgrade.php run before Moodle
        // reads db/access.php.
        update_capabilities('local_socialreport');
        $roleid = (int) get_config('local_socialreport', 'roleid');
        $role = $roleid ? $DB->get_record('role', ['id' => $roleid]) : false;
        if (!$role) {
            if ($DB->record_exists('role', ['shortname' => self::ROLE])) {
                return 0;
            }
            $roleid = create_role(
                get_string('rolename', 'local_socialreport'),
                self::ROLE,
                get_string('roledescription', 'local_socialreport')
            );
            set_config('roleid', $roleid, 'local_socialreport');
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $system = \context_system::instance();
        $where = 'roleid = ? AND contextid = ?';
        $given = $DB->get_fieldset_select('role_capabilities', 'capability', $where, [$roleid, $system->id]);
        $capabilities = self::capabilities();
        foreach (array_diff($given, $capabilities) as $capability) {
            unassign_capability($capability, $roleid, $system->id);
        }
        foreach ($capabilities as $capability) {
            if (get_capability_info($capability)) {
                assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
            }
        }
        $system->mark_dirty();
        return $roleid;
    }

    /**
     * The external service of the plugin, as Moodle created it from db/services.php.
     *
     * @return \stdClass|false
     */
    public static function service() {
        global $DB;
        return $DB->get_record('external_services', ['shortname' => self::SERVICE, 'component' => 'local_socialreport']);
    }

    /**
     * Frees the name of the plugin service before Moodle reads db/services.php: a service with that name that is not the
     * plugin's (made by hand) gets another name, because external_services.name is unique and the install would fail.
     */
    public static function prepare_service(): void {
        global $DB;
        $select = 'name = ? AND (component IS NULL OR component <> ?)';
        foreach ($DB->get_records_select('external_services', $select, [self::SERVICE_NAME, 'local_socialreport']) as $other) {
            $candidate = self::SERVICE_NAME . ' (manual)';
            for ($i = 2; $DB->record_exists('external_services', ['name' => $candidate]); $i++) {
                $candidate = self::SERVICE_NAME . ' (manual) ' . $i;
            }
            $DB->set_field('external_services', 'name', $candidate, ['id' => $other->id]);
        }
    }

    /**
     * The service of the plugin, created again from db/services.php when it is missing (an update that stopped half way).
     *
     * @return \stdClass|false
     */
    public static function ensure_service() {
        global $CFG;
        $service = self::service();
        if (!$service) {
            require_once($CFG->libdir . '/upgradelib.php');
            self::prepare_service();
            external_update_descriptions('local_socialreport');
            $service = self::service();
        }
        return $service;
    }

    /**
     * The integration account the plugin created, if it still exists.
     *
     * @return \stdClass|false
     */
    public static function account() {
        global $DB;
        $userid = (int) get_config('local_socialreport', 'accountid');
        return $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]) : false;
    }

    /**
     * What is in place, for the plugin page.
     *
     * @return array
     */
    public static function status(): array {
        global $CFG, $DB;
        $service = self::service();
        $account = self::account();
        $protocols = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
        $roleid = (int) get_config('local_socialreport', 'roleid');
        $tokens = ($service && $account)
            ? $DB->count_records('external_tokens', ['userid' => $account->id, 'externalserviceid' => $service->id]) : 0;
        return [
            'webservices' => !empty($CFG->enablewebservices),
            'rest' => in_array('rest', $protocols),
            'auth' => is_enabled_auth('webservice'),
            'service' => $service ? (bool) $service->enabled : null,
            'role' => $roleid > 0 && $DB->record_exists('role', ['id' => $roleid]),
            'account' => $account ? $account->username : null,
            'tokens' => $tokens,
        ];
    }

    /**
     * Turns on the web service authentication (auth_webservice), the one of the account, when the account exists. From Moodle
     * 5.3 on, the web service server refuses the token of an account whose authentication method is disabled.
     *
     * @return bool whether the account exists and its authentication is now on
     */
    public static function enable_authentication(): bool {
        if (!self::account()) {
            return false;
        }
        if (!is_enabled_auth('webservice')) {
            \core\plugininfo\auth::enable_plugin('webservice', 1);
        }
        return is_enabled_auth('webservice');
    }

    /**
     * Everything the connection needs, on the click of a site administrator: web services and REST on, the account (created or
     * the one the plugin created before) and its authentication method on, the role at system level, the authorisation in the
     * service, and a new token. The tokens the account had for the service stop working.
     *
     * @return string the token
     */
    public static function generate_key(): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_capability('moodle/site:config', \context_system::instance());
        $service = self::ensure_service();
        if (!$service) {
            throw new \moodle_exception('error:noservice', 'local_socialreport');
        }
        $roleid = self::ensure_role();
        if (!$roleid) {
            throw new \moodle_exception('error:rolenameinuse', 'local_socialreport', '', self::ROLE);
        }

        set_config('enablewebservices', 1);
        $protocols = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
        if (!in_array('rest', $protocols)) {
            $protocols[] = 'rest';
            set_config('webserviceprotocols', implode(',', $protocols));
        }
        if (!$service->enabled) {
            $DB->set_field('external_services', 'enabled', 1, ['id' => $service->id]);
        }

        $account = self::account();
        if (!$account) {
            $taken = ['username' => self::USERNAME, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0];
            if ($DB->record_exists('user', $taken)) {
                throw new \moodle_exception('error:usernameinuse', 'local_socialreport', '', self::USERNAME);
            }
            $user = (object) [
                'username' => self::USERNAME,
                'auth' => 'webservice',
                'firstname' => get_string('accountfirstname', 'local_socialreport'),
                'lastname' => get_string('accountlastname', 'local_socialreport'),
                'email' => self::EMAIL,
                'emailstop' => 1,
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'description' => get_string('accountdescription', 'local_socialreport'),
            ];
            // Moodle 5.3 deprecated user_create_user() in favour of \core\user::create_user().
            $userid = method_exists(\core\user::class, 'create_user')
                ? \core\user::create_user($user, false, false)
                : user_create_user($user, false, false);
            set_config('accountid', $userid, 'local_socialreport');
            $account = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        } else if ($account->suspended) {
            $DB->set_field('user', 'suspended', 0, ['id' => $account->id]);
        }

        self::enable_authentication();

        $system = \context_system::instance();
        role_assign($roleid, $account->id, $system->id);
        if (!$DB->record_exists('external_services_users', ['externalserviceid' => $service->id, 'userid' => $account->id])) {
            $DB->insert_record('external_services_users', (object) [
                'externalserviceid' => $service->id,
                'userid' => $account->id,
                'iprestriction' => null,
                'validuntil' => null,
                'timecreated' => time(),
            ]);
        }
        $DB->delete_records('external_tokens', ['userid' => $account->id, 'externalserviceid' => $service->id]);
        if (class_exists(\core_external\util::class)) {
            return \core_external\util::generate_token(
                EXTERNAL_TOKEN_PERMANENT,
                $service,
                $account->id,
                $system,
                0,
                '',
                get_string('pluginname', 'local_socialreport')
            );
        }
        require_once($CFG->libdir . '/externallib.php');
        return external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $account->id, $system);
    }

    /**
     * The code to paste in the SocialReport: site address and token, in one piece.
     *
     * @param string $token
     * @return string
     */
    public static function connection_code(string $token): string {
        global $CFG;
        return codes::encode_connection($CFG->wwwroot, $token);
    }

    /**
     * Every token of the account for the service stops working (the account and its role stay, for a new key later).
     *
     * @return int how many tokens were removed
     */
    public static function revoke_key(): int {
        global $DB;
        require_capability('moodle/site:config', \context_system::instance());
        $service = self::service();
        $account = self::account();
        if (!$service || !$account) {
            return 0;
        }
        $count = $DB->count_records('external_tokens', ['userid' => $account->id, 'externalserviceid' => $service->id]);
        $DB->delete_records('external_tokens', ['userid' => $account->id, 'externalserviceid' => $service->id]);
        return $count;
    }

    /**
     * Deletes the account the plugin created (its tokens, its role assignment and its authorisation go with it).
     *
     * @return bool whether there was an account to delete
     */
    public static function remove_account(): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_capability('moodle/site:config', \context_system::instance());
        $account = self::account();
        unset_config('accountid', 'local_socialreport');
        if (!$account) {
            return false;
        }
        $DB->delete_records('external_tokens', ['userid' => $account->id]);
        $DB->delete_records('external_services_users', ['userid' => $account->id]);
        delete_user($account);
        return true;
    }

    /**
     * Uninstalling the plugin: the account, the role and the tracker settings go too (Moodle removes the service and its tokens).
     * Runs from the uninstaller, where the administrator is already checked.
     */
    public static function uninstall(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $account = self::account();
        unset_config('accountid', 'local_socialreport');
        if ($account) {
            $DB->delete_records('external_tokens', ['userid' => $account->id]);
            $DB->delete_records('external_services_users', ['userid' => $account->id]);
            delete_user($account);
        }
        $roleid = (int) get_config('local_socialreport', 'roleid');
        if ($roleid && $DB->record_exists('role', ['id' => $roleid])) {
            delete_role($roleid);
        }
        unset_config('roleid', 'local_socialreport');
        tracker::clear();
    }
}
