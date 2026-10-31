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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_socialreport\local\counts::class)]
/**
 * Tests of the counts: they add up right, respect the date ranges, say "no record" apart from "not completed", leave out the
 * people whose account was deleted, and never carry anything of a person.
 *
 * @package    local_socialreport
 * @category   test
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialreport\local\counts
 */
final class counts_test extends \advanced_testcase {
    /** @var \DateTimeZone */
    private \DateTimeZone $tz;

    /**
     * Fixed time zone for every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->tz = new \DateTimeZone('America/Sao_Paulo');
    }

    /**
     * Timestamp of a day at 15:42 in the test time zone.
     *
     * @param string $day
     * @return int
     */
    private function ts_of(string $day): int {
        return (new \DateTimeImmutable($day . ' 15:42:00', $this->tz))->getTimestamp();
    }

    /**
     * Enrols a new user in a course, with the creation time of the enrolment, and optionally a completion record.
     *
     * @param \stdClass $course
     * @param string $created day of the enrolment
     * @param string|null|false $completed day of the completion; null = record, not completed; false = no record at all
     * @return \stdClass the user
     */
    private function enrol(\stdClass $course, string $created, $completed = false): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $DB->execute('UPDATE {user_enrolments} SET timecreated = ? WHERE userid = ?', [$this->ts_of($created), $user->id]);
        if ($completed !== false) {
            $DB->insert_record('course_completions', (object) [
                'userid' => $user->id, 'course' => $course->id, 'timeenrolled' => 0, 'timestarted' => 0,
                'timecompleted' => $completed === null ? null : $this->ts_of($completed), 'reaggregate' => 0,
            ]);
        }
        return $user;
    }

    /**
     * The ranges and cutoff of the scenario: October 2026, September (cycle before) and October 2025 (year before).
     *
     * @return array
     */
    private function ranges(): array {
        return [
            'current' => ['2026-10-01', '2026-10-28'],
            'previous' => ['2026-09-01', '2026-09-30'],
            'lastyear' => ['2025-10-01', '2025-10-28'],
        ];
    }

    /**
     * The result by course short name.
     *
     * @return array
     */
    private function fetch_counts(): array {
        $now = $this->ts_of('2026-10-28');
        $result = counts::compute($this->ranges(), '2026-07-30', $this->tz, $now);
        $byname = [];
        foreach ($result['courses'] as $course) {
            $byname[$course['shortname']] = $course;
        }
        return [$byname, $result];
    }

    public function test_counts_per_course_per_range_and_per_day(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_course(['shortname' => 'CursoA']);
        $b = $this->getDataGenerator()->create_course(['shortname' => 'CursoB']);
        $this->getDataGenerator()->create_course(['shortname' => 'CursoD']);
        $this->enrol($a, '2026-10-02', '2026-10-05');
        $this->enrol($a, '2026-10-02', null);
        $this->enrol($a, '2026-09-10', '2026-10-05');
        $this->enrol($a, '2025-10-10', false);
        $this->enrol($b, '2026-10-28');
        [$byname, $result] = $this->fetch_counts();

        $this->assertSame(5, $byname['CursoA']['enrolments'] + $byname['CursoB']['enrolments']);
        $this->assertSame(['enrolments' => 2, 'completions' => 2], $byname['CursoA']['current']);
        $this->assertSame(['enrolments' => 1, 'completions' => 0], $byname['CursoA']['previous']);
        $this->assertSame(['enrolments' => 1, 'completions' => 0], $byname['CursoA']['lastyear']);
        $this->assertSame(['enrolments' => 1, 'completions' => 0], $byname['CursoB']['current']);
        $this->assertSame('2025-10-10', $byname['CursoA']['firstenrolment']);
        // Two enrolments completed (10-05, twice); one has a record not completed; one has no record at all.
        $this->assertSame(2, $byname['CursoA']['completions']);
        $this->assertSame(1, $byname['CursoA']['norecord']);
        $this->assertSame([
            ['date' => '2026-10-02', 'enrolments' => 2, 'completions' => 0],
            ['date' => '2026-10-05', 'enrolments' => 0, 'completions' => 2],
            ['date' => '2026-10-28', 'enrolments' => 1, 'completions' => 0],
        ], $result['daily']);
    }

    public function test_course_without_enrolment_is_listed_with_zeros_and_the_site_course_is_not(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course(['shortname' => 'Vazio']);
        [$byname] = $this->fetch_counts();
        $this->assertSame(0, $byname['Vazio']['enrolments']);
        $this->assertSame('', $byname['Vazio']['firstenrolment']);
        $this->assertSame(['Vazio'], array_keys($byname));
    }

    public function test_no_record_is_not_the_same_as_not_completed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'Curso']);
        $this->enrol($course, '2026-05-01', false);
        $this->enrol($course, '2026-05-01', null);
        $this->enrol($course, '2026-05-01', '2026-06-01');
        [$byname] = $this->fetch_counts();
        $this->assertSame(3, $byname['Curso']['enrolments']);
        $this->assertSame(1, $byname['Curso']['norecord']);
        $this->assertSame(1, $byname['Curso']['completions']);
        // The cohort (enrolled until the cutoff) only counts the ones with a record, and completed is a subset of it.
        $this->assertSame(2, $byname['Curso']['cohort']);
        $this->assertSame(1, $byname['Curso']['cohortcompleted']);
        // Active and not completed: the "no record" one and the "not completed" one.
        $this->assertSame(2, $byname['Curso']['active']);
    }

    public function test_cohort_cutoff_is_inclusive_and_a_later_enrolment_is_out(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'Curso']);
        $this->enrol($course, '2026-07-30', null);
        $this->enrol($course, '2026-07-31', null);
        [$byname] = $this->fetch_counts();
        $this->assertSame(1, $byname['Curso']['cohort']);
    }

    public function test_range_ends_are_inclusive_in_the_site_time_zone(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course(['shortname' => 'Curso']);
        $late = $this->enrol($course, '2026-10-28');
        // 23:59:59 of the last day is inside; the first second of the next day is outside.
        $lastsecond = (new \DateTimeImmutable('2026-10-28 23:59:59', $this->tz))->getTimestamp();
        $DB->set_field('user_enrolments', 'timecreated', $lastsecond, ['userid' => $late->id]);
        $out = $this->enrol($course, '2026-10-28');
        $nextday = (new \DateTimeImmutable('2026-10-29 00:00:00', $this->tz))->getTimestamp();
        $DB->set_field('user_enrolments', 'timecreated', $nextday, ['userid' => $out->id]);
        [$byname, $result] = $this->fetch_counts();
        $this->assertSame(1, $byname['Curso']['current']['enrolments']);
        $this->assertSame([['date' => '2026-10-28', 'enrolments' => 1, 'completions' => 0]], $result['daily']);
    }

    public function test_deleted_people_do_not_count(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'Curso']);
        $gone = $this->enrol($course, '2026-10-02', '2026-10-03');
        $this->enrol($course, '2026-10-02', '2026-10-03');
        delete_user($gone);
        [$byname, $result] = $this->fetch_counts();
        $this->assertSame(1, $byname['Curso']['enrolments']);
        $this->assertSame(1, $byname['Curso']['completions']);
        $this->assertSame(1, $result['daily'][0]['enrolments']);
    }

    public function test_nothing_personal_in_the_result(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'Curso']);
        $user = $this->enrol($course, '2026-10-02', '2026-10-03');
        [, $result] = $this->fetch_counts();
        $text = json_encode($result);
        foreach ([$user->username, $user->email, $user->firstname, $user->lastname, (string) $user->idnumber] as $personal) {
            if ($personal !== '') {
                $this->assertStringNotContainsString($personal, $text);
            }
        }
        $this->assertEqualsCanonicalizing(['courses', 'daily'], array_keys($result));
        $this->assertEqualsCanonicalizing(
            ['shortname', 'firstenrolment', 'enrolments', 'norecord', 'completions', 'active', 'cohort', 'cohortcompleted',
                'current', 'previous', 'lastyear'],
            array_keys($result['courses'][0])
        );
        $this->assertEqualsCanonicalizing(['date', 'enrolments', 'completions'], array_keys($result['daily'][0]));
    }

    public function test_invalid_dates_and_ranges_are_refused(): void {
        $now = $this->ts_of('2026-10-28');
        $bad = [
            ['2026-13-01', '2026-10-28'], ['2026-10-01', 'ontem'], ['2026-10-28', '2026-10-01'], ['2026-01-01', '2027-12-31'],
        ];
        foreach ($bad as [$start, $end]) {
            $ranges = $this->ranges();
            $ranges['current'] = [$start, $end];
            try {
                counts::compute($ranges, '2026-07-30', $this->tz, $now);
                $this->fail("$start $end should be refused");
            } catch (\invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertIsArray(counts::compute($this->ranges(), '2026-07-30', $this->tz, $now));
    }
}
