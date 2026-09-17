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

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for bloquecero Zoom category reuse.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_filter::class)]
final class category_filter_test extends advanced_testcase {
    /**
     * Without Zoom categories and without a block instance, the course is out of scope.
     */
    public function test_should_show_false_without_instance_or_zoom(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('showcategories', '', 'block_zoom_udima');

        $this->assertFalse(category_filter::should_show($course));
    }

    /**
     * A course in Zoom UDIMA categories is in scope even without a bloquecero instance.
     */
    public function test_should_show_true_when_zoom_category_matches(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('showcategories', (string) $course->category, 'block_zoom_udima');

        $this->assertTrue(category_filter::should_show($course));
        $this->assertFalse(category_filter::has_course_instance((int) $course->id));
    }

    /**
     * Turning off reuse ignores Zoom categories unless the block is in the course.
     */
    public function test_should_show_false_when_reuse_disabled(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('showcategories', (string) $course->category, 'block_zoom_udima');
        set_config('reusezoomcategories', '0', 'block_bloquecero');

        $this->assertFalse(category_filter::should_show($course));
    }

    /**
     * Site home is never in scope.
     */
    public function test_should_show_false_on_site(): void {
        $this->resetAfterTest();
        $this->assertFalse(category_filter::should_show(get_site()));
        $this->assertFalse(category_filter::should_show(null));
    }

    /**
     * Sessions for a course are returned without requiring a block instance id.
     */
    public function test_get_sessions_by_courseid_without_instance(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $DB->insert_record('block_bloquecero_sessions', (object) [
            'blockinstanceid' => 0,
            'courseid' => $course->id,
            'name' => 'Directo sincronizado',
            'sessiondate' => $now,
            'duration' => 3600,
            'description' => '',
            'calendarid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $sessions = category_filter::get_sessions((int) $course->id, 0);
        $this->assertCount(1, $sessions);
        $first = reset($sessions);
        $this->assertSame('Directo sincronizado', $first->name);
    }

    /**
     * Sincronizador calendar events appear on the live-sessions card.
     */
    public function test_get_sessions_includes_sincronizador_events(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        if (!$DB->get_manager()->table_exists('sincronizador_eventos_mapeo')) {
            $this->markTestSkipped('local_sincronizador_eventos is not installed');
        }

        require_once($CFG->dirroot . '/calendar/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $event = \calendar_event::create((object) [
            'name' => 'Clase sincronizada',
            'description' => 'Desde el sincronizador',
            'format' => FORMAT_HTML,
            'courseid' => $course->id,
            'timestart' => $now,
            'timeduration' => 3600,
            'eventtype' => 'course',
            'visible' => 1,
        ]);
        $DB->insert_record('sincronizador_eventos_mapeo', (object) [
            'external_id' => 'ext-clase-1',
            'moodle_event_id' => $event->id,
            'courseid' => $course->id,
            'timemodified' => $now,
        ]);

        $sessions = category_filter::get_sessions((int) $course->id, 0);
        $this->assertCount(1, $sessions);
        $first = reset($sessions);
        $this->assertSame('Clase sincronizada', $first->name);
        $this->assertSame($now, (int) $first->sessiondate);
        $this->assertSame((int) $event->id, (int) $first->calendarid);
    }

    /**
     * A bloquecero session already linked to the calendar event is not duplicated.
     */
    public function test_get_sessions_does_not_duplicate_mapped_calendar_event(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        if (!$DB->get_manager()->table_exists('sincronizador_eventos_mapeo')) {
            $this->markTestSkipped('local_sincronizador_eventos is not installed');
        }

        require_once($CFG->dirroot . '/calendar/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $event = \calendar_event::create((object) [
            'name' => 'Misma clase',
            'description' => '',
            'format' => FORMAT_HTML,
            'courseid' => $course->id,
            'timestart' => $now,
            'timeduration' => 3600,
            'eventtype' => 'course',
            'visible' => 1,
        ]);
        $DB->insert_record('sincronizador_eventos_mapeo', (object) [
            'external_id' => 'ext-clase-2',
            'moodle_event_id' => $event->id,
            'courseid' => $course->id,
            'timemodified' => $now,
        ]);
        $DB->insert_record('block_bloquecero_sessions', (object) [
            'blockinstanceid' => 0,
            'courseid' => $course->id,
            'name' => 'Misma clase',
            'sessiondate' => $now,
            'duration' => 3600,
            'description' => '',
            'calendarid' => $event->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $sessions = category_filter::get_sessions((int) $course->id, 0);
        $this->assertCount(1, $sessions);
    }

    /**
     * Manual and sincronizador sessions at the same minute count as one.
     */
    public function test_merge_collapses_same_minute(): void {
        $local = (object) [
            'id' => 10,
            'name' => 'Clase del profesor',
            'sessiondate' => 1710000000,
            'duration' => 3600,
            'calendarid' => null,
        ];
        $sync = (object) [
            'calendarid' => 99,
            'name' => 'Clase del sincronizador',
            'sessiondate' => 1710000040,
            'duration' => 3600,
        ];

        $merged = category_filter::merge_session_lists([$local], [$sync]);
        $this->assertCount(1, $merged);
        $first = reset($merged);
        $this->assertSame('Clase del profesor', $first->name);
        $this->assertSame(99, (int) $first->calendarid);
    }

    /**
     * Sessions a minute apart are both kept.
     */
    public function test_merge_keeps_different_minutes(): void {
        $local = (object) [
            'id' => 10,
            'name' => 'Diez',
            'sessiondate' => 1710000000,
            'calendarid' => null,
        ];
        $sync = (object) [
            'calendarid' => 99,
            'name' => 'Diez y uno',
            'sessiondate' => 1710000060,
        ];

        $merged = category_filter::merge_session_lists([$local], [$sync]);
        $this->assertCount(2, $merged);
    }
}
