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
 * Tests for course-page activity order with subsections.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_order::class)]
final class course_order_test extends advanced_testcase {
    /**
     * Subsection contents are inlined where the subsection sits, not at the end.
     */
    public function test_subsection_activities_keep_parent_sequence_order(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $before = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Antes',
        ]);
        $subsection = $this->getDataGenerator()->create_module('subsection', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'AEC',
        ]);
        $modinfo = get_fast_modinfo($course);
        $delegated = $modinfo->get_section_info_by_component('mod_subsection', $subsection->id);
        $this->assertNotNull($delegated);

        $inside = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => $delegated->section,
            'name' => 'Dentro',
        ]);
        $after = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Después',
        ]);

        $listed = course_order::listed_cms(get_fast_modinfo($course));
        $names = array_map(static fn(array $row): string => $row['cm']->name, $listed);
        $this->assertSame(['Antes', 'Dentro', 'Después'], $names);

        foreach ($listed as $row) {
            $this->assertSame(1, $row['sectionnum']);
        }

        $cmsorder = [];
        foreach (get_fast_modinfo($course)->cms as $cm) {
            if (in_array($cm->name, ['Antes', 'Dentro', 'Después'], true)) {
                $cmsorder[] = $cm->name;
            }
        }
        $this->assertSame(['Antes', 'Después', 'Dentro'], $cmsorder);
    }
}
