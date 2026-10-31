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

use local_socialreport\local\counts;

/**
 * Enrolments and completions per course and per day, for three date ranges. The answer is a fixed structure of whole numbers,
 * course short names and dates: no person, no id of a person, no row per person, and the structure has nowhere to put one.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_counts extends base {
    /**
     * Parameters: the three ranges (Y-m-d, both ends included) and the cutoff day of the cohort.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        $date = function (string $what): external_value {
            return new external_value(PARAM_RAW_TRIMMED, $what . ' (YYYY-MM-DD, in the time zone of the site)');
        };
        return new external_function_parameters([
            'current_start' => $date('First day of the current range'),
            'current_end' => $date('Last day of the current range'),
            'previous_start' => $date('First day of the previous range'),
            'previous_end' => $date('Last day of the previous range'),
            'lastyear_start' => $date('First day of the same range one year before'),
            'lastyear_end' => $date('Last day of the same range one year before'),
            'cohort_cutoff' => $date('Enrolments made until this day form the cohort whose completion is read'),
        ]);
    }

    /**
     * Counts.
     *
     * @param string $currentstart
     * @param string $currentend
     * @param string $previousstart
     * @param string $previousend
     * @param string $lastyearstart
     * @param string $lastyearend
     * @param string $cohortcutoff
     * @return array
     */
    public static function execute(
        string $currentstart,
        string $currentend,
        string $previousstart,
        string $previousend,
        string $lastyearstart,
        string $lastyearend,
        string $cohortcutoff
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'current_start' => $currentstart, 'current_end' => $currentend,
            'previous_start' => $previousstart, 'previous_end' => $previousend,
            'lastyear_start' => $lastyearstart, 'lastyear_end' => $lastyearend,
            'cohort_cutoff' => $cohortcutoff,
        ]);
        self::require_viewcounts();
        $tz = \core_date::get_server_timezone_object();
        $result = counts::compute([
            'current' => [$params['current_start'], $params['current_end']],
            'previous' => [$params['previous_start'], $params['previous_end']],
            'lastyear' => [$params['lastyear_start'], $params['lastyear_end']],
        ], $params['cohort_cutoff'], $tz, time());
        $result['apiversion'] = get_info::API_VERSION;
        $result['timezone'] = \core_date::get_server_timezone();
        return $result;
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $int = function (string $what): external_value {
            return new external_value(PARAM_INT, $what);
        };
        $pair = function () use ($int): external_single_structure {
            return new external_single_structure(['enrolments' => $int('Enrolments made in the range'),
                'completions' => $int('Course completions in the range')]);
        };
        return new external_single_structure([
            'apiversion' => $int('Version of this answer'),
            'timezone' => new external_value(PARAM_TEXT, 'Time zone of the site: the day of every date'),
            'courses' => new external_multiple_structure(new external_single_structure([
                'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                'firstenrolment' => new external_value(PARAM_RAW, 'Day of the first enrolment (YYYY-MM-DD), empty when none'),
                'enrolments' => $int('Enrolments, ever'),
                'norecord' => $int('Enrolments with no completion record at all (completion never calculated)'),
                'completions' => $int('Enrolments whose course completion is done, ever'),
                'active' => $int('Active enrolments not completed'),
                'cohort' => $int('Enrolments of the cohort with a completion record'),
                'cohortcompleted' => $int('Enrolments of the cohort that completed the course'),
                'current' => $pair(),
                'previous' => $pair(),
                'lastyear' => $pair(),
            ])),
            'daily' => new external_multiple_structure(new external_single_structure([
                'date' => new external_value(PARAM_RAW, 'Day (YYYY-MM-DD)'),
                'enrolments' => $int('Enrolments made that day, in every course'),
                'completions' => $int('Course completions that day, in every course'),
            ])),
        ]);
    }
}
