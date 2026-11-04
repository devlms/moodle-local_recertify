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
 * Recertification status of every learner in a course, and the settings.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_recertify\form\settings_form;
use local_recertify\local\{schedule, settings};

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/recertify:viewstatus', $context);
$canmanage = has_capability('local/recertify:manage', $context);

$url = new moodle_url('/local/recertify/status.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_recertify'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$cfg = settings::get($courseid);
$form = null;
if ($canmanage) {
    $form = new settings_form($url);
    $form->set_data(['courseid' => $courseid, 'enabled' => $cfg->enabled, 'perioddays' => $cfg->perioddays,
        'reminderdays' => $cfg->reminderdays]);
    if ($data = $form->get_data()) {
        settings::save($courseid, (bool) $data->enabled, (int) $data->perioddays, explode(',', (string) $data->reminderdays));
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$now = time();
$rows = [];
$counts = ['valid' => 0, 'expiring' => 0, 'expired' => 0, 'none' => 0];
$nextweek = 0;
$learners = get_enrolled_users($context, 'moodle/course:isincompletionreports', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true);
foreach ($learners as $user) {
    $status = schedule::status($cfg, (int) $user->id, $now);
    $counts[$status->state]++;
    if ($status->expires && $status->expires <= $now + 7 * schedule::DAY) {
        $nextweek++;
    }
    $rows[] = [
        'name' => fullname($user),
        'state' => get_string('state:' . $status->state, 'local_recertify'),
        'statekey' => $status->state,
        'completed' => $status->timecompleted ? userdate($status->timecompleted, get_string('strftimedate', 'langconfig')) : '',
        'expires' => $status->expires ? userdate($status->expires, get_string('strftimedate', 'langconfig')) : '',
        'past' => $status->certifications,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_recertify'));
if (!$cfg->enabled) {
    echo $OUTPUT->notification(get_string('notenabled', 'local_recertify', $nextweek), 'info', false);
}
echo $OUTPUT->render_from_template('local_recertify/status', [
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'summary' => get_string('summary', 'local_recertify', (object) ($counts + ['period' => $cfg->perioddays])),
    'nextweek' => get_string($cfg->enabled ? 'nextweek' : 'nextweekoff', 'local_recertify', $nextweek),
]);
if ($form) {
    $form->display();
}
echo $OUTPUT->footer();
