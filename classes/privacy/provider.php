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

namespace local_socialreport\privacy;

/**
 * The plugin stores no personal data: it returns counts only. The Matomo tracker it can serve to the pages makes this Moodle pass
 * the visits of the visitors on to the Matomo of the organisation (a call to the outside), which is declared here; the Matomo is
 * not a store of this plugin and the plugin keeps nothing about the visitor.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider {
    /**
     * Declares the external location the visits go to.
     *
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(
        \core_privacy\local\metadata\collection $collection
    ): \core_privacy\local\metadata\collection {
        $collection->add_external_location_link('matomo', [
            'pageurl' => 'privacy:metadata:matomo:pageurl',
            'referrer' => 'privacy:metadata:matomo:referrer',
            'ipaddress' => 'privacy:metadata:matomo:ipaddress',
            'useragent' => 'privacy:metadata:matomo:useragent',
            'language' => 'privacy:metadata:matomo:language',
        ], 'privacy:metadata:matomo');
        return $collection;
    }

    /**
     * The plugin holds no data about a user.
     *
     * @param int $userid
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        return new \core_privacy\local\request\contextlist();
    }

    /**
     * Nothing to export: the plugin holds no data about a user.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
    }

    /**
     * Nothing to delete.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Nothing to delete.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
    }
}
