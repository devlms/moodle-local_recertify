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

use context_course;
use stdClass;

/**
 * Works out who expires, who needs a reminder, and where each learner stands.
 *
 * A certification expires a fixed number of days after the course was completed. Only learners
 * with an active enrolment are reminded or reset.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schedule {
    /** @var int Seconds in a day. */
    public const DAY = 86400;

    /**
     * Learners whose certification has expired by $now.
     *
     * @param stdClass $cfg Course settings.
     * @param int $now
     * @return int[] User ids.
     */
    public static function due_resets(stdClass $cfg, int $now): array {
        $due = [];
        foreach (self::completions($cfg) as $row) {
            if ((int) $row->timecompleted + $cfg->perioddays * self::DAY <= $now) {
                $due[] = (int) $row->userid;
            }
        }
        return $due;
    }

    /**
     * Reminders to send now. Each learner gets the nearest reminder that applies, so a learner
     * already inside 7 days never gets the 30 day one late.
     *
     * @param stdClass $cfg
     * @param int $now
     * @return stdClass[] userid, timecompleted, daysbefore, expires
     */
    public static function due_reminders(stdClass $cfg, int $now): array {
        global $DB;
        $days = settings::reminder_days($cfg);
        $due = [];
        foreach (self::completions($cfg) as $row) {
            $expires = (int) $row->timecompleted + $cfg->perioddays * self::DAY;
            if ($expires <= $now) {
                continue;
            }
            $applies = array_filter($days, fn($d) => $expires - $d * self::DAY <= $now);
            if (!$applies) {
                continue;
            }
            $nearest = min($applies);
            $sent = $DB->record_exists('local_recertify_notice', ['courseid' => $cfg->courseid, 'userid' => $row->userid,
                'timecompleted' => $row->timecompleted, 'daysbefore' => $nearest]);
            if (!$sent) {
                $due[] = (object) ['userid' => (int) $row->userid, 'timecompleted' => (int) $row->timecompleted,
                    'daysbefore' => $nearest, 'expires' => $expires];
            }
        }
        return $due;
    }

    /**
     * Record a reminder as sent, along with every earlier one it replaces.
     *
     * @param stdClass $cfg
     * @param stdClass $reminder From due_reminders().
     * @param int $now
     */
    public static function mark_reminded(stdClass $cfg, stdClass $reminder, int $now): void {
        global $DB;
        foreach (settings::reminder_days($cfg) as $days) {
            $key = ['courseid' => $cfg->courseid, 'userid' => $reminder->userid,
                'timecompleted' => $reminder->timecompleted, 'daysbefore' => $days];
            if ($days >= $reminder->daysbefore && !$DB->record_exists('local_recertify_notice', $key)) {
                $DB->insert_record('local_recertify_notice', (object) ($key + ['timesent' => $now]));
            }
        }
    }

    /**
     * Where a learner stands: valid, expiring (inside the first reminder), expired (reset, not yet
     * completed again) or none.
     *
     * @param stdClass $cfg
     * @param int $userid
     * @param int $now
     * @return stdClass state, timecompleted, expires, certifications (past ones on record)
     */
    public static function status(stdClass $cfg, int $userid, int $now): stdClass {
        global $DB;
        $past = $DB->count_records('local_recertify_history', ['courseid' => $cfg->courseid, 'userid' => $userid]);
        $timecompleted = (int) $DB->get_field(
            'course_completions',
            'timecompleted',
            ['course' => $cfg->courseid, 'userid' => $userid]
        );
        if ($timecompleted) {
            $expires = $timecompleted + $cfg->perioddays * self::DAY;
            $window = (settings::reminder_days($cfg)[0] ?? 30) * self::DAY;
            $state = $expires <= $now ? 'expired' : ($expires - $window <= $now ? 'expiring' : 'valid');
            return (object) ['state' => $state, 'timecompleted' => $timecompleted, 'expires' => $expires,
                'certifications' => $past];
        }
        return (object) ['state' => $past ? 'expired' : 'none', 'timecompleted' => 0, 'expires' => 0,
            'certifications' => $past];
    }

    /**
     * Course completions of learners with an active enrolment, oldest first.
     *
     * @param stdClass $cfg
     * @return stdClass[] userid, timecompleted
     */
    private static function completions(stdClass $cfg): array {
        global $DB;
        $context = context_course::instance($cfg->courseid);
        $rows = $DB->get_records_select(
            'course_completions',
            'course = ? AND timecompleted > 0',
            [$cfg->courseid],
            'timecompleted ASC, id ASC',
            'id, userid, timecompleted'
        );
        return array_values(array_filter($rows, fn($r) => is_enrolled($context, $r->userid, '', true)));
    }
}
