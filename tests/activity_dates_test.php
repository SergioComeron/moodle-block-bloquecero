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
 * Tests for Gantt activity date extraction, including lessons.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(activity_dates::class)]
final class activity_dates_test extends advanced_testcase {
    /**
     * A lesson with available/deadline is plotted from those timestamps.
     */
    public function test_lesson_uses_available_and_deadline(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $available = (new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $deadline = (new \DateTimeImmutable('2026-10-15 23:59:59', new \DateTimeZone('UTC')))->getTimestamp();
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'available' => $available,
            'deadline' => $deadline,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($lesson->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame($available, $dates['start']);
        $this->assertSame($deadline, $dates['end']);
    }

    /**
     * A lesson with only an opening date is a single-day bar.
     */
    public function test_lesson_available_without_deadline_is_point_in_time(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $available = (new \DateTimeImmutable('2026-11-02 08:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'available' => $available,
            'deadline' => 0,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($lesson->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame($available, $dates['start']);
        $this->assertSame($available, $dates['end']);
    }

    /**
     * A lesson with no module dates and no restrictions is omitted.
     */
    public function test_lesson_without_dates_returns_zero(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'available' => 0,
            'deadline' => 0,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($lesson->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame(0, $dates['start']);
        $this->assertSame(0, $dates['end']);
    }

    /**
     * Restrict-access date conditions are used when the lesson has no own dates.
     */
    public function test_lesson_falls_back_to_availability_dates(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $from = (new \DateTimeImmutable('2026-09-01 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $until = (new \DateTimeImmutable('2026-09-30 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'available' => 0,
            'deadline' => 0,
            'availability' => json_encode([
                'op' => '&',
                'c' => [
                    ['type' => 'date', 'd' => '>=', 't' => $from],
                    ['type' => 'date', 'd' => '<', 't' => $until],
                ],
                'showc' => [true, true],
            ]),
        ]);

        $cm = get_fast_modinfo($course)->get_cm($lesson->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame($from, $dates['start']);
        $this->assertSame($until - 1, $dates['end']);
    }

    /**
     * Availability JSON without a course module still parses date conditions.
     */
    public function test_from_availability_nested_tree(): void {
        $from = 1700000000;
        $until = 1700600000;
        $json = json_encode([
            'op' => '&',
            'c' => [
                [
                    'op' => '|',
                    'c' => [
                        ['type' => 'date', 'd' => '>=', 't' => $from],
                    ],
                ],
                ['type' => 'date', 'd' => '<=', 't' => $until],
            ],
        ]);

        $dates = activity_dates::from_availability($json);
        $this->assertSame($from, $dates['start']);
        $this->assertSame($until, $dates['end']);
    }

    /**
     * Moodle 5.2 forums use duedate/cutoffdate as the activity window.
     */
    public function test_forum_uses_duedate_and_cutoffdate(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $due = (new \DateTimeImmutable('2026-10-08 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $cutoff = (new \DateTimeImmutable('2026-10-15 23:59:00', new \DateTimeZone('UTC')))->getTimestamp();
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'duedate' => $due,
            'cutoffdate' => $cutoff,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($forum->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame($due, $dates['start']);
        $this->assertSame($cutoff, $dates['end']);
    }

    /**
     * Modules without a dedicated extractor use core activity dates (choice).
     */
    public function test_choice_uses_core_activity_dates(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $open = (new \DateTimeImmutable('2026-10-06 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $close = (new \DateTimeImmutable('2026-10-13 23:59:00', new \DateTimeZone('UTC')))->getTimestamp();
        $choice = $this->getDataGenerator()->create_module('choice', [
            'course' => $course->id,
            'timeopen' => $open,
            'timeclose' => $close,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($choice->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame($open, $dates['start']);
        $this->assertSame($close, $dates['end']);
    }

    /**
     * Assign dates keep the previous Gantt behaviour.
     */
    public function test_assign_uses_allowsubmissionsfromdate_and_duedate(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $open = (new \DateTimeImmutable('2026-10-05 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $due = (new \DateTimeImmutable('2026-10-20 23:59:00', new \DateTimeZone('UTC')))->getTimestamp();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'allowsubmissionsfromdate' => $open,
            'duedate' => $due,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);
        $dates = activity_dates::for_cm($cm);

        $this->assertSame($open, $dates['start']);
        $this->assertSame($due, $dates['end']);
    }
}
