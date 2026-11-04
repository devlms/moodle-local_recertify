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

use core_reportbuilder\tests\core_reportbuilder_testcase;

/**
 * Tests for the certifications report source.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_recertify\reportbuilder\datasource\certifications
 * @covers     \local_recertify\reportbuilder\local\entities\certification
 */
final class certifications_test extends core_reportbuilder_testcase {
    /**
     * One archived certification.
     *
     * @return array [course, user]
     */
    private function make(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Fire safety']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record('local_recertify_history', (object) ['courseid' => $course->id, 'userid' => $user->id,
            'timecompleted' => 1700000000, 'timeexpired' => 1731536000, 'finalgrade' => 92.5, 'archive' => '{}']);
        return [$course, $user];
    }

    public function test_default_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $user] = $this->make();
        $report = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder')
            ->create_report(['name' => 'Certs', 'source' => certifications::class, 'default' => 1]);
        $content = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        $this->assertSame(
            ['Fire safety', fullname($user), userdate(1700000000), userdate(1731536000), '92.50'],
            $content[0]
        );
    }

    public function test_only_available_with_viewall(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertTrue(certifications::is_available());
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(certifications::is_available());
    }

    public function test_stress(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->make();
        $this->datasource_stress_test_columns(certifications::class);
        $this->datasource_stress_test_columns_aggregation(certifications::class);
        $this->datasource_stress_test_conditions(certifications::class, 'certification:timeexpired');
    }
}
