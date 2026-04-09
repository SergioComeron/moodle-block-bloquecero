<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing live sessions
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding/editing a live session.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Session name.
        $mform->addElement('text', 'name', get_string('sessionname', 'block_bloquecero'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Session date and time.
        $mform->addElement('date_time_selector', 'sessiondate', get_string('sessiondate', 'block_bloquecero'));
        $mform->addRule('sessiondate', get_string('required'), 'required', null, 'client');

        // Duration.
        $mform->addElement('duration', 'duration', get_string('duration', 'block_bloquecero'), ['optional' => false]);
        $mform->setDefault('duration', 3600); // 1 hour default.
        $mform->addHelpButton('duration', 'duration', 'block_bloquecero');

        // Description.
        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        // Sync with calendar.
        $mform->addElement('advcheckbox', 'synccalendar', get_string('synccalendar', 'block_bloquecero'));
        $mform->setDefault('synccalendar', 1);
        $mform->addHelpButton('synccalendar', 'synccalendar', 'block_bloquecero');

        // Hidden fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'blockid');
        $mform->setType('blockid', PARAM_INT);

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        return parent::validation($data, $files);
    }
}
