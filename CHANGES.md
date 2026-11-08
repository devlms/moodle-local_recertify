# Changes

## 1.0.0 (2026-11-08)

First stable release.

- A reset that fails rolls back cleanly. Before, the open transaction swallowed every later reset in the same run and failed again every night.
- The snapshot of what a reset removes is taken inside the same transaction.
- Courses whose completion can come back by itself (a date, an enrolment duration or another course is enough) cannot be turned on, and the task skips them with a message.
- At most 200 resets per run across the site.
- Deleting a course removes its settings, history and reminder records.
- Course names with "&" show correctly in reminder and expiry messages.
- Privacy: the completion time on reminder records is declared.
- The README says which activities are not reset.
- CI covers Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 on PostgreSQL and MariaDB.

## 0.1.0 (2026-11-04)

First alpha.
