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

use cm_info;

/**
 * Course-page order of activities, including subsection contents in place.
 *
 * Moodle stores each subsection as a real section with a high section number
 * (at the end of the course). Walking $modinfo->cms therefore lists those
 * activities last. Section cards already expand subsections in the parent
 * sequence; Gantt and the activities card must use the same walk.
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_order {
    /**
     * Visible course modules in aula order, with parent section number.
     *
     * Labels and the subsection modules themselves are omitted; their
     * children are inlined where the subsection sits in the parent.
     *
     * @param \course_modinfo $modinfo Course modinfo.
     * @return array<int, array{cm: cm_info, sectionnum: int}>
     */
    public static function listed_cms(\course_modinfo $modinfo): array {
        $sectionbyid = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sectionbyid[$section->id] = $section;
        }

        $listed = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!empty($section->component) && $section->component === 'mod_subsection') {
                continue;
            }
            if (empty($modinfo->sections[$section->section])) {
                continue;
            }
            $parentnum = (int) $section->section;
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if ($cm->modname === 'label') {
                    continue;
                }
                if ($cm->modname === 'subsection') {
                    foreach (self::subsection_cms($cm, $modinfo, $sectionbyid) as $subcm) {
                        $listed[] = ['cm' => $subcm, 'sectionnum' => $parentnum];
                    }
                    continue;
                }
                $listed[] = ['cm' => $cm, 'sectionnum' => $parentnum];
            }
        }
        return $listed;
    }

    /**
     * Activities inside a subsection module, in that subsection's sequence.
     *
     * @param cm_info $subsectioncm The subsection course module.
     * @param \course_modinfo $modinfo Course modinfo.
     * @param array $sectionbyid Sections keyed by id.
     * @return cm_info[]
     */
    private static function subsection_cms(cm_info $subsectioncm, \course_modinfo $modinfo, array $sectionbyid): array {
        $sectionid = $subsectioncm->customdata['sectionid'] ?? null;
        if (!$sectionid || empty($sectionbyid[$sectionid])) {
            return [];
        }
        $sub = $sectionbyid[$sectionid];
        if (empty($modinfo->sections[$sub->section])) {
            return [];
        }
        $cms = [];
        foreach ($modinfo->sections[$sub->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname === 'label' || $cm->modname === 'subsection') {
                continue;
            }
            $cms[] = $cm;
        }
        return $cms;
    }
}
