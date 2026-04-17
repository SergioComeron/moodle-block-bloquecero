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
 * Restore step for block_bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Defines the restore structure for block_bloquecero.
 */
class restore_bloquecero_block_structure_step extends restore_structure_step {
    /**
     * Defines the XML paths to process during restore.
     */
    protected function define_structure() {
        return [
            new restore_path_element('block', '/block', true),
            new restore_path_element('bloquecero_session', '/block/bloquecero/sessions/session'),
            new restore_path_element('bloquecero_bibliography', '/block/bloquecero/bibliographies/bibliography'),
        ];
    }

    /**
     * Required by the API but block-level processing is handled by the parent.
     */
    public function process_block($data) {
    }

    /**
     * Restores a live session record and recreates the calendar event if sync was active.
     */
    public function process_bloquecero_session($data) {
        global $DB;

        if (!$this->task->get_blockid()) {
            return;
        }

        $data = (object)$data;
        $hadcalendarsync       = !empty($data->hadcalendarsync);
        $data->blockinstanceid = $this->task->get_blockid();
        $data->courseid        = $this->task->get_courseid();
        $data->calendarid      = null;
        $data->timecreated     = isset($data->timecreated) ? (int)$data->timecreated : time();
        $data->timemodified    = time();
        unset($data->id, $data->hadcalendarsync);

        $newid = $DB->insert_record('block_bloquecero_sessions', $data);

        if ($hadcalendarsync) {
            $event = new stdClass();
            $event->name        = $data->name;
            $event->description = $data->description ?? '';
            $event->format      = FORMAT_HTML;
            $event->courseid    = $data->courseid;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = '';
            $event->instance    = 0;
            $event->eventtype   = 'course';
            $event->timestart   = (int)$data->sessiondate;
            $event->timeduration = (int)$data->duration;
            $event->visible     = 1;

            $calendarevent = calendar_event::create($event, false);
            $DB->set_field('block_bloquecero_sessions', 'calendarid', $calendarevent->id, ['id' => $newid]);
        }
    }

    /**
     * Restores a bibliography entry.
     */
    public function process_bloquecero_bibliography($data) {
        global $DB;

        if (!$this->task->get_blockid()) {
            return;
        }

        $data = (object)$data;
        $data->blockinstanceid = $this->task->get_blockid();
        $data->courseid        = $this->task->get_courseid();
        $data->timecreated     = isset($data->timecreated) ? (int)$data->timecreated : time();
        $data->timemodified    = time();
        unset($data->id);

        $DB->insert_record('block_bloquecero_bibliography', $data);
    }
}
