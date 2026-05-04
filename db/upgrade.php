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
 * Upgrade script for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade block_bloquecero database structures.
 *
 * @param int $oldversion Previous plugin version.
 * @return bool True on success.
 */
function xmldb_block_bloquecero_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025061701) {
        // Define table block_bloquecero_sessions to be created.
        $table = new xmldb_table('block_bloquecero_sessions');

        // Adding fields to table block_bloquecero_sessions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sessiondate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, '3600');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('calendarid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_bloquecero_sessions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Adding indexes to table block_bloquecero_sessions.
        $table->add_index('courseid_sessiondate', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'sessiondate']);

        // Conditionally launch create table for block_bloquecero_sessions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Bloquecero savepoint reached.
        upgrade_block_savepoint(true, 2025061701, 'bloquecero');
    }

    if ($oldversion < 2025061702) {
        // Define table block_bloquecero_bibliography to be created.
        $table = new xmldb_table('block_bloquecero_bibliography');

        // Adding fields to table block_bloquecero_bibliography.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('url', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_bloquecero_bibliography.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Adding indexes to table block_bloquecero_bibliography.
        $table->add_index('courseid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'sortorder']);

        // Conditionally launch create table for block_bloquecero_bibliography.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate existing bibliography data from block config to new table.
        $blocks = $DB->get_records('block_instances', ['blockname' => 'bloquecero']);
        foreach ($blocks as $block) {
            if (empty($block->configdata)) {
                continue;
            }
            $config = unserialize(base64_decode($block->configdata));
            if (!empty($config->bibliography_name) && is_array($config->bibliography_name)) {
                // Get courseid from block's parent context.
                $parentcontext = $DB->get_record('context', ['id' => $block->parentcontextid]);
                if ($parentcontext && $parentcontext->contextlevel == CONTEXT_COURSE) {
                    $courseid = $parentcontext->instanceid;

                    $now = time();
                    foreach ($config->bibliography_name as $index => $name) {
                        $name = trim($name);
                        if (!empty($name)) {
                            $url = isset($config->bibliography_url[$index]) ? trim($config->bibliography_url[$index]) : '';

                            $record = new stdClass();
                            $record->blockinstanceid = $block->id;
                            $record->courseid = $courseid;
                            $record->name = $name;
                            $record->url = $url;
                            $record->sortorder = $index;
                            $record->timecreated = $now;
                            $record->timemodified = $now;

                            $DB->insert_record('block_bloquecero_bibliography', $record);
                        }
                    }
                }
            }
        }

        // Bloquecero savepoint reached.
        upgrade_block_savepoint(true, 2025061702, 'bloquecero');
    }

    if ($oldversion < 2025061703) {
        // Add description field to block_bloquecero_bibliography table.
        $table = new xmldb_table('block_bloquecero_bibliography');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'url');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Bloquecero savepoint reached.
        upgrade_block_savepoint(true, 2025061703, 'bloquecero');
    }

    if ($oldversion < 2025061704) {
        // Ensure bibliography table exists (may be missing if upgrade path skipped 2025061702).
        $table = new xmldb_table('block_bloquecero_bibliography');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('url', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);
            $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

            $table->add_index('courseid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'sortorder']);

            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025061704, 'bloquecero');
    }

    if ($oldversion < 2026050201) {
        // Fix name column type: must be CHAR(255), not TEXT.
        foreach (['block_bloquecero_sessions', 'block_bloquecero_bibliography'] as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
                if ($dbman->field_exists($table, $field)) {
                    $dbman->change_field_type($table, $field);
                }
            }
        }

        upgrade_block_savepoint(true, 2026050201, 'bloquecero');
    }

    return true;
}
