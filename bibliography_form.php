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
 * Form for editing bibliography entries
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comeron <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding/editing a bibliography entry.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bibliography_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Book/resource name.
        $mform->addElement('text', 'name', get_string('bookname', 'block_bloquecero'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // URL.
        $mform->addElement('text', 'url', get_string('bookurl', 'block_bloquecero'), ['size' => 60]);
        $mform->setType('url', PARAM_RAW);
        $mform->addHelpButton('url', 'bookurl', 'block_bloquecero');

        // Description.
        $mform->addElement('textarea', 'description', get_string('bookdescription', 'block_bloquecero'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        // Hidden fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'blockid');
        $mform->setType('blockid', PARAM_INT);

        $mform->addElement('hidden', 'bibliographyid');
        $mform->setType('bibliographyid', PARAM_INT);

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

        // Validate URL format if provided.
        if (!empty($data['url'])) {
            $url = trim($data['url']);
            // Auto-prefix https:// if missing protocol for validation.
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
