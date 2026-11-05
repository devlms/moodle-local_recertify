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
use local_recertify\local\settings;

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
$tab = $canmanage ? optional_param('tab', 'status', PARAM_ALPHA) : 'status';
$form = null;
if ($canmanage) {
    $form = new settings_form(new moodle_url($url, ['tab' => 'settings']), null, 'post', '', ['class' => 'full-width-labels']);
    $form->set_data(['courseid' => $courseid, 'enabled' => $cfg->enabled, 'perioddays' => $cfg->perioddays,
        'reminderdays' => $cfg->reminderdays]);
    if ($data = $form->get_data()) {
        settings::save($courseid, (bool) $data->enabled, (int) $data->perioddays, explode(',', (string) $data->reminderdays));
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$learners = get_enrolled_users($context, 'moodle/course:isincompletionreports', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true);
$page = new \local_recertify\output\status_page(
    $course,
    $cfg,
    array_values($learners),
    time(),
    optional_param('state', '', PARAM_ALPHA),
    $canmanage
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_recertify'));
if ($canmanage) {
    $tabs = [];
    foreach (['status' => 'learners', 'settings' => 'settings'] as $name => $label) {
        $tabs[] = new tabobject($name, new moodle_url($url, ['tab' => $name]), get_string($label, 'local_recertify'));
    }
    echo html_writer::div($OUTPUT->tabtree($tabs, $tab), 'local-recertify-tabs');
}
if ($tab === 'settings') {
    echo html_writer::start_div('local-recertify-settings');
    $form->display();
    echo html_writer::end_div();
} else {
    echo $OUTPUT->render_from_template('local_recertify/status', $page->export_for_template($OUTPUT));
}
echo $OUTPUT->footer();
