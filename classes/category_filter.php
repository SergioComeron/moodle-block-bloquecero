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
 * Decides whether bloquecero should treat a course as in-scope.
 *
 * Reuses block_zoom_udima/showcategories so the same category list
 * drives Zoom and bloquecero, unless the site setting is turned off.
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_filter {
    /**
     * Whether bloquecero should reuse Zoom UDIMA category config.
     *
     * Defaults to on when the setting has never been saved.
     *
     * @return bool
     */
    public static function reuses_zoom_categories(): bool {
        $reuse = get_config('block_bloquecero', 'reusezoomcategories');
        return $reuse !== '0';
    }

    /**
     * Category ids selected in block_zoom_udima/showcategories.
     *
     * @return int[]
     */
    public static function zoom_category_ids(): array {
        $raw = get_config('block_zoom_udima', 'showcategories');
        if ($raw === false || $raw === null || $raw === '') {
            return [];
        }
        $ids = array_map('intval', explode(',', (string) $raw));
        return array_values(array_filter($ids, static function (int $id): bool {
            return $id > 0;
        }));
    }

    /**
     * Whether the course category is in Zoom UDIMA's allowed list.
     *
     * Matches Zoom's own check: the course's direct category, not parents.
     *
     * @param \stdClass $course Course record (needs category).
     * @return bool
     */
    public static function course_in_zoom_categories(\stdClass $course): bool {
        if (empty($course->category)) {
            return false;
        }
        return in_array((int) $course->category, self::zoom_category_ids(), true);
    }

    /**
     * Whether the course has a bloquecero instance in its own context.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function has_course_instance(int $courseid): bool {
        global $DB;

        if ($courseid <= 0) {
            return false;
        }
        $context = \context_course::instance($courseid);
        return $DB->record_exists('block_instances', [
            'blockname' => 'bloquecero',
            'parentcontextid' => $context->id,
        ]);
    }

    /**
     * Whether bloquecero UI should apply to this course.
     *
     * True if the course has a bloquecero instance, or if Zoom-category
     * reuse is on and the course is in those categories.
     *
     * @param \stdClass|null $course Course record. Null or SITEID → false.
     * @return bool
     */
    public static function should_show(?\stdClass $course): bool {
        if (empty($course) || empty($course->id) || (int) $course->id === (int) SITEID) {
            return false;
        }
        if (self::has_course_instance((int) $course->id)) {
            return true;
        }
        if (!self::reuses_zoom_categories()) {
            return false;
        }
        return self::course_in_zoom_categories($course);
    }

    /**
     * Live sessions for a course, optionally scoped to a block instance.
     *
     * Includes manual bloquecero sessions and calendar events created by
     * local_sincronizador_eventos (via sincronizador_eventos_mapeo).
     * Duplicate starts (same calendar event, or same minute) are collapsed.
     * When instance id is 0, all bloquecero sessions for the course are returned.
     *
     * @param int $courseid Course id.
     * @param int $blockinstanceid Block instance id, or 0 to ignore instance.
     * @return \stdClass[]
     */
    public static function get_sessions(int $courseid, int $blockinstanceid = 0): array {
        global $DB;

        $select = 'courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($blockinstanceid > 0) {
            $select .= ' AND (blockinstanceid = :blockinstanceid OR blockinstanceid = 0)';
            $params['blockinstanceid'] = $blockinstanceid;
        }
        $sessions = $DB->get_records_select(
            'block_bloquecero_sessions',
            $select,
            $params,
            'sessiondate ASC'
        );

        return self::merge_session_lists($sessions, self::get_sincronizador_sessions($courseid));
    }

    /**
     * Minute of the session start (unix time floored to 60s).
     *
     * Used to treat a manual bloquecero session and a sincronizador event
     * as the same class when they share date and time.
     *
     * @param int $timestamp Start timestamp.
     * @return int
     */
    public static function session_start_key(int $timestamp): int {
        return intdiv($timestamp, 60);
    }

    /**
     * Merge bloquecero sessions with sincronizador events, dropping duplicates.
     *
     * A sincronizador event is skipped if it is the same Moodle calendar event
     * or if it starts in the same minute as a bloquecero session. In the latter
     * case the bloquecero row is kept (professor title) and, if it had no
     * calendarid, the sincronizador event id is copied for the calendar link.
     *
     * @param \stdClass[] $localsessions Sessions from block_bloquecero_sessions.
     * @param \stdClass[] $syncsessions Sessions from the sincronizador mapping.
     * @return \stdClass[]
     */
    public static function merge_session_lists(array $localsessions, array $syncsessions): array {
        $result = [];
        $seenminutes = [];
        $seencalendar = [];

        foreach ($localsessions as $session) {
            if (!empty($session->calendarid)) {
                $seencalendar[(int) $session->calendarid] = true;
            }
            $minute = self::session_start_key((int) $session->sessiondate);
            $seenminutes[$minute] = $session;
            $localid = isset($session->id) ? (int) $session->id : 0;
            $result['b' . $localid] = $session;
        }

        foreach ($syncsessions as $sync) {
            $calendarid = (int) ($sync->calendarid ?? 0);
            if ($calendarid && isset($seencalendar[$calendarid])) {
                continue;
            }
            $minute = self::session_start_key((int) $sync->sessiondate);
            if (isset($seenminutes[$minute])) {
                $existing = $seenminutes[$minute];
                if (empty($existing->calendarid) && $calendarid) {
                    $existing->calendarid = $calendarid;
                }
                continue;
            }
            $result['s' . $calendarid] = $sync;
            $seenminutes[$minute] = $sync;
        }

        uasort($result, static function ($a, $b): int {
            return ((int) $a->sessiondate) <=> ((int) $b->sessiondate);
        });

        return $result;
    }

    /**
     * Calendar events created by local_sincronizador_eventos for a course.
     *
     * @param int $courseid Course id.
     * @return \stdClass[]
     */
    public static function get_sincronizador_sessions(int $courseid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('sincronizador_eventos_mapeo')) {
            return [];
        }

        $sql = "SELECT e.id AS calendarid, e.name, e.description,
                       e.timestart AS sessiondate, e.timeduration AS duration
                  FROM {event} e
                  JOIN {sincronizador_eventos_mapeo} m ON m.moodle_event_id = e.id
                 WHERE m.courseid = :courseid
              ORDER BY e.timestart ASC";

        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        foreach ($records as $record) {
            $record->blockinstanceid = 0;
            $record->duration = (int) ($record->duration ?? 0);
        }
        return $records;
    }
}
