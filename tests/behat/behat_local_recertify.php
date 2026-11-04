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
 * Behat steps for local_recertify.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Named pages and completion setup for the plugin.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_recertify extends behat_base {
    /**
     * Resolve "local_recertify > Status" with a course shortname or full name.
     *
     * @param string $type
     * @param string $identifier
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        if (strtolower($type) === 'status') {
            return new moodle_url('/local/recertify/status.php', ['courseid' => $this->get_course_id($identifier)]);
        }
        throw new Exception("Unknown page type '$type'.");
    }

    /**
     * Mark a course completed for a user some days ago.
     *
     * @Given /^"(?P<username>[^"]*)" completed the course "(?P<course>[^"]*)" (?P<days>\d+) days ago$/
     * @param string $username
     * @param string $course Shortname or full name.
     * @param int $days
     */
    public function user_completed_course_days_ago(string $username, string $course, int $days): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $this->get_user_id_by_identifier($username),
            'course' => $this->get_course_id($course),
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => time() - $days * DAYSECS,
            'reaggregate' => 0,
        ]);
    }
}
