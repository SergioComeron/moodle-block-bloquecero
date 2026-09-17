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

use block_bloquecero\category_filter;
use block_bloquecero\zoom_cards;

/**
 * Hook callbacks for block_bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacks {
    /**
     * Hides the course index drawer on course pages where bloquecero applies.
     *
     * Applies when the course has a bloquecero instance, or when Zoom UDIMA
     * categories are reused and this course is in that list. Also hides the
     * Zoom UDIMA block when bloquecero is embedding its two entry cards.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $COURSE;

        if (!category_filter::should_show($COURSE ?? null)) {
            return;
        }

        $css = '#courseindex-drawer,
        .courseindex,
        .drawer-toggler.drawer-left-toggle { display: none !important; }
        @media (min-width: 768px) {
            .drawer-left {
                display: block !important;
                transform: translateX(0) !important;
                visibility: visible !important;
            }
            .drawers-backdrop { display: none !important; }
        }';
        // require_once: el classmap de Moodle no ve clases nuevas hasta purgar caché.
        if (!class_exists(zoom_cards::class, false)) {
            $zoomcardsfile = dirname(__DIR__) . '/zoom_cards.php';
            if (is_readable($zoomcardsfile)) {
                require_once($zoomcardsfile);
            }
        }
        if (class_exists(zoom_cards::class, false) && zoom_cards::should_show($COURSE ?? null)) {
            $css .= '.block_zoom_udima { display: none !important; }';
        }

        $hook->add_html('<style>' . $css . '</style>');
    }
}
