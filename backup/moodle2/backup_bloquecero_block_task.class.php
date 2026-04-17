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
 * Backup task for block_bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/bloquecero/backup/moodle2/backup_bloquecero_stepslib.php');

/**
 * Backup task for block_bloquecero (sessions + bibliography).
 */
class backup_bloquecero_block_task extends backup_block_task {
    /**
     * No custom settings needed.
     */
    protected function define_my_settings() {
    }

    /**
     * Register the backup structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_bloquecero_block_structure_step('bloquecero_structure', 'bloquecero.xml'));
    }

    /**
     * No file areas to backup.
     */
    public function get_fileareas() {
        return [];
    }

    /**
     * No configdata attributes require special encoding.
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * No content links to encode.
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
