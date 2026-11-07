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

namespace local_recertify\local;

use local_recertify\task\process;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\local\settings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\local\schedule::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\task\process::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\local\notifier::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\event\certification_expired::class)]
/**
 * Tests for settings, the schedule and the daily task.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_recertify\local\settings
 * @covers     \local_recertify\local\schedule
 * @covers     \local_recertify\task\process
 * @covers     \local_recertify\local\notifier
 * @covers     \local_recertify\event\certification_expired
 */
final class schedule_test extends \advanced_testcase {
    /** @var int A fixed "now". */
    private const NOW = 1800000000;
    /** @var int Seconds in a day. */
    private const DAY = 86400;

    /** @var \stdClass */
    private \stdClass $course;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * Enrol a learner whose completion is this many days old.
     *
     * @param int $daysago
     * @param string $status active or suspended
     * @return \stdClass
     */
    private function completed(int $daysago, string $status = 'active'): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_and_enrol(
            $this->course,
            'student',
            null,
            'manual',
            0,
            0,
            $status === 'active' ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED
        );
        $DB->insert_record('course_completions', (object) [
            'userid' => $user->id,
            'course' => $this->course->id,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => self::NOW - $daysago * self::DAY,
            'reaggregate' => 0,
        ]);
        return $user;
    }

    public function test_settings_defaults_and_save(): void {
        $cfg = settings::get((int) $this->course->id);
        $this->assertFalse((bool) $cfg->enabled);
        $this->assertSame(365, (int) $cfg->perioddays);
        $this->assertSame([30, 7], settings::reminder_days($cfg));

        settings::save((int) $this->course->id, true, 180, [7, 30, 7, 0, -3, 400]);
        $cfg = settings::get((int) $this->course->id);
        $this->assertTrue((bool) $cfg->enabled);
        $this->assertSame(180, (int) $cfg->perioddays);
        $this->assertSame([30, 7], settings::reminder_days($cfg), 'Sorted, unique, and inside the period.');
        $this->assertSame([(int) $this->course->id], settings::enabled_courses());
    }

    public function test_period_must_be_positive(): void {
        $this->expectException(\invalid_parameter_exception::class);
        settings::save((int) $this->course->id, true, 0, []);
    }

    public function test_due_resets(): void {
        settings::save((int) $this->course->id, true, 365, [30, 7]);
        $expired = $this->completed(366);
        $this->completed(10);
        $this->completed(400, 'suspended');
        $this->assertSame([(int) $expired->id], schedule::due_resets(settings::get((int) $this->course->id), self::NOW));
    }

    public function test_reminders_pick_the_nearest_and_never_repeat(): void {
        settings::save((int) $this->course->id, true, 365, [30, 7]);
        $soon = $this->completed(360);
        $month = $this->completed(340);
        $this->completed(100);
        $cfg = settings::get((int) $this->course->id);
        $due = schedule::due_reminders($cfg, self::NOW);
        $this->assertEquals([
            ['userid' => $soon->id, 'daysbefore' => 7, 'expires' => self::NOW + 5 * self::DAY],
            ['userid' => $month->id, 'daysbefore' => 30, 'expires' => self::NOW + 25 * self::DAY],
        ], array_map(fn($r) => ['userid' => $r->userid, 'daysbefore' => $r->daysbefore, 'expires' => $r->expires], $due));
        foreach ($due as $reminder) {
            schedule::mark_reminded($cfg, $reminder, self::NOW);
        }
        $this->assertSame([], schedule::due_reminders($cfg, self::NOW));
        // The 30 day reminder was skipped for the user already inside 7 days: it must not arrive later either.
        $this->assertSame([], schedule::due_reminders($cfg, self::NOW + self::DAY));
    }

    public function test_status(): void {
        global $DB;
        settings::save((int) $this->course->id, true, 365, [30]);
        $cfg = settings::get((int) $this->course->id);
        $valid = $this->completed(10);
        $expiring = $this->completed(350);
        $none = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertSame('valid', schedule::status($cfg, (int) $valid->id, self::NOW)->state);
        $this->assertSame('expiring', schedule::status($cfg, (int) $expiring->id, self::NOW)->state);
        $this->assertSame(self::NOW + 15 * self::DAY, schedule::status($cfg, (int) $expiring->id, self::NOW)->expires);
        $this->assertSame('none', schedule::status($cfg, (int) $none->id, self::NOW)->state);
        $DB->insert_record('local_recertify_history', (object) ['courseid' => $this->course->id, 'userid' => $none->id,
            'timecompleted' => 1, 'timeexpired' => 2, 'archive' => '{}']);
        $this->assertSame('expired', schedule::status($cfg, (int) $none->id, self::NOW)->state);
    }

    public function test_task_sends_reminders_and_resets(): void {
        global $DB;
        settings::save((int) $this->course->id, true, 365, [30]);
        $expired = $this->completed(370);
        $soon = $this->completed(350);
        $disabled = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $sink = $this->redirectMessages();
        $events = $this->redirectEvents();
        ob_start();
        (new process())->run(self::NOW);
        ob_end_clean();

        $this->assertFalse($DB->record_exists('course_completions', ['userid' => $expired->id]));
        $this->assertTrue($DB->record_exists('local_recertify_history', ['userid' => $expired->id]));
        $this->assertTrue($DB->record_exists('course_completions', ['userid' => $soon->id]));
        $messages = $sink->get_messages();
        $this->assertEqualsCanonicalizing(
            [[(int) $expired->id, 'expired'], [(int) $soon->id, 'reminder']],
            array_map(fn($m) => [(int) $m->useridto, $m->eventtype], $messages)
        );
        $logged = array_filter($events->get_events(), fn($e) => $e instanceof \local_recertify\event\certification_expired);
        $this->assertCount(1, $logged);
        $event = reset($logged);
        $this->assertStringContainsString("user with id '{$expired->id}'", $event->get_description());
        $this->assertStringContainsString('/local/recertify/status.php', $event->get_url()->out(false));
        $this->assertNotEmpty($event::get_name());
        $this->assertSame('local_recertify_history', $event::get_objectid_mapping()['db']);
        $this->assertNotEmpty((new process())->get_name());
        $this->assertNotEmpty($disabled);

        // Running again the same day does nothing more.
        $sink->clear();
        ob_start();
        (new process())->run(self::NOW);
        ob_end_clean();
        $this->assertSame(0, $sink->count());
    }
}
