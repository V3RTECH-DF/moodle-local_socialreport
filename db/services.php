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

/**
 * Web service functions of the SocialReport companion plugin.
 *
 * The plugin declares the "SocialReport (local_socialreport)" external service with them and NOTHING else: no core function is
 * added, so the token of the account reads counts and nothing more.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_socialreport_get_info' => [
        'classname' => 'local_socialreport\external\get_info',
        'description' => 'Version of the SocialReport companion plugin, and whether the Matomo tracker is configured.',
        'type' => 'read',
        'capabilities' => 'local/socialreport:viewcounts',
    ],
    'local_socialreport_get_counts' => [
        'classname' => 'local_socialreport\external\get_counts',
        'description' => 'Enrolments and completions per course and per day for three date ranges. Counts only, no person.',
        'type' => 'read',
        'capabilities' => 'local/socialreport:viewcounts',
    ],
];

$services = [
    'SocialReport (local_socialreport)' => [
        'shortname' => 'local_socialreport',
        'enabled' => 1,
        'restrictedusers' => 1,
        'uploadfiles' => 0,
        'downloadfiles' => 0,
        'functions' => [
            'local_socialreport_get_info',
            'local_socialreport_get_counts',
        ],
    ],
];
