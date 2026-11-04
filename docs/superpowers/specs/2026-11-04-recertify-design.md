# local_recertify design (2026-11-04)

Demand: annual compliance retraining. local_recompletion became paid on the Moodle Marketplace;
mod_reengagement stops at 4.4; forum and tracker requests (MDL-35335, 36 votes) and Freelancer projects.

Core rule: expiry = course_completions.timecompleted + perioddays. On expiry the learner's data that
completion is computed from is archived (local_recertify_history.archive JSON) and removed through
module APIs: quiz_delete_attempt, scorm_delete_attempt, grade_grade::delete('local_recertify'), then
completion rows and the two completion cache keys. A completion-only reset springs back (proved by
test_reset_does_not_spring_back_when_the_learner_opens_the_quiz). Assignment resets are out of 0.1.

Reminders: nearest applicable day, once per (course, user, timecompleted, daysbefore); earlier days are
marked sent too. Only active enrolments are reminded or reset. Status page previews resets while off.
