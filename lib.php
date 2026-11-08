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
 * Navigation.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Recertification" to the course navigation for staff who can see learners' status.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_recertify_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if (has_capability('local/recertify:viewstatus', $context)) {
        $navigation->add(
            get_string('pluginname', 'local_recertify'),
            new moodle_url('/local/recertify/status.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_recertify',
            new pix_icon('i/completion-auto-enabled', '')
        );
    }
}

/**
 * Remove the settings, history and reminder records of a course that is being deleted. After deletion its
 * context is gone, so the privacy API could no longer find them.
 *
 * @param stdClass $course
 */
function local_recertify_pre_course_delete($course) {
    global $DB;
    foreach (['local_recertify_course', 'local_recertify_history', 'local_recertify_notice'] as $table) {
        $DB->delete_records($table, ['courseid' => $course->id]);
    }
}
