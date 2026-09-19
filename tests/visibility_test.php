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
 * Tests for course-page visibility of restricted activities.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(visibility::class)]
final class visibility_test extends advanced_testcase {
    /**
     * Greyed-out restrictions are shown; hidden-completely ones are not.
     */
    public function test_student_sees_greyed_out_but_not_hidden_completely(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $future = time() + YEARSECS;

        $greyed = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'AEC en gris',
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => $future]],
                'showc' => [true],
            ]),
        ]);
        $hidden = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'AEC oculta',
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => $future]],
                'showc' => [false],
            ]),
        ]);
        $open = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Instrucciones',
        ]);

        $this->setUser($student);
        $modinfo = get_fast_modinfo($course);

        $greycm = $modinfo->get_cm($greyed->cmid);
        $this->assertTrue(visibility::show_cm($greycm));
        $this->assertTrue(visibility::is_restricted($greycm));
        $this->assertNotSame('', visibility::restriction_html($greycm));

        $hiddencm = $modinfo->get_cm($hidden->cmid);
        $this->assertFalse(visibility::show_cm($hiddencm));
        $this->assertFalse(visibility::is_restricted($hiddencm));

        $opencm = $modinfo->get_cm($open->cmid);
        $this->assertTrue(visibility::show_cm($opencm));
        $this->assertFalse(visibility::is_restricted($opencm));
    }

    /**
     * Completion restrictions point at the predecessor module id.
     */
    public function test_completion_predecessor_cmids(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $second = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'completion', 'cm' => $first->cmid, 'e' => COMPLETION_COMPLETE]],
                'showc' => [true],
            ]),
        ]);

        $cm = get_fast_modinfo($course)->get_cm($second->cmid);
        $this->assertSame([(int) $first->cmid], visibility::completion_predecessor_cmids($cm));
    }

    /**
     * Gantt chain marks mid/last among dependents in the same list.
     */
    public function test_annotate_gantt_chains(): void {
        $rows = [
            ['cmid' => 1, 'name' => 'Instrucciones', 'pred_cmids' => []],
            ['cmid' => 2, 'name' => 'Lección', 'pred_cmids' => [1]],
            ['cmid' => 3, 'name' => 'Buzón', 'pred_cmids' => [2]],
        ];
        $annotated = visibility::annotate_gantt_chains($rows);
        $this->assertSame('', $annotated[0]['chain']);
        $this->assertSame('mid', $annotated[1]['chain']);
        $this->assertSame('Instrucciones', $annotated[1]['pred_name']);
        $this->assertSame('last', $annotated[2]['chain']);
        $this->assertSame('Lección', $annotated[2]['pred_name']);
    }
}
