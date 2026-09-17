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
 * Zoom UDIMA destinations reused as entry cards inside bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_cards {
    /**
     * Whether Zoom UDIMA is installed.
     *
     * @return bool
     */
    public static function zoom_plugin_available(): bool {
        $plugins = \core_component::get_plugin_list('block');
        return isset($plugins['zoom_udima']);
    }

    /**
     * Whether the live-session pills should point to Zoom UDIMA.
     *
     * True when the course has a bloquecero instance and Zoom UDIMA would
     * show its own cards (plugin installed and course category allowed).
     *
     * @param \stdClass|null $course Course record.
     * @return bool
     */
    public static function should_show(?\stdClass $course): bool {
        if (empty($course) || empty($course->id) || (int) $course->id === (int) SITEID) {
            return false;
        }
        if (!self::zoom_plugin_available()) {
            return false;
        }
        if (!category_filter::has_course_instance((int) $course->id)) {
            return false;
        }
        return category_filter::course_in_zoom_categories($course);
    }

    /**
     * Live and recordings URLs for Zoom UDIMA, or null if pills should not use Zoom.
     *
     * @param \stdClass|null $course Course record.
     * @return array{live: \moodle_url, records: \moodle_url}|null
     */
    public static function urls(?\stdClass $course): ?array {
        if (!self::should_show($course)) {
            return null;
        }

        return [
            'live' => new \moodle_url('/blocks/zoom_udima/sessions.php', ['courseid' => $course->id]),
            'records' => new \moodle_url('/blocks/zoom_udima/records.php', ['courseid' => $course->id]),
        ];
    }

    /**
     * Two Zoom-style entry cards linking to live sessions and recordings.
     *
     * @param \moodle_url $liveurl Live sessions URL.
     * @param \moodle_url $recordsurl Recordings URL.
     * @return string
     */
    public static function render_cards(\moodle_url $liveurl, \moodle_url $recordsurl): string {
        $cards = self::card(
            $liveurl,
            'zoom-udima-card--live',
            self::icon_live(),
            self::card_string('card_live_title', 'directos_livelink'),
            self::card_string('card_live_sub', 'directos_livelink')
        ) . self::card(
            $recordsurl,
            'zoom-udima-card--records',
            self::icon_records(),
            self::card_string('card_records_title', 'directos_recordingslink'),
            self::card_string('card_records_sub', 'directos_recordingslink')
        );

        return \html_writer::div($cards, 'zoom-udima-cards');
    }

    /**
     * Title/subtitle from Zoom UDIMA when installed, otherwise bloquecero.
     *
     * @param string $zoomkey String id in block_zoom_udima.
     * @param string $fallbackkey String id in block_bloquecero.
     * @return string
     */
    private static function card_string(string $zoomkey, string $fallbackkey): string {
        if (
            self::zoom_plugin_available()
            && get_string_manager()->string_exists($zoomkey, 'block_zoom_udima')
        ) {
            return get_string($zoomkey, 'block_zoom_udima');
        }
        return get_string($fallbackkey, 'block_bloquecero');
    }

    /**
     * One Zoom-style entry card.
     *
     * @param \moodle_url $url Destination.
     * @param string $modifier CSS modifier class.
     * @param string $iconhtml Icon markup.
     * @param string $title Card title.
     * @param string $sub Card subtitle.
     * @return string
     */
    private static function card(
        \moodle_url $url,
        string $modifier,
        string $iconhtml,
        string $title,
        string $sub
    ): string {
        $icon = \html_writer::span($iconhtml, 'zoom-udima-card__icon');
        $text = \html_writer::span(s($title), 'zoom-udima-card__title');
        return \html_writer::link($url, $icon . $text, [
            'class' => 'zoom-udima-card ' . $modifier,
            'title' => $sub,
        ]);
    }

    /**
     * Broadcast icon for the live-sessions card.
     *
     * @return string
     */
    private static function icon_live(): string {
        return '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="3.2" fill="currentColor"/>'
            . '<path d="M7.05 7.05a7 7 0 0 0 0 9.9M16.95 7.05a7 7 0 0 1 0 9.9'
            . 'M4.22 4.22a11 11 0 0 0 0 15.56M19.78 4.22a11 11 0 0 1 0 15.56"'
            . ' fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '</svg>';
    }

    /**
     * Film icon for the recordings card.
     *
     * @return string
     */
    private static function icon_records(): string {
        return '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">'
            . '<rect x="2.5" y="6.5" width="13" height="11" rx="2" fill="currentColor"/>'
            . '<path d="M16 10.5l5-2.8v8.6l-5-2.8z" fill="currentColor"/>'
            . '</svg>';
    }
}
