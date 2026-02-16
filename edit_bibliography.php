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
 * Edit/add bibliography entry for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comeron <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('bibliography_form.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$bibliographyid = optional_param('bibliographyid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/bloquecero:managebibliography', $context);

$returnurl = new moodle_url('/blocks/bloquecero/manage_bibliography.php', ['courseid' => $courseid, 'blockid' => $blockid]);
$PAGE->set_url('/blocks/bloquecero/edit_bibliography.php', [
    'courseid' => $courseid,
    'blockid' => $blockid,
    'bibliographyid' => $bibliographyid
]);
$PAGE->set_context($context);
$PAGE->set_title(get_string($bibliographyid ? 'editbibliography' : 'addbibliography', 'block_bloquecero'));
$PAGE->set_heading($course->fullname);

// Load existing bibliography entry if editing.
$bibliography = null;
if ($bibliographyid) {
    $bibliography = $DB->get_record('block_bloquecero_bibliography', ['id' => $bibliographyid], '*', MUST_EXIST);
}

$mform = new bibliography_form(null, ['courseid' => $courseid, 'blockid' => $blockid, 'bibliographyid' => $bibliographyid]);

// Set form data if editing.
if ($bibliography) {
    $bibliography->courseid = $courseid;
    $bibliography->blockid = $blockid;
    $bibliography->bibliographyid = $bibliographyid;
    $mform->set_data($bibliography);
} else {
    // Set default data for new entry.
    $defaultdata = new stdClass();
    $defaultdata->courseid = $courseid;
    $defaultdata->blockid = $blockid;
    $defaultdata->bibliographyid = 0;
    $mform->set_data($defaultdata);
}

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    // Auto-prefix https:// if URL is provided without protocol.
    if (!empty($data->url)) {
        $data->url = trim($data->url);
        if (!preg_match('#^https?://#i', $data->url)) {
            $data->url = 'https://' . $data->url;
        }
    }

    if ($bibliographyid) {
        // Update existing entry.
        $data->id = $bibliographyid;
        $data->timemodified = $now;
        $DB->update_record('block_bloquecero_bibliography', $data);
    } else {
        // Create new entry.
        $data->blockinstanceid = $blockid;
        $data->courseid = $courseid;
        $data->timecreated = $now;
        $data->timemodified = $now;

        // Get max sortorder and add 1.
        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {block_bloquecero_bibliography} WHERE blockinstanceid = ? AND courseid = ?',
            [$blockid, $courseid]
        );
        $data->sortorder = ($maxsort !== null && $maxsort !== false) ? $maxsort + 1 : 0;

        $DB->insert_record('block_bloquecero_bibliography', $data);
    }

    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($bibliographyid ? 'editbibliography' : 'addbibliography', 'block_bloquecero'));
$mform->display();
echo $OUTPUT->footer();
