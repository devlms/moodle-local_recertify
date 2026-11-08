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

use cm_info;
use grade_grade;
use grade_item;
use moodle_exception;

/**
 * Resets one learner's progress in a course so they can certify again, after archiving it.
 *
 * Clearing completion alone is not enough: Moodle recomputes activity completion from the
 * module's own data (grades, quiz attempts, SCORM tracks) the next time the learner opens the
 * activity, and the old completion comes straight back. So the reset removes the data completion
 * is computed from, through each module's own API, after copying it into the history table.
 *
 * Covered: activity and course completion, "viewed" records, gradebook grades, quiz attempts and
 * SCORM attempts. Assignment submissions are not reset yet.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resetter {
    /**
     * Archive and reset a learner who completed the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return int The history row id.
     */
    public static function reset(int $courseid, int $userid): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        $timecompleted = (int) $DB->get_field(
            'course_completions',
            'timecompleted',
            ['userid' => $userid, 'course' => $courseid]
        );
        if (!$timecompleted) {
            throw new moodle_exception('error:notcompleted', 'local_recertify');
        }
        $modinfo = get_fast_modinfo($courseid);

        $transaction = $DB->start_delegated_transaction();
        try {
            $historyid = self::reset_in_transaction($courseid, $userid, $timecompleted, $modinfo);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Never leave a transaction open: later resets in the same cron run would be lost with it.
            $transaction->rollback($e);
        }

        \cache::make('core', 'completion')->delete($userid . '_' . $courseid);
        \cache::make('core', 'coursecompletion')->delete($userid . '_' . $courseid);
        return $historyid;
    }

    /**
     * Why completion would come straight back after a reset, as a string key, or null when a reset holds.
     *
     * Moodle's completion cron re-marks date, enrolment duration and "another course" criteria by itself.
     * When those alone can complete the course (any criteria is enough, or they are the only criteria),
     * a reset learner is complete again within a day without retaking anything.
     *
     * @param int $courseid
     * @return string|null
     */
    public static function blocking_criteria(int $courseid): ?string {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $types = array_map('intval', $DB->get_fieldset_select(
            'course_completion_criteria',
            'criteriatype',
            'course = ?',
            [$courseid]
        ));
        if (!$types) {
            return null;
        }
        $auto = [COMPLETION_CRITERIA_TYPE_DATE, COMPLETION_CRITERIA_TYPE_DURATION, COMPLETION_CRITERIA_TYPE_COURSE];
        $autotypes = array_intersect($types, $auto);
        if (!$autotypes) {
            return null;
        }
        $method = (int) $DB->get_field_select(
            'course_completion_aggr_methd',
            'method',
            'course = ? AND criteriatype IS NULL',
            [$courseid]
        );
        $any = $method === COMPLETION_AGGREGATION_ANY;
        if ($any || count($autotypes) === count($types)) {
            return 'error:autocriteria';
        }
        return null;
    }

    /**
     * Archive and reset inside the caller's transaction.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $timecompleted
     * @param \course_modinfo $modinfo
     * @return int The history row id.
     */
    private static function reset_in_transaction(int $courseid, int $userid, int $timecompleted, \course_modinfo $modinfo): int {
        global $DB;
        // Inside the transaction, so an attempt submitted meanwhile is either archived or kept.
        [$archive, $finalgrade] = self::snapshot($courseid, $userid, $modinfo);
        $historyid = (int) $DB->insert_record('local_recertify_history', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'timecompleted' => $timecompleted,
            'timeexpired' => time(),
            'finalgrade' => $finalgrade,
            'archive' => json_encode($archive),
        ]);

        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            $quiz->cmid = $cm->id;
            foreach ($DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $userid]) as $attempt) {
                quiz_delete_attempt($attempt, $quiz);
            }
        }
        foreach ($modinfo->get_instances_of('scorm') as $cm) {
            $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
            foreach ($DB->get_records('scorm_attempt', ['scormid' => $scorm->id, 'userid' => $userid]) as $attempt) {
                scorm_delete_attempt($userid, $scorm, $attempt);
            }
        }
        // Every grade in the course, the course total included, through the grade API so the gradebook
        // history records who removed it.
        foreach (grade_item::fetch_all(['courseid' => $courseid]) ?: [] as $item) {
            if ($grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid])) {
                $grade->delete('local_recertify');
            }
            if ($item->is_course_item()) {
                $item->force_regrading();
            }
        }

        $cmsql = 'coursemoduleid IN (SELECT id FROM {course_modules} WHERE course = :courseid) AND userid = :userid';
        $params = ['courseid' => $courseid, 'userid' => $userid];
        $DB->delete_records_select('course_modules_completion', $cmsql, $params);
        $DB->delete_records_select('course_modules_viewed', $cmsql, $params);
        $DB->delete_records('course_completion_crit_compl', ['course' => $courseid, 'userid' => $userid]);
        $DB->delete_records('course_completions', ['course' => $courseid, 'userid' => $userid]);
        return $historyid;
    }

    /**
     * Copy everything the reset removes.
     *
     * @param int $courseid
     * @param int $userid
     * @param \course_modinfo $modinfo
     * @return array [archive, course total or null]
     */
    private static function snapshot(int $courseid, int $userid, \course_modinfo $modinfo): array {
        global $DB;
        $archive = ['completion' => [], 'grades' => [], 'quiz' => [], 'scorm' => []];
        $rows = $DB->get_records_sql(
            'SELECT cmc.coursemoduleid, cmc.completionstate
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = ? AND cmc.userid = ?',
            [$courseid, $userid]
        );
        foreach ($rows as $row) {
            $archive['completion'][$row->coursemoduleid] = (int) $row->completionstate;
        }

        $finalgrade = null;
        foreach (grade_item::fetch_all(['courseid' => $courseid]) ?: [] as $item) {
            $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
            if (!$grade || $grade->finalgrade === null) {
                continue;
            }
            if ($item->is_course_item()) {
                $finalgrade = (float) $grade->finalgrade;
            } else if ($item->itemtype === 'mod' && isset($modinfo->instances[$item->itemmodule][$item->iteminstance])) {
                $cm = $modinfo->instances[$item->itemmodule][$item->iteminstance];
                $archive['grades'][$cm->id] = (float) $grade->finalgrade;
            }
        }

        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            $attempts = $DB->get_records('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $userid], 'attempt');
            foreach ($attempts as $attempt) {
                $archive['quiz'][$cm->id][] = [
                    'attempt' => (int) $attempt->attempt,
                    'state' => $attempt->state,
                    'sumgrades' => $attempt->sumgrades === null ? null : (float) $attempt->sumgrades,
                    'timestart' => (int) $attempt->timestart,
                    'timefinish' => (int) $attempt->timefinish,
                ];
            }
        }
        foreach ($modinfo->get_instances_of('scorm') as $cm) {
            self::snapshot_scorm($cm, $userid, $archive);
        }
        return [$archive, $finalgrade];
    }

    /**
     * Copy a learner's SCORM tracking values, by attempt and by SCO.
     *
     * @param cm_info $cm
     * @param int $userid
     * @param array $archive
     */
    private static function snapshot_scorm(cm_info $cm, int $userid, array &$archive): void {
        global $DB;
        foreach ($DB->get_records('scorm_attempt', ['scormid' => $cm->instance, 'userid' => $userid], 'attempt') as $attempt) {
            $scos = [];
            $values = $DB->get_recordset_sql(
                'SELECT v.id, v.scoid, e.element, v.value
                   FROM {scorm_scoes_value} v
                   JOIN {scorm_element} e ON e.id = v.elementid
                  WHERE v.attemptid = ?',
                [$attempt->id]
            );
            foreach ($values as $value) {
                $scos[$value->scoid][$value->element] = $value->value;
            }
            $values->close();
            $archive['scorm'][$cm->id][] = ['attempt' => (int) $attempt->attempt, 'scos' => $scos];
        }
    }
}
