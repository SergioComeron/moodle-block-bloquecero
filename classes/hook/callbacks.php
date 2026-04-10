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

namespace block_bloquecero\hook;

/**
 * Hook callbacks for block_bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacks {
    /**
     * Hides the course index drawer on course pages where the block is installed.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $COURSE, $DB;

        if (empty($COURSE) || $COURSE->id == SITEID) {
            return;
        }

        $coursecontext = \context_course::instance($COURSE->id);
        $hasblock = $DB->record_exists('block_instances', [
            'blockname'       => 'bloquecero',
            'parentcontextid' => $coursecontext->id,
        ]);

        if (!$hasblock) {
            return;
        }

        $hook->add_html('<style>
        #courseindex-drawer,
        .courseindex,
        .drawer-toggler.drawer-left-toggle { display: none !important; }
        @media (min-width: 768px) {
            .drawer-left {
                display: block !important;
                transform: translateX(0) !important;
                visibility: visible !important;
            }
            .drawers-backdrop { display: none !important; }
        }
    </style>');
    }
}
