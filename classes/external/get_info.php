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

use local_socialreport\local\tracker;

/**
 * Version of the plugin and the state of the tracker, so that the SocialReport shows what is installed. No counts, no person.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_info extends base {
    /** The version of the answer of get_counts that this plugin speaks. The SocialReport refuses another one. */
    const API_VERSION = 1;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the version of the plugin and of Moodle, and the tracker settings.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG;
        self::require_viewcounts();
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_socialreport');
        $config = tracker::config();
        return [
            'component' => 'local_socialreport',
            'version' => (int) $plugin->versiondisk,
            'release' => (string) $plugin->release,
            'apiversion' => self::API_VERSION,
            'moodlerelease' => (string) $CFG->release,
            'timezone' => \core_date::get_server_timezone(),
            'tracker' => [
                'configured' => tracker::is_configured($config),
                'enabled' => $config['enabled'],
                'siteid' => $config['siteid'],
                'url' => $config['url'],
            ],
        ];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'component' => new external_value(PARAM_COMPONENT, 'Always local_socialreport'),
            'version' => new external_value(PARAM_INT, 'Version of the plugin (YYYYMMDDXX)'),
            'release' => new external_value(PARAM_TEXT, 'Release of the plugin'),
            'apiversion' => new external_value(PARAM_INT, 'Version of the answer of local_socialreport_get_counts'),
            'moodlerelease' => new external_value(PARAM_TEXT, 'Release of Moodle'),
            'timezone' => new external_value(PARAM_TEXT, 'Time zone of the site: the day of every date of the counts'),
            'tracker' => new external_single_structure([
                'configured' => new external_value(PARAM_BOOL, 'The address of the Matomo and the site id are set'),
                'enabled' => new external_value(PARAM_BOOL, 'The tracker is on'),
                'siteid' => new external_value(PARAM_INT, 'Id of the site in the Matomo (0 when not set)'),
                'url' => new external_value(PARAM_RAW, 'Address of the Matomo (empty when not set); it is not a secret'),
            ]),
        ]);
    }
}
