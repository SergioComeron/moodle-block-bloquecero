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
 * Edit/add teaching guide entry for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php');
require_once('guide_form.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid  = required_param('blockid', PARAM_INT);
$guideid  = optional_param('guideid', 0, PARAM_INT);

$course        = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/bloquecero:manageguides', $context);

$returnurl = new moodle_url('/blocks/bloquecero/manage_guides.php', ['courseid' => $courseid, 'blockid' => $blockid]);
$PAGE->set_url('/blocks/bloquecero/edit_guide.php', [
    'courseid' => $courseid,
    'blockid'  => $blockid,
    'guideid'  => $guideid,
]);
$PAGE->set_context($context);
$PAGE->set_title(get_string($guideid ? 'editguide' : 'addguide', 'block_bloquecero'));
$PAGE->set_heading($course->fullname);

$guide = null;
if ($guideid) {
    $guide = $DB->get_record('block_bloquecero_guides', ['id' => $guideid], '*', MUST_EXIST);
}

$mform = new guide_form(null, ['courseid' => $courseid, 'blockid' => $blockid, 'guideid' => $guideid]);

if ($guide) {
    $guide->courseid = $courseid;
    $guide->blockid  = $blockid;
    $guide->guideid  = $guideid;
    $mform->set_data($guide);
} else {
    $defaultdata          = new stdClass();
    $defaultdata->courseid = $courseid;
    $defaultdata->blockid  = $blockid;
    $defaultdata->guideid  = 0;
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

    if ($guideid) {
        $data->id           = $guideid;
        $data->timemodified = $now;
        $DB->update_record('block_bloquecero_guides', $data);
    } else {
        $data->blockinstanceid = $blockid;
        $data->courseid        = $courseid;
        $data->timecreated     = $now;
        $data->timemodified    = $now;

        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {block_bloquecero_guides} WHERE blockinstanceid = ? AND courseid = ?',
            [$blockid, $courseid]
        );
        $data->sortorder = ($maxsort !== null && $maxsort !== false) ? $maxsort + 1 : 0;

        $DB->insert_record('block_bloquecero_guides', $data);
    }

    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($guideid ? 'editguide' : 'addguide', 'block_bloquecero'));
$mform->display();
echo $OUTPUT->footer();
