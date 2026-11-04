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
use core_user;
use moodle_url;

/**
 * Messages to learners.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {
    /**
     * Tell a learner their certification expires soon.
     *
     * @param \stdClass $course
     * @param int $userid
     * @param int $expires
     */
    public static function reminder(\stdClass $course, int $userid, int $expires): void {
        $user = core_user::get_user($userid);
        $a = (object) [
            'course' => self::course_name($course),
            'date' => userdate($expires, get_string('strftimedate', 'langconfig'), $user->timezone ?? 99),
        ];
        self::send(
            $course,
            $user,
            'reminder',
            get_string('message:reminder:subject', 'local_recertify', $a),
            get_string('message:reminder', 'local_recertify', $a)
        );
    }

    /**
     * Tell a learner their certification expired and the course was reset.
     *
     * @param \stdClass $course
     * @param int $userid
     */
    public static function expired(\stdClass $course, int $userid): void {
        $name = self::course_name($course);
        $user = core_user::get_user($userid);
        self::send(
            $course,
            $user,
            'expired',
            get_string('message:expired:subject', 'local_recertify', $name),
            get_string('message:expired', 'local_recertify', $name)
        );
    }

    /**
     * Course name for messages.
     *
     * @param \stdClass $course
     * @return string
     */
    private static function course_name(\stdClass $course): string {
        return format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
    }

    /**
     * Send a notification.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param string $provider
     * @param string $subject
     * @param string $text
     */
    private static function send(\stdClass $course, \stdClass $user, string $provider, string $subject, string $text): void {
        $message = new \core\message\message();
        $message->component = 'local_recertify';
        $message->name = $provider;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html(s($text), false, false, true);
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $message->contexturlname = self::course_name($course);
        $message->courseid = $course->id;
        message_send($message);
    }
}
