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

/**
 * English strings for local_recertify.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['certificationsource'] = 'Past certifications';
$string['completed'] = 'Completed';
$string['enabled'] = 'Recertification on';
$string['enabled_help'] = 'When on, a learner\'s certification expires the set number of days after they complete the course. Their completion, grades, quiz attempts and SCORM attempts in this course are archived and reset, and they are asked to complete it again. Assignment submissions are not reset.';
$string['entitycertification'] = 'Past certification';
$string['error:notcompleted'] = 'This learner has not completed the course, so there is nothing to recertify.';
$string['error:period'] = 'Enter at least 1 day.';
$string['event:expired'] = 'Certification expired';
$string['expired'] = 'Expired';
$string['expires'] = 'Expires';
$string['finalgrade'] = 'Course total at expiry';
$string['learner'] = 'Learner';
$string['message:expired'] = 'Your certification for {$a} has expired. Your progress in the course was reset so you can complete it again.';
$string['message:expired:subject'] = 'Your certification for {$a} has expired';
$string['message:reminder'] = 'Your certification for {$a->course} expires on {$a->date}. Complete the course again before then to stay certified.';
$string['message:reminder:subject'] = 'Your certification for {$a->course} expires on {$a->date}';
$string['messageprovider:expired'] = 'Your certification expired and the course was reset';
$string['messageprovider:reminder'] = 'Your certification is about to expire';
$string['nextweek'] = '{$a} learner(s) will be reset in the next 7 days.';
$string['nextweekoff'] = 'If you turn it on, {$a} learner(s) would be reset in the next 7 days.';
$string['notenabled'] = 'Recertification is off in this course. Nothing is reset or sent. The table shows what would happen.';
$string['past'] = 'Past certifications';
$string['perioddays'] = 'Valid for (days)';
$string['perioddays_help'] = 'How many days a course completion counts as a valid certification. 365 for a yearly refresher.';
$string['pluginname'] = 'Recertification';
$string['privacy:metadata:core_message'] = 'Reminders and expiry notices are sent as Moodle notifications.';
$string['privacy:metadata:history'] = 'Archived certifications: what a learner had done when their certification expired.';
$string['privacy:metadata:history:archive'] = 'Activity completion, grades, quiz attempts and SCORM tracking at the time of expiry.';
$string['privacy:metadata:history:finalgrade'] = 'The course total at expiry.';
$string['privacy:metadata:history:timecompleted'] = 'When the learner completed the course.';
$string['privacy:metadata:history:timeexpired'] = 'When the certification expired and the course was reset.';
$string['privacy:metadata:history:userid'] = 'The learner.';
$string['privacy:metadata:notice'] = 'Reminders sent before a certification expired.';
$string['privacy:metadata:notice:daysbefore'] = 'How many days before expiry the reminder was for.';
$string['privacy:metadata:notice:timesent'] = 'When the reminder was sent.';
$string['privacy:metadata:notice:userid'] = 'The learner.';
$string['recertify:manage'] = 'Change recertification settings in a course';
$string['recertify:viewall'] = 'Report on certifications across the site';
$string['recertify:viewstatus'] = 'See learners\' recertification status in a course';
$string['reminderdays'] = 'Reminders (days before)';
$string['reminderdays_help'] = 'Comma separated, for example 30,7. Each learner gets the nearest reminder that applies, once.';
$string['settings'] = 'Settings';
$string['state:expired'] = 'expired, waiting for a retake';
$string['state:expiring'] = 'expiring soon';
$string['state:none'] = 'not completed';
$string['state:valid'] = 'valid';
$string['status'] = 'Status';
$string['summary'] = '{$a->valid} valid, {$a->expiring} expiring soon, {$a->expired} expired, {$a->none} not completed. Certifications last {$a->period} days.';
$string['task:process'] = 'Send recertification reminders and reset expired certifications';
