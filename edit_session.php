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
 * Edit/add live session for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once('session_form.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/bloquecero:managesessions', $context);

$returnurl = new moodle_url('/blocks/bloquecero/manage_sessions.php', ['courseid' => $courseid, 'blockid' => $blockid]);
$PAGE->set_url('/blocks/bloquecero/edit_session.php', ['courseid' => $courseid, 'blockid' => $blockid, 'sessionid' => $sessionid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string($sessionid ? 'editsession' : 'addsession', 'block_bloquecero'));
$PAGE->set_heading($course->fullname);

// Load existing session if editing.
$session = null;
if ($sessionid) {
    $session = $DB->get_record('block_bloquecero_sessions', ['id' => $sessionid], '*', MUST_EXIST);
}

$mform = new session_form(null, ['courseid' => $courseid, 'blockid' => $blockid, 'sessionid' => $sessionid]);

// Set form data if editing.
if ($session) {
    $session->courseid = $courseid;
    $session->blockid = $blockid;
    $session->sessionid = $sessionid;
    $mform->set_data($session);
} else {
    // Set default data for new session.
    $defaultdata = new stdClass();
    $defaultdata->courseid = $courseid;
    $defaultdata->blockid = $blockid;
    $defaultdata->sessionid = 0;
    $mform->set_data($defaultdata);
}

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    if ($sessionid) {
        // Update existing session.
        $data->id = $sessionid;
        $data->timemodified = $now;
        $DB->update_record('block_bloquecero_sessions', $data);

        // Update calendar event if exists and sync is enabled.
        if ($data->synccalendar && $session->calendarid) {
            $calendarevent = $DB->get_record('event', ['id' => $session->calendarid]);
            if ($calendarevent) {
                $calendarevent = calendar_event::load($calendarevent);
                $calendarevent->name = $data->name;
                $calendarevent->description = $data->description;
                $calendarevent->timestart = $data->sessiondate;
                $calendarevent->timeduration = $data->duration;
                $calendarevent->update($calendarevent, false);
            }
        } else if ($data->synccalendar && !$session->calendarid) {
            // Create calendar event if sync enabled but doesn't exist.
            $calendarid = block_bloquecero_create_calendar_event($data, $courseid);
            $DB->set_field('block_bloquecero_sessions', 'calendarid', $calendarid, ['id' => $sessionid]);
        } else if (!$data->synccalendar && $session->calendarid) {
            // Delete calendar event if sync disabled.
            $calendarevent = $DB->get_record('event', ['id' => $session->calendarid]);
            if ($calendarevent) {
                $calendarevent = calendar_event::load($calendarevent);
                $calendarevent->delete();
            }
            $DB->set_field('block_bloquecero_sessions', 'calendarid', null, ['id' => $sessionid]);
        }

    } else {
        // Create new session.
        $data->blockinstanceid = $blockid;
        $data->courseid = $courseid;
        $data->timecreated = $now;
        $data->timemodified = $now;
        $data->calendarid = null;

        $sessionid = $DB->insert_record('block_bloquecero_sessions', $data);

        // Create calendar event if sync enabled.
        if ($data->synccalendar) {
            $data->id = $sessionid;
            $calendarid = block_bloquecero_create_calendar_event($data, $courseid);
            $DB->set_field('block_bloquecero_sessions', 'calendarid', $calendarid, ['id' => $sessionid]);
        }
    }

    // Destroy form object and clear session data before redirect to prevent session mutation warning.
    unset($mform);
    global $SESSION;
    if (isset($SESSION->editedpages)) {
        unset($SESSION->editedpages);
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($sessionid ? 'editsession' : 'addsession', 'block_bloquecero'));
$mform->display();
echo $OUTPUT->footer();

/**
 * Create a calendar event for a session
 *
 * @param stdClass $session Session data
 * @param int $courseid Course ID
 * @return int Calendar event ID
 */
function block_bloquecero_create_calendar_event($session, $courseid) {
    $event = new stdClass();
    $event->name = $session->name;
    $event->description = $session->description ?? '';
    $event->format = FORMAT_HTML;
    $event->courseid = $courseid;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = '';
    $event->instance = 0;
    $event->eventtype = 'course';
    $event->timestart = $session->sessiondate;
    $event->timeduration = $session->duration;
    $event->visible = 1;

    $calendarevent = calendar_event::create($event, false);
    return $calendarevent->id;
}
