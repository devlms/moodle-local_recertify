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

use completion_info;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_recertify\local\resetter::class)]
/**
 * Tests for resetting one learner's progress in a course.
 *
 * @package    local_recertify
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_recertify\local\resetter
 */
final class resetter_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $quiz;

    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    }

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['enablecompletion' => 1]);
        // A quiz completed by passing it, and a course completed by completing the quiz.
        $this->quiz = $gen->create_module('quiz', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
            'completionpassgrade' => 1,
            'gradepass' => 5,
            'grade' => 10,
            'sumgrades' => 1,
        ]);
        $gen->get_plugin_generator('mod_quiz')->create_quiz_structure($this->quiz, [['TF1', 1, 'truefalse']]);
        $criterion = new \completion_criteria_activity();
        $config = (object) ['id' => $this->course->id, 'criteria_activity' => [$this->quiz->cmid => 1]];
        $criterion->update_config($config);
    }

    /**
     * Enrol a learner who passes the quiz, and complete the course for them.
     *
     * @return \stdClass the learner
     */
    private function certified_learner(): \stdClass {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->pass_quiz($user);
        return $user;
    }

    /**
     * Pass the quiz as this user and run completion aggregation.
     *
     * @param \stdClass $user
     */
    private function pass_quiz(\stdClass $user): void {
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $this->setUser($user);
        $attempt = $quizgen->create_attempt($this->quiz->id, $user->id);
        $quizgen->submit_responses($attempt->id, [1 => 'True'], false, true);
        $this->setAdminUser();
        $this->aggregate();
    }

    /**
     * Run the completion cron.
     */
    private function aggregate(): void {
        ob_start();
        (new \core\task\completion_regular_task())->execute();
        ob_end_clean();
    }

    /**
     * Whether the course is complete for the user, straight from the database.
     *
     * @param \stdClass $user
     * @return bool
     */
    private function course_complete(\stdClass $user): bool {
        global $DB;
        return (bool) $DB->get_field(
            'course_completions',
            'timecompleted',
            ['userid' => $user->id, 'course' => $this->course->id]
        );
    }

    public function test_setup_completes_the_course(): void {
        $this->assertTrue($this->course_complete($this->certified_learner()));
    }

    public function test_reset_does_not_spring_back_when_the_learner_opens_the_quiz(): void {
        $user = $this->certified_learner();
        resetter::reset((int) $this->course->id, (int) $user->id);

        // What happens when the learner opens the quiz to retake it, then cron runs.
        $completion = new completion_info($this->course);
        $cm = get_coursemodule_from_id('quiz', $this->quiz->cmid);
        $completion->update_state($cm, COMPLETION_UNKNOWN, $user->id);
        $this->aggregate();

        $this->assertSame(COMPLETION_INCOMPLETE, (int) $completion->get_data($cm, false, $user->id)->completionstate);
        $this->assertFalse($this->course_complete($user));
        $grades = grade_get_grades($this->course->id, 'mod', 'quiz', $this->quiz->id, $user->id);
        $this->assertNull($grades->items[0]->grades[$user->id]->grade, 'The quiz grade is gone.');
    }

    public function test_reset_archives_what_it_removes(): void {
        global $DB;
        $user = $this->certified_learner();
        $timecompleted = (int) $DB->get_field(
            'course_completions',
            'timecompleted',
            ['userid' => $user->id, 'course' => $this->course->id]
        );
        $historyid = resetter::reset((int) $this->course->id, (int) $user->id);

        $history = $DB->get_record('local_recertify_history', ['id' => $historyid]);
        $this->assertSame($timecompleted, (int) $history->timecompleted);
        $archive = json_decode($history->archive, true);
        $this->assertCount(1, $archive['quiz'][$this->quiz->cmid]);
        $this->assertEquals(1, $archive['quiz'][$this->quiz->cmid][0]['sumgrades']);
        $this->assertEquals(10, $archive['grades'][$this->quiz->cmid]);
        $this->assertEquals(COMPLETION_COMPLETE_PASS, $archive['completion'][$this->quiz->cmid]);
        $this->assertFalse($DB->record_exists('quiz_attempts', ['userid' => $user->id]));
    }

    public function test_the_learner_can_certify_again(): void {
        $user = $this->certified_learner();
        resetter::reset((int) $this->course->id, (int) $user->id);
        $this->pass_quiz($user);
        $this->assertTrue($this->course_complete($user));
    }

    public function test_other_learners_are_untouched(): void {
        global $DB;
        $expired = $this->certified_learner();
        $current = $this->certified_learner();
        resetter::reset((int) $this->course->id, (int) $expired->id);
        $this->assertTrue($this->course_complete($current));
        $this->assertTrue($DB->record_exists('quiz_attempts', ['userid' => $current->id]));
    }

    public function test_scorm_attempts_are_archived_and_removed(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        $user = $this->certified_learner();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $this->course->id]);
        $sco = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id, 'scormtype' => 'sco'], 'id', 'id', 0, 1);
        $scoid = (int) array_key_first($sco);
        scorm_insert_track($user->id, $scorm->id, $scoid, 1, 'cmi.core.lesson_status', 'passed');
        $historyid = resetter::reset((int) $this->course->id, (int) $user->id);

        $this->assertFalse($DB->record_exists('scorm_attempt', ['userid' => $user->id, 'scormid' => $scorm->id]));
        $archive = json_decode($DB->get_field('local_recertify_history', 'archive', ['id' => $historyid]), true);
        $this->assertSame('passed', $archive['scorm'][$scorm->cmid][0]['scos'][$scoid]['cmi.core.lesson_status']);
    }

    public function test_reset_without_a_completion_is_refused(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->expectException(\moodle_exception::class);
        resetter::reset((int) $this->course->id, (int) $user->id);
    }

    public function test_a_failed_reset_rolls_back_and_leaves_no_open_transaction(): void {
        global $DB;
        $user = $this->certified_learner();
        // A broken module record makes the reset fail halfway.
        $DB->delete_records('quiz', ['id' => $this->quiz->id]);
        // The test framework holds a transaction of its own, so count them: the reset must add none.
        $depth = fn() => count((new \ReflectionProperty(\moodle_database::class, 'transactions'))->getValue($DB));
        $before = $depth();
        try {
            resetter::reset((int) $this->course->id, (int) $user->id);
            $this->fail('Reset did not fail');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame($before, $depth());
        }
    }

    public function test_criteria_that_complete_by_themselves_block_resets(): void {
        global $DB;
        // The setup course needs the quiz: a reset holds.
        $this->assertNull(resetter::blocking_criteria((int) $this->course->id));
        // Add a date criterion with "any" aggregation: the date alone completes the course again.
        $DB->insert_record('course_completion_criteria', ['course' => $this->course->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_DATE, 'timeend' => time() - DAYSECS]);
        $DB->insert_record('course_completion_aggr_methd', ['course' => $this->course->id, 'criteriatype' => null,
            'method' => COMPLETION_AGGREGATION_ANY]);
        $this->assertSame('error:autocriteria', resetter::blocking_criteria((int) $this->course->id));
        // With "all", the quiz is still needed.
        $DB->set_field('course_completion_aggr_methd', 'method', COMPLETION_AGGREGATION_ALL, ['course' => $this->course->id]);
        $this->assertNull(resetter::blocking_criteria((int) $this->course->id));
        // A course whose only criterion is a date is blocked even with "all".
        $other = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $DB->insert_record('course_completion_criteria', ['course' => $other->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_DURATION, 'enrolperiod' => DAYSECS]);
        $this->assertSame('error:autocriteria', resetter::blocking_criteria((int) $other->id));
    }

    public function test_deleting_the_course_removes_history(): void {
        global $DB;
        $user = $this->certified_learner();
        resetter::reset((int) $this->course->id, (int) $user->id);
        delete_course($this->course->id, false);
        $this->assertFalse($DB->record_exists('local_recertify_history', ['courseid' => $this->course->id]));
    }
}
