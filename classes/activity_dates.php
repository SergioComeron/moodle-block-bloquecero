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
 * Start/end timestamps for activities drawn on the bloquecero Gantt.
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_dates {
    /**
     * Start and end unix timestamps for a course module.
     *
     * Uses the activity's own open/close fields when they exist. If those are
     * empty, falls back to date restrictions in the availability tree (common
     * for lessons that are scheduled only via "restrict access").
     *
     * @param cm_info $cm Course module.
     * @return array{start: int, end: int}
     */
    public static function for_cm(cm_info $cm): array {
        global $DB;

        $start = 0;
        $end = 0;

        if (!empty($cm->instance)) {
            switch ($cm->modname) {
                case 'assign':
                    $rec = $DB->get_record('assign', ['id' => $cm->instance], 'allowsubmissionsfromdate, duedate');
                    if ($rec) {
                        $start = (int) ($rec->allowsubmissionsfromdate ?: $rec->duedate);
                        $end = (int) $rec->duedate;
                    }
                    break;
                case 'quiz':
                    $rec = $DB->get_record('quiz', ['id' => $cm->instance], 'timeopen, timeclose');
                    if ($rec) {
                        $start = (int) ($rec->timeopen ?: $rec->timeclose);
                        $end = (int) $rec->timeclose;
                    }
                    break;
                case 'forum':
                    $rec = $DB->get_record(
                        'forum',
                        ['id' => $cm->instance],
                        'assesstimestart, assesstimefinish, duedate, cutoffdate'
                    );
                    if ($rec) {
                        $candidates = array_filter([
                            (int) $rec->assesstimestart,
                            (int) $rec->duedate,
                            (int) $rec->assesstimefinish,
                            (int) $rec->cutoffdate,
                        ]);
                        if ($candidates) {
                            $start = min($candidates);
                            $end = max($candidates);
                        }
                    }
                    break;
                case 'lesson':
                    $rec = $DB->get_record('lesson', ['id' => $cm->instance], 'available, deadline');
                    if ($rec) {
                        $start = (int) ($rec->available ?: $rec->deadline);
                        $end = (int) $rec->deadline;
                    }
                    break;
            }
        }

        if (!$start && !$end) {
            $fromcore = self::from_core($cm);
            $start = $fromcore['start'];
            $end = $fromcore['end'];
        }

        if (!$start && !$end) {
            $fromavail = self::from_availability($cm->availability ?? null);
            $start = $fromavail['start'];
            $end = $fromavail['end'];
        }

        if (!$start && !$end && !empty($cm->completionexpected)) {
            $start = (int) $cm->completionexpected;
            $end = (int) $cm->completionexpected;
        }

        if (!$start && !$end) {
            return ['start' => 0, 'end' => 0];
        }
        if (!$start) {
            $start = $end;
        }
        if (!$end) {
            $end = $start;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Min/max timestamps from the module's core activity_dates implementation.
     *
     * Covers workshop, choice, feedback, data, scorm, bigbluebuttonbn, etc.
     *
     * @param cm_info $cm Course module.
     * @param int $userid User id, or 0 for the current user.
     * @return array{start: int, end: int}
     */
    public static function from_core(cm_info $cm, int $userid = 0): array {
        global $USER;

        if ($userid <= 0) {
            $userid = (int) ($USER->id ?? 0);
        }
        $items = \core\activity_dates::get_dates_for_module($cm, $userid);
        $start = 0;
        $end = 0;
        foreach ($items as $item) {
            $timestamp = (int) ($item['timestamp'] ?? 0);
            if ($timestamp <= 0) {
                continue;
            }
            if ($start === 0 || $timestamp < $start) {
                $start = $timestamp;
            }
            if ($end === 0 || $timestamp > $end) {
                $end = $timestamp;
            }
        }
        return ['start' => $start, 'end' => $end];
    }

    /**
     * Earliest start and latest end from a Moodle availability JSON tree.
     *
     * @param string|null $availability JSON from course_modules.availability.
     * @return array{start: int, end: int}
     */
    public static function from_availability(?string $availability): array {
        $start = 0;
        $end = 0;
        if ($availability === null || $availability === '') {
            return ['start' => 0, 'end' => 0];
        }
        $tree = json_decode($availability);
        if (!is_object($tree)) {
            return ['start' => 0, 'end' => 0];
        }

        $walk = function ($node) use (&$walk, &$start, &$end): void {
            if (!is_object($node)) {
                return;
            }
            $type = $node->type ?? '';
            if ($type === 'date' && !empty($node->t)) {
                $timestamp = (int) $node->t;
                $op = $node->d ?? '>=';
                if ($op === '>=' || $op === '>') {
                    if ($start === 0 || $timestamp < $start) {
                        $start = $timestamp;
                    }
                } else if ($op === '<' || $op === '<=') {
                    $endts = ($op === '<') ? $timestamp - 1 : $timestamp;
                    if ($end === 0 || $endts > $end) {
                        $end = $endts;
                    }
                }
            }
            if (!empty($node->c) && is_array($node->c)) {
                foreach ($node->c as $child) {
                    $walk($child);
                }
            }
        };
        $walk($tree);

        return ['start' => $start, 'end' => $end];
    }
}
