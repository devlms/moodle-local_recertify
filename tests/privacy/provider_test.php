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

namespace local_recertify\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_recertify\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $user;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        foreach ([$this->user, $other] as $u) {
            $DB->insert_record('local_recertify_history', (object) ['courseid' => $this->course->id, 'userid' => $u->id,
                'timecompleted' => 1700000000, 'timeexpired' => 1731536000, 'finalgrade' => 87.5,
                'archive' => json_encode(['grades' => [5 => 9.0]])]);
            $DB->insert_record('local_recertify_notice', (object) ['courseid' => $this->course->id, 'userid' => $u->id,
                'timecompleted' => 1700000000, 'daysbefore' => 30, 'timesent' => 1729000000]);
        }
    }

    public function test_metadata(): void {
        $items = provider::get_metadata(new \core_privacy\local\metadata\collection('local_recertify'))->get_collection();
        $this->assertEqualsCanonicalizing(
            ['local_recertify_history', 'local_recertify_notice', 'core_message'],
            array_map(fn($i) => $i->get_name(), $items)
        );
    }

    public function test_contexts_and_users(): void {
        $context = \context_course::instance($this->course->id);
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $this->user->id)->get_contextids());
        $list = new userlist($context, 'local_recertify');
        provider::get_users_in_context($list);
        $this->assertCount(2, $list->get_userids());
    }

    public function test_export(): void {
        $context = \context_course::instance($this->course->id);
        provider::export_user_data(new approved_contextlist($this->user, 'local_recertify', [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_recertify')]);
        $this->assertCount(1, $data->certifications);
        $this->assertEquals(87.5, $data->certifications[0]->finalgrade);
        $this->assertCount(1, $data->reminders);
    }

    public function test_delete_one_user_then_the_context(): void {
        global $DB;
        $context = \context_course::instance($this->course->id);
        provider::delete_data_for_user(new approved_contextlist($this->user, 'local_recertify', [$context->id]));
        $this->assertFalse($DB->record_exists('local_recertify_history', ['userid' => $this->user->id]));
        $this->assertFalse($DB->record_exists('local_recertify_notice', ['userid' => $this->user->id]));
        $this->assertSame(1, $DB->count_records('local_recertify_history'));
        provider::delete_data_for_users(new approved_userlist($context, 'local_recertify', []));
        $this->assertSame(1, $DB->count_records('local_recertify_history'));
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('local_recertify_history'));
        $this->assertSame(0, $DB->count_records('local_recertify_notice'));
    }
}
