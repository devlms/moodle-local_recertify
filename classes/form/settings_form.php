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

namespace local_recertify\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Recertification settings for a course.
 *
 * @package    local_recertify
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {
    /**
     * Define the fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('header', 'settingshdr', get_string('settings', 'local_recertify'));
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_recertify'));
        $mform->addHelpButton('enabled', 'enabled', 'local_recertify');
        $mform->addElement('text', 'perioddays', get_string('perioddays', 'local_recertify'), ['size' => 6]);
        $mform->setType('perioddays', PARAM_INT);
        $mform->addRule('perioddays', null, 'required', null, 'client');
        $mform->addHelpButton('perioddays', 'perioddays', 'local_recertify');
        $mform->addElement('text', 'reminderdays', get_string('reminderdays', 'local_recertify'), ['size' => 20]);
        $mform->setType('reminderdays', PARAM_SEQUENCE);
        $mform->addHelpButton('reminderdays', 'reminderdays', 'local_recertify');
        $this->add_action_buttons(false);
    }

    /**
     * The period must be at least a day.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((int) $data['perioddays'] < 1) {
            $errors['perioddays'] = get_string('error:period', 'local_recertify');
        }
        return $errors;
    }
}
