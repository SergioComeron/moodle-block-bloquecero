<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free free software: you can redistribute it and/or modify
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

use advanced_testcase;
use context_course;
use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Tests for block_bloquecero hook callbacks.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_bloquecero\hook\callbacks
 */
final class callbacks_test extends advanced_testcase {

    /**
     * Devuelve un objeto hook vacío para usar en los tests.
     */
    private function make_hook(): before_standard_top_of_body_html_generation {
        $renderer = $this->createMock(\renderer_base::class);
        return new before_standard_top_of_body_html_generation($renderer);
    }

    /**
     * No debe inyectar CSS en la página principal del sitio (SITEID).
     *
     * @covers ::before_standard_top_of_body_html
     */
    public function test_no_output_on_site_home(): void {
        global $COURSE;

        $this->resetAfterTest();

        $COURSE = get_site();

        $hook = $this->make_hook();
        callbacks::before_standard_top_of_body_html($hook);

        $this->assertSame('', $hook->get_output());
    }

    /**
     * No debe inyectar CSS en un curso que no tiene el bloque instalado.
     *
     * @covers ::before_standard_top_of_body_html
     */
    public function test_no_output_without_block(): void {
        global $COURSE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;

        $hook = $this->make_hook();
        callbacks::before_standard_top_of_body_html($hook);

        $this->assertSame('', $hook->get_output());
    }

    /**
     * Debe inyectar el CSS cuando el bloque está instalado en el curso.
     *
     * @covers ::before_standard_top_of_body_html
     */
    public function test_output_with_block_installed(): void {
        global $COURSE, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;

        // Instalar el bloque en el contexto del curso.
        $coursecontext = context_course::instance($course->id);
        $DB->insert_record('block_instances', (object)[
            'blockname'        => 'bloquecero',
            'parentcontextid'  => $coursecontext->id,
            'showinsubcontexts' => 0,
            'requiredbytheme'  => 0,
            'pagetypepattern'  => 'course-view-*',
            'defaultregion'    => 'side-pre',
            'defaultweight'    => 0,
            'configdata'       => '',
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        $hook = $this->make_hook();
        callbacks::before_standard_top_of_body_html($hook);

        $output = $hook->get_output();
        $this->assertStringContainsString('<style>', $output);
        $this->assertStringContainsString('#courseindex-drawer', $output);
        $this->assertStringContainsString('display: none !important', $output);
    }
}
