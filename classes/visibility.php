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
use section_info;

/**
 * Course-page visibility for bloquecero lists and the Gantt.
 *
 * Matches Moodle's course view: greyed-out restricted activities are shown
 * with the restriction text; activities hidden completely are omitted.
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibility {
    /**
     * Whether this activity should appear in bloquecero (same as the course page).
     *
     * @param cm_info $cm Course module.
     * @return bool
     */
    public static function show_cm(cm_info $cm): bool {
        return $cm->is_visible_on_course_page();
    }

    /**
     * Visible on the course page but not yet accessible (greyed out).
     *
     * @param cm_info $cm Course module.
     * @return bool
     */
    public static function is_restricted(cm_info $cm): bool {
        return !$cm->uservisible && $cm->is_visible_on_course_page();
    }

    /**
     * Formatted restriction HTML, or empty if there is nothing to show.
     *
     * @param cm_info $cm Course module.
     * @return string
     */
    public static function restriction_html(cm_info $cm): string {
        if (empty($cm->availableinfo)) {
            return '';
        }
        return \core_availability\info::format_info($cm->availableinfo, $cm->get_course());
    }

    /**
     * Whether this section should appear (visible, or greyed with info).
     *
     * @param section_info $section Section.
     * @param bool $canviewhidden Teachers who can see hidden sections.
     * @return bool
     */
    public static function show_section(section_info $section, bool $canviewhidden = false): bool {
        if ($section->uservisible || $canviewhidden) {
            return true;
        }
        return !empty($section->availableinfo);
    }

    /**
     * Icon + name (link only when the activity is accessible) + restriction text.
     *
     * @param cm_info $cm Course module.
     * @param string $icon HTML icon.
     * @return string
     */
    /**
     * Course-module ids this activity waits on (completion restrictions).
     *
     * @param cm_info $cm Course module.
     * @return int[]
     */
    public static function completion_predecessor_cmids(cm_info $cm): array {
        if (empty($cm->availability)) {
            return [];
        }
        $tree = json_decode($cm->availability);
        if (!is_object($tree)) {
            return [];
        }
        $ids = [];
        $walk = function ($node) use (&$walk, &$ids): void {
            if (!is_object($node)) {
                return;
            }
            if (($node->type ?? '') === 'completion' && !empty($node->cm)) {
                $ids[] = (int) $node->cm;
            }
            if (!empty($node->c) && is_array($node->c)) {
                foreach ($node->c as $child) {
                    $walk($child);
                }
            }
        };
        $walk($tree);
        return array_values(array_unique($ids));
    }

    /**
     * Mark Gantt rows that depend on another row in the same list.
     *
     * Adds pred_cmid, pred_name and chain (mid|last) for tree drawing.
     *
     * @param array $activities Gantt activity rows (cmid, name, pred_cmids).
     * @return array
     */
    public static function annotate_gantt_chains(array $activities): array {
        $byid = [];
        foreach ($activities as $index => $act) {
            if (!empty($act['cmid'])) {
                $byid[(int) $act['cmid']] = $index;
            }
        }
        foreach ($activities as $index => &$act) {
            $act['pred_cmid'] = 0;
            $act['pred_name'] = '';
            $act['chain'] = '';
            foreach ($act['pred_cmids'] ?? [] as $predid) {
                $predid = (int) $predid;
                if (isset($byid[$predid])) {
                    $act['pred_cmid'] = $predid;
                    $act['pred_name'] = $activities[$byid[$predid]]['name'];
                    break;
                }
            }
        }
        unset($act);

        $count = count($activities);
        for ($index = 0; $index < $count; $index++) {
            if (empty($activities[$index]['pred_cmid'])) {
                continue;
            }
            $continues = false;
            if ($index + 1 < $count && !empty($activities[$index + 1]['pred_cmid'])) {
                $nextpred = (int) $activities[$index + 1]['pred_cmid'];
                $thisid = (int) ($activities[$index]['cmid'] ?? 0);
                $thispred = (int) $activities[$index]['pred_cmid'];
                $continues = ($nextpred === $thisid || $nextpred === $thispred);
            }
            $activities[$index]['chain'] = $continues ? 'mid' : 'last';
        }
        return $activities;
    }

    /**
     * Name cell for a Gantt activity, with optional chain marker.
     *
     * @param array $act Activity row after annotate_gantt_chains().
     * @return string
     */
    public static function gantt_activity_label_html(array $act): string {
        $chain = $act['chain'] ?? '';
        $tree = '';
        if ($chain === 'mid') {
            $tree = '<span class="bloquecero-gantt-tree" aria-hidden="true">├</span> ';
        } else if ($chain === 'last') {
            $tree = '<span class="bloquecero-gantt-tree" aria-hidden="true">└</span> ';
        }
        $html = $tree . ($act['icon'] ?? '') . ' ' . htmlspecialchars($act['name'] ?? '');
        if (!empty($act['pred_name'])) {
            $html .= '<div class="bloquecero-gantt-after">'
                . get_string('ganttafter', 'block_bloquecero', htmlspecialchars($act['pred_name']))
                . '</div>';
        }
        return $html;
    }

    /**
     * Icon + name (link only when the activity is accessible) + restriction text.
     *
     * @param cm_info $cm Course module.
     * @param string $icon HTML icon.
     * @return string
     */
    public static function activity_title_html(cm_info $cm, string $icon): string {
        $name = format_string($cm->name);
        if (self::is_restricted($cm) || empty($cm->url)) {
            $html = $icon . ' <span>' . $name . '</span>';
        } else {
            $html = $icon . ' <a href="' . $cm->url->out() . '">' . $name . '</a>';
        }
        $info = self::restriction_html($cm);
        if ($info !== '') {
            $html .= '<div class="bloquecero-restriction-info">' . $info . '</div>';
        }
        return $html;
    }
}
