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
    /** @var array Maps old section ID => section number from the backup XML. */
    protected $sectionoldidtonumber = [];

    /**
     * Defines the XML paths to process during restore.
     */
    protected function define_structure() {
        return [
            new restore_path_element('bloquecero_session', '/block/bloquecero/sessions/session'),
            new restore_path_element('bloquecero_bibliography', '/block/bloquecero/bibliographies/bibliography'),
            new restore_path_element('bloquecero_sectionentry', '/block/bloquecero/sectionmapping/sectionentry'),
        ];
    }

    /**
     * Collects old section ID → section number from the backup XML.
     * Used later in after_execute() to remap configdata keys by position.
     */
    public function process_bloquecero_sectionentry($data) {
        $data = (object)$data;
        file_put_contents('/tmp/bloquecero_restore.log', '[sectionentry] id=' . $data->id . ' number=' . $data->number . PHP_EOL, FILE_APPEND);
        $this->sectionoldidtonumber[(int)$data->id] = (int)$data->number;
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

    /**
     * Remaps section IDs in configdata using section numbers collected from the backup XML.
     *
     * Keys like section_enabled_X / section_start_X / section_end_X contain old section IDs.
     * We map them to destination section IDs by matching section position numbers,
     * which avoids any dependency on backup_ids_temp.
     */
    protected function after_execute() {
        global $DB;

        $blockid = $this->task->get_blockid();
        file_put_contents('/tmp/bloquecero_restore.log', '[after_execute] blockid=' . $blockid
            . ' sectionmap_count=' . count($this->sectionoldidtonumber) . PHP_EOL, FILE_APPEND);

        if (!$blockid || empty($this->sectionoldidtonumber)) {
            return;
        }

        $configdata = $DB->get_field('block_instances', 'configdata', ['id' => $blockid]);
        if (empty($configdata)) {
            return;
        }

        $config = unserialize_object(base64_decode($configdata));
        if (!is_object($config)) {
            return;
        }

        // Get destination course sections indexed by section number.
        $courseid = $this->task->get_courseid();
        file_put_contents('/tmp/bloquecero_restore.log', '[after_execute] courseid=' . $courseid
            . ' destsections_count=' . count($DB->get_records_menu('course_sections', ['course' => $courseid], '', 'section, id'))
            . PHP_EOL, FILE_APPEND);
        $destsections = $DB->get_records_menu('course_sections', ['course' => $courseid], '', 'section, id');

        // Build oldid → newid map via: oldid → number → dest section id.
        $sectionmap = [];
        foreach ($this->sectionoldidtonumber as $oldid => $number) {
            if (isset($destsections[$number])) {
                $sectionmap[$oldid] = (int)$destsections[$number];
            }
        }

        if (empty($sectionmap)) {
            return;
        }

        $newconfig = new stdClass();
        $changed = false;

        foreach ((array)$config as $key => $value) {
            if (preg_match('/^(section_(?:enabled|start|end)_)(\d+)$/', $key, $m)) {
                $oldsectionid = (int)$m[2];
                if (isset($sectionmap[$oldsectionid])) {
                    $newconfig->{$m[1] . $sectionmap[$oldsectionid]} = $value;
                    $changed = true;
                }
                // Keys with no mapping are dropped (section not present in destination).
            } else {
                $newconfig->$key = $value;
            }
        }

        if ($changed) {
            $DB->set_field(
                'block_instances',
                'configdata',
                base64_encode(serialize($newconfig)),
                ['id' => $blockid]
            );
        }
    }
}
