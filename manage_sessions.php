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
 * Manage live sessions for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/bloquecero:managesessions', $context);

$PAGE->set_url('/blocks/bloquecero/manage_sessions.php', ['courseid' => $courseid, 'blockid' => $blockid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managesessions', 'block_bloquecero'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('managesessions', 'block_bloquecero'));

// Handle actions.
if ($action === 'delete' && $sessionid && confirm_sesskey()) {
    $session = $DB->get_record('block_bloquecero_sessions', ['id' => $sessionid], '*', MUST_EXIST);

    // Delete calendar event if exists.
    if ($session->calendarid) {
        $calendarevent = $DB->get_record('event', ['id' => $session->calendarid]);
        if ($calendarevent) {
            $calendarevent = calendar_event::load($calendarevent);
            $calendarevent->delete();
        }
    }

    $DB->delete_records('block_bloquecero_sessions', ['id' => $sessionid]);
    redirect($PAGE->url);
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('managesessions', 'block_bloquecero'));

// Add session button.
$addurl = new moodle_url('/blocks/bloquecero/edit_session.php', ['courseid' => $courseid, 'blockid' => $blockid]);
echo html_writer::link($addurl, get_string('addsession', 'block_bloquecero'), ['class' => 'btn btn-primary mb-3']);

// Get sessions for this course and block.
$sessions = $DB->get_records('block_bloquecero_sessions',
    ['courseid' => $courseid, 'blockinstanceid' => $blockid],
    'sessiondate ASC');

if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('nosessionsyet', 'block_bloquecero'), 'info');
} else {
    // Create table.
    $table = new html_table();
    $table->head = [
        get_string('sessionname', 'block_bloquecero'),
        get_string('sessiondate', 'block_bloquecero'),
        get_string('duration', 'block_bloquecero'),
        get_string('bookdescription', 'block_bloquecero'),
        get_string('syncedwithcalendar', 'block_bloquecero'),
        get_string('actions')
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($sessions as $session) {
        $editurl = new moodle_url('/blocks/bloquecero/edit_session.php', [
            'courseid' => $courseid,
            'blockid' => $blockid,
            'sessionid' => $session->id
        ]);
        $deleteurl = new moodle_url('/blocks/bloquecero/manage_sessions.php', [
            'courseid' => $courseid,
            'blockid' => $blockid,
            'action' => 'delete',
            'sessionid' => $session->id,
            'sesskey' => sesskey()
        ]);

        $datestr = userdate($session->sessiondate, get_string('strftimedaydatetime', 'langconfig'));
        $durationstr = format_time($session->duration);
        $synced = $session->calendarid ?
            get_string('yes') . ' ' . $OUTPUT->pix_icon('i/calendar', '', 'moodle') :
            get_string('no');

        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));
        $actions .= ' ' . html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')), [
            'onclick' => 'return confirm("' . get_string('confirmdeletesession', 'block_bloquecero') . '");'
        ]);

        $descriptionstr = !empty($session->description) ? shorten_text(strip_tags($session->description), 80) : '—';

        $table->data[] = [
            format_string($session->name),
            $datestr,
            $durationstr,
            $descriptionstr,
            $synced,
            $actions
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
