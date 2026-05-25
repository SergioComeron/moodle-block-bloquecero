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
 * Form for editing teaching guide entries
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding/editing a teaching guide entry.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guide_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Label (optional).
        $mform->addElement('text', 'name', get_string('guide_label', 'block_bloquecero'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'guide_label', 'block_bloquecero');

        // URL (required).
        $mform->addElement('text', 'url', get_string('guide_url', 'block_bloquecero'), ['size' => 60]);
        $mform->setType('url', PARAM_RAW);
        $mform->addRule('url', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('url', 'guide_url', 'block_bloquecero');

        // Hidden fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'blockid');
        $mform->setType('blockid', PARAM_INT);

        $mform->addElement('hidden', 'guideid');
        $mform->setType('guideid', PARAM_INT);

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
        $errors = parent::validation($data, $files);

        if (!empty($data['url'])) {
            $url = trim($data['url']);
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors['url'] = get_string('invalidurl', 'block_bloquecero');
            }
        }

        return $errors;
    }
}
