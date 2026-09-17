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
use context_course;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for Zoom UDIMA cards embedded in bloquecero.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(zoom_cards::class)]
final class zoom_cards_test extends advanced_testcase {
    /**
     * Adds a course-level bloquecero instance.
     *
     * @param \stdClass $course Course record.
     */
    private function add_bloquecero_instance(\stdClass $course): void {
        global $DB;

        $DB->insert_record('block_instances', (object) [
            'blockname' => 'bloquecero',
            'parentcontextid' => context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Without a bloquecero instance the Zoom cards stay in the Zoom block.
     */
    public function test_should_show_false_without_bloquecero_instance(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('showcategories', (string) $course->category, 'block_zoom_udima');

        $this->assertFalse(zoom_cards::should_show($course));
        $this->assertNull(zoom_cards::urls($course));
    }

    /**
     * A bloquecero instance is not enough if Zoom UDIMA is not configured for the course.
     */
    public function test_should_show_false_when_zoom_category_does_not_match(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->add_bloquecero_instance($course);
        set_config('showcategories', '', 'block_zoom_udima');

        $this->assertFalse(zoom_cards::should_show($course));
        $this->assertNull(zoom_cards::urls($course));
    }

    /**
     * With bloquecero and Zoom categories, the pills point to Zoom UDIMA pages.
     */
    public function test_urls_when_instance_and_zoom_category_match(): void {
        $this->resetAfterTest();

        if (!zoom_cards::zoom_plugin_available()) {
            $this->markTestSkipped('block_zoom_udima is not installed');
        }

        $course = $this->getDataGenerator()->create_course();
        $this->add_bloquecero_instance($course);
        set_config('showcategories', (string) $course->category, 'block_zoom_udima');

        $this->assertTrue(zoom_cards::should_show($course));

        $urls = zoom_cards::urls($course);
        $this->assertNotNull($urls);
        $this->assertStringContainsString('/blocks/zoom_udima/sessions.php', $urls['live']->out(false));
        $this->assertStringContainsString('/blocks/zoom_udima/records.php', $urls['records']->out(false));
        $this->assertStringContainsString('courseid=' . $course->id, $urls['live']->out(false));
        $this->assertStringContainsString('courseid=' . $course->id, $urls['records']->out(false));
    }

    /**
     * Entry cards use the Zoom UDIMA markup and the given destinations.
     */
    public function test_render_cards_uses_zoom_markup_and_urls(): void {
        $this->resetAfterTest();

        $live = new \moodle_url('/blocks/zoom_udima/sessions.php', ['courseid' => 7]);
        $records = new \moodle_url('/blocks/zoom_udima/records.php', ['courseid' => 7]);
        $html = zoom_cards::render_cards($live, $records);

        $this->assertStringContainsString('zoom-udima-cards', $html);
        $this->assertStringContainsString('zoom-udima-card--live', $html);
        $this->assertStringContainsString('zoom-udima-card--records', $html);
        $this->assertStringContainsString('/blocks/zoom_udima/sessions.php', $html);
        $this->assertStringContainsString('/blocks/zoom_udima/records.php', $html);
        $this->assertStringContainsString('courseid=7', $html);
        $this->assertStringNotContainsString('zoom-udima-card__sub', $html);
        if (zoom_cards::zoom_plugin_available()) {
            $this->assertStringContainsString(
                'title="' . s(get_string('card_live_sub', 'block_zoom_udima')) . '"',
                $html
            );
        }
    }

    /**
     * Site home never embeds Zoom cards.
     */
    public function test_should_show_false_on_site(): void {
        $this->resetAfterTest();
        $this->assertFalse(zoom_cards::should_show(get_site()));
        $this->assertFalse(zoom_cards::should_show(null));
    }
}
