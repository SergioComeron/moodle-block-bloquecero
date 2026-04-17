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
 * Backup step for block_bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure for block_bloquecero.
 */
class backup_bloquecero_block_structure_step extends backup_block_structure_step {
    /**
     * Defines the XML structure to be backed up.
     */
    protected function define_structure() {
        $blockid = $this->task->get_blockid();

        $bloquecero = new backup_nested_element('bloquecero', ['id'], null);

        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'courseid', 'name', 'sessiondate', 'duration', 'description',
            'hadcalendarsync', 'timecreated', 'timemodified',
        ]);

        $bibliographies = new backup_nested_element('bibliographies');
        $bibliography = new backup_nested_element('bibliography', ['id'], [
            'courseid', 'name', 'url', 'description', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $bloquecero->add_child($sessions);
        $sessions->add_child($session);
        $bloquecero->add_child($bibliographies);
        $bibliographies->add_child($bibliography);

        $bloquecero->set_source_array([(object)['id' => $blockid]]);

        $session->set_source_sql(
            'SELECT id, courseid, name, sessiondate, duration, description, timecreated, timemodified,
                    CASE WHEN calendarid IS NOT NULL AND calendarid > 0 THEN 1 ELSE 0 END AS hadcalendarsync
               FROM {block_bloquecero_sessions}
              WHERE blockinstanceid = ?',
            [backup_helper::is_sqlparam($blockid)]
        );

        $bibliography->set_source_sql(
            'SELECT id, courseid, name, url, description, sortorder, timecreated, timemodified
               FROM {block_bloquecero_bibliography}
              WHERE blockinstanceid = ?',
            [backup_helper::is_sqlparam($blockid)]
        );

        $session->annotate_ids('course', 'courseid');
        $bibliography->annotate_ids('course', 'courseid');

        return $this->prepare_block_structure($bloquecero);
    }
}
