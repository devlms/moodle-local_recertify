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

use stdClass;

/**
 * Recertification settings per course.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class settings {
    /** @var string Table. */
    private const TABLE = 'local_recertify_course';

    /**
     * Settings for a course, with defaults when none are saved.
     *
     * @param int $courseid
     * @return stdClass courseid, enabled, perioddays, reminderdays
     */
    public static function get(int $courseid): stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['courseid' => $courseid]) ?: (object) [
            'courseid' => $courseid,
            'enabled' => 0,
            'perioddays' => 365,
            'reminderdays' => '30,7',
        ];
    }

    /**
     * Save settings for a course.
     *
     * @param int $courseid
     * @param bool $enabled
     * @param int $perioddays How long a completion stays valid, in days.
     * @param int[] $reminderdays Days before expiry to remind. Values outside 1 to the period are dropped.
     */
    public static function save(int $courseid, bool $enabled, int $perioddays, array $reminderdays): void {
        global $DB;
        if ($perioddays < 1) {
            throw new \invalid_parameter_exception('The period must be at least one day.');
        }
        $days = array_unique(array_filter(array_map('intval', $reminderdays), fn($d) => $d >= 1 && $d < $perioddays));
        rsort($days);
        $record = (object) [
            'courseid' => $courseid,
            'enabled' => $enabled ? 1 : 0,
            'perioddays' => $perioddays,
            'reminderdays' => implode(',', $days),
            'timemodified' => time(),
        ];
        if ($id = $DB->get_field(self::TABLE, 'id', ['courseid' => $courseid])) {
            $record->id = $id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Reminder days, largest first.
     *
     * @param stdClass $cfg
     * @return int[]
     */
    public static function reminder_days(stdClass $cfg): array {
        $days = array_filter(array_map('intval', explode(',', (string) $cfg->reminderdays)), fn($d) => $d > 0);
        rsort($days);
        return array_values(array_unique($days));
    }

    /**
     * Courses with recertification switched on.
     *
     * @return int[]
     */
    public static function enabled_courses(): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(self::TABLE, 'courseid', 'enabled = 1'));
    }
}
