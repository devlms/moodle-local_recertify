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

namespace local_recertify;

use local_recertify\form\settings_form;

#[\PHPUnit\Framework\Attributes\CoversFunction('local_recertify_extend_navigation_course')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\form\settings_form::class)]
/**
 * Tests for navigation and the settings form.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_recertify_extend_navigation_course
 * @covers     \local_recertify\form\settings_form
 */
final class lib_test extends \advanced_testcase {
    public function test_navigation_only_for_staff(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/recertify/lib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $nav = function () use ($course): int {
            $root = \navigation_node::create('root');
            local_recertify_extend_navigation_course($root, $course, \context_course::instance($course->id));
            return count($root->children);
        };
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertSame(1, $nav());
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertSame(0, $nav());
    }

    public function test_settings_form(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = new settings_form(new \moodle_url('/local/recertify/status.php'));
        $this->assertStringContainsString('name="perioddays"', $form->render());
        $this->assertArrayHasKey('perioddays', $form->validation(['perioddays' => 0], []));
        $this->assertSame([], $form->validation(['perioddays' => 365], []));
    }
}
