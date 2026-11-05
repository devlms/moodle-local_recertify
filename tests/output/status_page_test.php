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

namespace local_recertify\output;

use local_recertify\local\settings;

/**
 * Tests for the data behind the status page.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_recertify\output\status_page
 */
final class status_page_test extends \advanced_testcase {
    /** @var int A fixed "now". */
    private const NOW = 1800000000;

    /** @var \stdClass */
    private \stdClass $course;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * Enrol a learner, completed this many days ago (null: never).
     *
     * @param string $name
     * @param int|null $daysago
     * @return \stdClass
     */
    private function learner(string $name, ?int $daysago): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => $name, 'lastname' => 'L']);
        if ($daysago !== null) {
            $DB->insert_record('course_completions', (object) [
                'userid' => $user->id, 'course' => $this->course->id, 'timeenrolled' => 0, 'timestarted' => 0,
                'timecompleted' => self::NOW - $daysago * DAYSECS, 'reaggregate' => 0,
            ]);
        }
        return $user;
    }

    /**
     * Export the page.
     *
     * @param string $filter
     * @param bool $enabled
     * @return array
     */
    private function export(string $filter = '', bool $enabled = true): array {
        global $PAGE;
        settings::save($this->course->id, $enabled, 365, [30, 7]);
        $cfg = settings::get($this->course->id);
        $users = array_values(get_enrolled_users(\context_course::instance($this->course->id)));
        $page = new status_page($this->course, $cfg, $users, self::NOW, $filter, true);
        return json_decode(json_encode($page->export_for_template($PAGE->get_renderer('core'))), true);
    }

    public function test_tiles_rows_in_order_of_urgency(): void {
        $this->learner('Valid', 100);
        $this->learner('Soon', 360);
        $this->learner('Never', null);
        $this->learner('Sooner', 345);

        $data = $this->export();
        $this->assertSame(
            ['valid' => 1, 'expiring' => 2, 'expired' => 0, 'none' => 1],
            array_column($data['tiles'], 'count', 'key')
        );
        $this->assertSame(['Soon L', 'Sooner L', 'Valid L', 'Never L'], array_column($data['rows'], 'name'));
        $this->assertSame('Expiring soon', $data['rows'][0]['state']);
        $this->assertStringContainsString('bg-warning', $data['rows'][0]['badge']);
        $this->assertSame('In 5 days', $data['rows'][0]['expiresin']);
        $this->assertSame('One learner will be reset in the next 7 days.', $data['nextweek']);
        $this->assertTrue($data['enabled']);
        $this->assertSame('30 and 7', $data['reminders']);
    }

    public function test_filter_by_state(): void {
        $this->learner('Valid', 100);
        $this->learner('Soon', 360);
        $data = $this->export('valid');
        $this->assertSame(['Valid L'], array_column($data['rows'], 'name'));
        $this->assertTrue($data['tiles'][0]['active']);
        $this->assertTrue($data['filtered']);
    }

    public function test_off_explains_what_would_happen(): void {
        $this->learner('Soon', 362);
        $this->learner('Later', 300);
        $data = $this->export('', false);
        $this->assertFalse($data['enabled']);
        $this->assertSame('If you turn it on, one learner would be reset in the next 7 days.', $data['nextweek']);
        $this->learner('Also', 361);
        $data = $this->export('', false);
        $this->assertSame('If you turn it on, 2 learners would be reset in the next 7 days.', $data['nextweek']);
    }

    public function test_nobody_due(): void {
        $this->learner('Valid', 10);
        $data = $this->export();
        $this->assertSame('Nobody is due for a reset in the next 7 days.', $data['nextweek']);
        $this->assertFalse($data['hasdue']);
    }
}
