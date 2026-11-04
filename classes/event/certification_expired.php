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

namespace local_recertify\event;

/**
 * Logged when a certification expires and the learner's progress is reset.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_expired extends \core\event\base {
    /**
     * Set the basic event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_recertify_history';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:expired', 'local_recertify');
    }

    /**
     * Description for the logs.
     *
     * @return string
     */
    public function get_description() {
        return "The certification of the user with id '{$this->relateduserid}' in the course with id '{$this->courseid}' "
            . "expired. Their progress was archived as '{$this->objectid}' and reset.";
    }

    /**
     * Where to see the learner's status.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/recertify/status.php', ['courseid' => $this->courseid]);
    }

    /**
     * Check the learner is given.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
    }

    /**
     * Backup mapping for the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_recertify_history', 'restore' => self::NOT_MAPPED];
    }
}
