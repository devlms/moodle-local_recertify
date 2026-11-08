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

namespace local_recertify\task;

use local_recertify\event\certification_expired;
use local_recertify\local\{notifier, resetter, schedule, settings};

/**
 * Daily: send reminders, then reset expired certifications, in every course that has it on.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process extends \core\task\scheduled_task {
    /** @var int Most resets in one run, across all courses. */
    public const MAXRESETS = 200;

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:process', 'local_recertify');
    }

    /**
     * Run for now.
     */
    public function execute() {
        $this->run(time());
    }

    /**
     * Run for a given time.
     *
     * @param int $now
     */
    public function run(int $now): void {
        global $DB;
        $total = 0;
        foreach (settings::enabled_courses() as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }
            if ($why = resetter::blocking_criteria($courseid)) {
                mtrace("Recertification: course $courseid skipped: " . get_string($why, 'local_recertify'));
                continue;
            }
            $cfg = settings::get($courseid);
            foreach (schedule::due_reminders($cfg, $now) as $reminder) {
                notifier::reminder($course, $reminder->userid, $reminder->expires);
                schedule::mark_reminded($cfg, $reminder, $now);
            }
            $resets = 0;
            foreach (schedule::due_resets($cfg, $now) as $userid) {
                // A cap per run, so turning the plugin on for an old course does not reset thousands at once.
                if ($total >= self::MAXRESETS) {
                    mtrace('Recertification: ' . self::MAXRESETS . ' resets this run, the rest wait for the next run.');
                    return;
                }
                try {
                    $historyid = resetter::reset($courseid, $userid);
                } catch (\Throwable $e) {
                    mtrace("Recertification: could not reset user $userid in course $courseid: " . $e->getMessage());
                    continue;
                }
                certification_expired::create([
                    'objectid' => $historyid,
                    'context' => \context_course::instance($courseid),
                    'relateduserid' => $userid,
                ])->trigger();
                notifier::expired($course, $userid);
                $resets++;
                $total++;
            }
            mtrace("Recertification: course $courseid, $resets reset.");
        }
    }
}
