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
 * The counts the SocialReport reads: enrolments and completions per course and per day. It is the SocialReport's monthly
 * course chapter, summed inside the Moodle database, so NOTHING about a person leaves: no id, no name, no e-mail, no row per
 * person. What this class returns is a fixed structure of whole numbers, course short names and dates (see compute()).
 *
 * The unit is the ENROLMENT (a row of user_enrolments), as in the custom report the SocialReport used to read: a person
 * enrolled twice in a course by two methods counts twice. People whose account is deleted do not count. The completion of an
 * enrolment is the course completion of the same person in the same course (course_completions): no record at all means the
 * completion was never calculated for it ("no record"), which is not the same as "not completed". The day of a date is the day
 * in the time zone of the site.
 *
 * Only standard SQL that runs the same on PostgreSQL, MariaDB and MySQL, with named parameters that are never reused.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class counts {
    /** Longest range accepted, in days. */
    const MAX_DAYS = 400;
    /** The three ranges the function takes, in the order they are answered. */
    const RANGES = ['current', 'previous', 'lastyear'];

    /**
     * A date as Y-m-d, or an exception.
     *
     * @param string $date
     * @return string
     */
    public static function check_date(string $date): string {
        $valid = preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $date, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            && (int) $m[1] >= 2000 && (int) $m[1] <= 2100;
        if (!$valid) {
            throw new \invalid_parameter_exception('Invalid date, expected YYYY-MM-DD: ' . s($date));
        }
        return $date;
    }

    /**
     * The first second of a day and the first second of the day after, in the given time zone.
     *
     * @param string $start first day (Y-m-d)
     * @param string $end last day, included (Y-m-d)
     * @param \DateTimeZone $tz
     * @return int[] [from (included), until (excluded)]
     */
    public static function bounds(string $start, string $end, \DateTimeZone $tz): array {
        $from = (new \DateTimeImmutable($start . ' 00:00:00', $tz))->getTimestamp();
        $until = (new \DateTimeImmutable($end . ' 00:00:00', $tz))->modify('+1 day')->getTimestamp();
        return [$from, $until];
    }

    /**
     * Every range checked: dates valid, start not after the end, no more than MAX_DAYS.
     *
     * @param array $ranges name => [start, end]
     * @return array the same, with the dates checked
     */
    public static function check_ranges(array $ranges): array {
        foreach (self::RANGES as $name) {
            [$start, $end] = $ranges[$name] ?? ['', ''];
            self::check_date((string) $start);
            self::check_date((string) $end);
            $days = (strtotime($end . ' UTC') - strtotime($start . ' UTC')) / DAYSECS + 1;
            if ($days < 1 || $days > self::MAX_DAYS) {
                throw new \invalid_parameter_exception('Invalid range ' . $name . ': from 1 to ' . self::MAX_DAYS . ' days.');
            }
        }
        return $ranges;
    }

    /**
     * Counts everything.
     *
     * @param array $ranges 'current', 'previous' and 'lastyear' => [Y-m-d start, Y-m-d end (included)]
     * @param string $cutoff Y-m-d: the enrolments made until this day (included) form the cohort whose completion is read
     * @param \DateTimeZone $tz time zone of the site
     * @param int $now the current time, to tell an active enrolment from one that ended
     * @return array ['courses' => [...], 'daily' => [...]]
     */
    public static function compute(array $ranges, string $cutoff, \DateTimeZone $tz, int $now): array {
        global $DB;
        $ranges = self::check_ranges($ranges);
        self::check_date($cutoff);
        $cutoffts = (new \DateTimeImmutable($cutoff . ' 00:00:00', $tz))->modify('+1 day')->getTimestamp();
        $bounds = [];
        foreach (self::RANGES as $name) {
            $bounds[$name] = self::bounds($ranges[$name][0], $ranges[$name][1], $tz);
        }

        $params = [];
        $n = 0;
        $p = function ($value) use (&$params, &$n): string {
            $name = 'p' . (++$n);
            $params[$name] = $value;
            return ':' . $name;
        };
        $valid = 'u.id IS NOT NULL';
        $open = '(cc.timecompleted IS NULL OR cc.timecompleted = 0)';
        $done = 'cc.timecompleted > 0';
        $sum = function (string $condition): string {
            return 'SUM(CASE WHEN ' . $condition . ' THEN 1 ELSE 0 END)';
        };
        $columns = [
            'COUNT(u.id) AS enrolments',
            $sum("$valid AND cc.id IS NULL") . ' AS norecord',
            $sum("$valid AND $done") . ' AS completions',
            $sum("$valid AND e.status = 0 AND ue.status = 0 AND ue.timestart <= " . $p($now) .
                ' AND (ue.timeend = 0 OR ue.timeend > ' . $p($now) . ") AND $open") . ' AS active',
            $sum("$valid AND cc.id IS NOT NULL AND ue.timecreated < " . $p($cutoffts)) . ' AS cohort',
            $sum("$valid AND $done AND ue.timecreated < " . $p($cutoffts)) . ' AS cohortcompleted',
            'MIN(CASE WHEN ' . $valid . ' THEN ue.timecreated END) AS firstts',
        ];
        foreach (self::RANGES as $name) {
            [$from, $until] = $bounds[$name];
            $columns[] = $sum("$valid AND ue.timecreated >= " . $p($from) . ' AND ue.timecreated < ' . $p($until)) .
                ' AS enr_' . $name;
            $columns[] = $sum("$valid AND $done AND cc.timecompleted >= " . $p($from) . ' AND cc.timecompleted < ' . $p($until)) .
                ' AS com_' . $name;
        }
        $sql = 'SELECT c.id AS id, c.shortname AS shortname, ' . implode(', ', $columns) . '
                  FROM {course} c
             LEFT JOIN {enrol} e ON e.courseid = c.id
             LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.timecreated > 0
             LEFT JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
                 WHERE c.id <> ' . $p((int) SITEID) . '
              GROUP BY c.id, c.shortname
              ORDER BY c.id';
        $courses = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $item = [
                'shortname' => (string) $row->shortname,
                'firstenrolment' => $row->firstts ? (new \DateTimeImmutable('@' . (int) $row->firstts))
                    ->setTimezone($tz)->format('Y-m-d') : '',
                'enrolments' => (int) $row->enrolments,
                'norecord' => (int) $row->norecord,
                'completions' => (int) $row->completions,
                'active' => (int) $row->active,
                'cohort' => (int) $row->cohort,
                'cohortcompleted' => (int) $row->cohortcompleted,
            ];
            foreach (self::RANGES as $name) {
                $item[$name] = ['enrolments' => (int) $row->{'enr_' . $name}, 'completions' => (int) $row->{'com_' . $name}];
            }
            $courses[] = $item;
        }

        return ['courses' => $courses, 'daily' => self::daily($bounds['current'], $tz)];
    }

    /**
     * The enrolments and completions of each day of the current range, summed over every course. Only the days with something
     * are listed. The times are read inside Moodle and thrown away after the day is counted.
     *
     * @param int[] $bounds [from, until]
     * @param \DateTimeZone $tz
     * @return array list of ['date' => Y-m-d, 'enrolments' => int, 'completions' => int], by date
     */
    private static function daily(array $bounds, \DateTimeZone $tz): array {
        global $DB;
        [$from, $until] = $bounds;
        $days = [];
        $bucket = function (int $time, string $field) use (&$days, $tz): void {
            $day = (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->format('Y-m-d');
            $days[$day] = $days[$day] ?? ['date' => $day, 'enrolments' => 0, 'completions' => 0];
            $days[$day][$field]++;
        };
        $base = 'FROM {user_enrolments} ue
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {user} u ON u.id = ue.userid AND u.deleted = 0';
        $sql = 'SELECT ue.timecreated ' . $base .
            ' WHERE e.courseid <> :site AND ue.timecreated >= :from AND ue.timecreated < :until';
        $times = $DB->get_recordset_sql($sql, ['site' => (int) SITEID, 'from' => $from, 'until' => $until]);
        foreach ($times as $row) {
            $bucket((int) $row->timecreated, 'enrolments');
        }
        $times->close();
        $sql = 'SELECT cc.timecompleted ' . $base . '
                  JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = u.id
                 WHERE e.courseid <> :site AND ue.timecreated > 0 AND cc.timecompleted >= :from AND cc.timecompleted < :until';
        $times = $DB->get_recordset_sql($sql, ['site' => (int) SITEID, 'from' => $from, 'until' => $until]);
        foreach ($times as $row) {
            $bucket((int) $row->timecompleted, 'completions');
        }
        $times->close();
        ksort($days);
        return array_values($days);
    }
}
