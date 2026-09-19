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

namespace block_bloquecero;

/**
 * Short course title for the bloquecero header.
 *
 * UDIMA fullnames look like "Expresión Gráfica - 110.Laboratorio - Segundo Semestre_SINC".
 * The header keeps the part before the first " - " and restores "Laboratorio" when
 * that word would otherwise be discarded.
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_name {
    /**
     * Display title for the block header.
     *
     * @param string $fullname Course fullname.
     * @return string
     */
    public static function display(string $fullname): string {
        $parts = explode(' - ', $fullname, 2);
        $short = trim($parts[0]);
        if ($short === '') {
            $short = trim($fullname);
        }
        $haslab = (bool) preg_match('/laboratorio/iu', $fullname);
        $shortalready = (bool) preg_match('/laboratorio/iu', $short);
        if ($haslab && !$shortalready) {
            $short .= ' · Laboratorio';
        }
        return $short;
    }
}
