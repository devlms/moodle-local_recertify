# Recertification for Moodle (local_recertify)

[![CI](https://github.com/devlms/moodle-local_recertify/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/devlms/moodle-local_recertify/actions/workflows/ci.yml)
[![PHPUnit + Behat](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-local_recertify%2Fbadges%2Fmoodle.json)](https://devlms.github.io/moodle-local_recertify/)
[![PHP coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-local_recertify%2Fbadges%2Fphp-coverage.json)](https://devlms.github.io/moodle-local_recertify/php-coverage/)
[![Moodle](https://img.shields.io/badge/Moodle-4.5%20to%205.3-f98012)](https://moodledev.io/general/releases)
[![PHP](https://img.shields.io/badge/PHP-8.1%20to%208.4-777bb4)](https://www.php.net/supported-versions.php)
[![License: GPL v3](https://img.shields.io/badge/license-GPL%20v3-blue)](LICENSE)
[![Maturity](https://img.shields.io/badge/maturity-stable-green)](version.php)

Make course completions expire. A year after a learner completes a mandatory course (fire safety, safeguarding, data protection, any annual refresher), their certification expires: Moodle archives what they did, resets their progress in that course, and asks them to complete it again. Reminders go out before it expires.

## Why the reset is harder than it looks

Clearing a learner's completion is not enough. Moodle recalculates activity completion from the module's own data the next time the learner opens the activity: the old quiz grade, the old attempts, the old SCORM tracks. A reset that only clears completion undoes itself the moment the learner starts the retake, and the course shows them as certified again without a retake. This plugin removes the data completion is calculated from, through each module's own API, after archiving it. A test proves the reset holds after the learner opens the quiz and cron runs.

## What it does

| Part | Status |
| --- | --- |
| Per course: on or off, how many days a completion stays valid, reminder days (for example 30 and 7) | Done |
| Daily task: reminders, then expiry for every course that has it on | Done |
| Reminders: each learner gets the nearest reminder that applies, once, even if cron missed days | Done |
| On expiry: archive activity completion, grades, the course total, quiz attempts and SCORM tracking, then reset them | Done |
| Quiz attempts and SCORM attempts removed through mod_quiz and mod_scorm, so their logs and gradebook updates happen | Done |
| Gradebook grades removed through the grade API, so the grade history shows the deletion | Done |
| Status page per course: valid, expiring soon, expired, not completed, past certifications, and who would be reset in the next 7 days, even while it is off | Done |
| Learners notified when a certification expires; a log event for each expiry | Done |
| Report builder source "Past certifications" for compliance reports across the site | Done |
| Privacy API for the history and reminders | Done |
| Safe resets: each reset is one transaction, and a failure rolls back without touching the next learner | Done |
| Courses that a date, an enrolment duration or another course can complete on their own are refused, because Moodle would mark them complete again by itself | Done |
| At most 200 resets per run, so turning it on for an old course spreads the work over several days | Done |
| Assignment, lesson, H5P, workshop and LTI data | Not reset. Their grades are cleared from the gradebook, but submissions stay, so completion based on them can come back when the activity is graded or re-checked. Use quizzes, SCORM, or manual and view completion for recertification courses. |

## Install

Needs Moodle 4.5 to 5.3 with cron running and completion tracking on.

1. Download the zip from [Releases](https://github.com/devlms/moodle-local_recertify/releases) and install it under Site administration, Plugins, Install plugins. Or clone it:

   ```bash
   # Moodle 4.5 and 5.0
   git clone https://github.com/devlms/moodle-local_recertify.git local/recertify
   # Moodle 5.1 and later
   git clone https://github.com/devlms/moodle-local_recertify.git public/local/recertify
   ```

2. Visit Site administration, Notifications to finish the install.
3. In a course with course completion set up, open "Recertification" from the course navigation (More menu). Check the status table first: it shows who would be reset before you switch anything on.

## Permissions

| Capability | Default roles | Allows |
| --- | --- | --- |
| `local/recertify:viewstatus` | Teacher, non-editing teacher, manager | See learners' status in the course |
| `local/recertify:manage` | Teacher, manager | Change the course's settings |
| `local/recertify:viewall` | Manager (site) | Build site-wide reports on past certifications |

## Tests

PHPUnit covers the reset (including the spring-back case: a reset that holds after the learner reopens the quiz), the archive, retaking and certifying again, other learners left alone, SCORM, the reminder schedule and its catch-up rule, the daily task, the privacy provider and the report source. A Behat scenario covers the status page and settings. CI runs on Moodle 4.5 to 5.3 with PostgreSQL and MariaDB: [reports](https://devlms.github.io/moodle-local_recertify/).

## Who made it

[DevLMS](https://devlms.com) builds custom Moodle plugins. Need recertification wired to your HR system, per-role periods, or assignment resets? [Get a quote](https://devlms.com/#quote).

## License

GNU GPL v3 or later.
