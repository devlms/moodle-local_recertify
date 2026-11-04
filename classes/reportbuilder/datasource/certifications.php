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

namespace local_recertify\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use local_recertify\reportbuilder\local\entities\certification;

/**
 * Report source for past certifications across the site, for compliance reporting.
 *
 * Only for people with local/recertify:viewall at site level.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certifications extends datasource {
    /**
     * Source name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('certificationsource', 'local_recertify');
    }

    /**
     * Only for people allowed to see every learner's history.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return has_capability('local/recertify:viewall', \context_system::instance());
    }

    /**
     * Join each archived certification to its course and learner.
     */
    protected function initialise(): void {
        $entity = new certification();
        $alias = $entity->get_table_alias('local_recertify_history');
        $this->set_main_table('local_recertify_history', $alias);
        $this->add_entity($entity);
        $course = new course();
        $coursealias = $course->get_table_alias('course');
        $this->add_entity($course->add_join("JOIN {course} {$coursealias} ON {$coursealias}.id = {$alias}.courseid"));
        $user = new user();
        $useralias = $user->get_table_alias('user');
        $this->add_entity($user->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$alias}.userid"));
        $this->add_all_from_entities();
    }

    /**
     * Columns of a new report.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['course:fullname', 'user:fullname', 'certification:timecompleted', 'certification:timeexpired',
            'certification:finalgrade'];
    }

    /**
     * Sorting of a new report.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return ['certification:timeexpired' => SORT_DESC];
    }

    /**
     * Filters of a new report.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['course:fullname', 'certification:timeexpired'];
    }

    /**
     * Conditions of a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['course:fullname'];
    }
}
