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

/**
 * Manage teaching guides for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid  = required_param('blockid', PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);
$guideid  = optional_param('guideid', 0, PARAM_INT);

$course        = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/bloquecero:manageguides', $context);

$PAGE->set_url('/blocks/bloquecero/manage_guides.php', ['courseid' => $courseid, 'blockid' => $blockid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageguides', 'block_bloquecero'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('manageguides', 'block_bloquecero'));

// Handle delete action.
if ($action === 'delete' && $guideid && confirm_sesskey()) {
    $DB->delete_records('block_bloquecero_guides', ['id' => $guideid]);
    redirect($PAGE->url);
}

// Handle move up action.
if ($action === 'moveup' && $guideid && confirm_sesskey()) {
    $current = $DB->get_record('block_bloquecero_guides', ['id' => $guideid], '*', MUST_EXIST);
    $previous = $DB->get_record_sql(
        'SELECT * FROM {block_bloquecero_guides}
         WHERE blockinstanceid = ? AND courseid = ? AND sortorder < ?
         ORDER BY sortorder DESC LIMIT 1',
        [$blockid, $courseid, $current->sortorder]
    );
    if ($previous) {
        $DB->set_field('block_bloquecero_guides', 'sortorder', $current->sortorder, ['id' => $previous->id]);
        $DB->set_field('block_bloquecero_guides', 'sortorder', $previous->sortorder, ['id' => $current->id]);
    }
    redirect($PAGE->url);
}

// Handle move down action.
if ($action === 'movedown' && $guideid && confirm_sesskey()) {
    $current = $DB->get_record('block_bloquecero_guides', ['id' => $guideid], '*', MUST_EXIST);
    $next = $DB->get_record_sql(
        'SELECT * FROM {block_bloquecero_guides}
         WHERE blockinstanceid = ? AND courseid = ? AND sortorder > ?
         ORDER BY sortorder ASC LIMIT 1',
        [$blockid, $courseid, $current->sortorder]
    );
    if ($next) {
        $DB->set_field('block_bloquecero_guides', 'sortorder', $current->sortorder, ['id' => $next->id]);
        $DB->set_field('block_bloquecero_guides', 'sortorder', $next->sortorder, ['id' => $current->id]);
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageguides', 'block_bloquecero'));

$addurl = new moodle_url('/blocks/bloquecero/edit_guide.php', ['courseid' => $courseid, 'blockid' => $blockid]);
echo html_writer::link($addurl, get_string('addguide', 'block_bloquecero'), ['class' => 'btn btn-primary mb-3']);

$guides = $DB->get_records(
    'block_bloquecero_guides',
    ['courseid' => $courseid, 'blockinstanceid' => $blockid],
    'sortorder ASC'
);

if (empty($guides)) {
    echo $OUTPUT->notification(get_string('noguidesyet', 'block_bloquecero'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('guide_label', 'block_bloquecero'),
        get_string('guide_url', 'block_bloquecero'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    $guidesarray = array_values($guides);
    $total = count($guidesarray);

    foreach ($guidesarray as $index => $guide) {
        $editurl = new moodle_url('/blocks/bloquecero/edit_guide.php', [
            'courseid' => $courseid,
            'blockid'  => $blockid,
            'guideid'  => $guide->id,
        ]);
        $deleteurl = new moodle_url('/blocks/bloquecero/manage_guides.php', [
            'courseid' => $courseid,
            'blockid'  => $blockid,
            'action'   => 'delete',
            'guideid'  => $guide->id,
            'sesskey'  => sesskey(),
        ]);

        $urlhtml = !empty($guide->url)
            ? html_writer::link($guide->url, shorten_text($guide->url, 60), ['target' => '_blank'])
            : '-';

        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));

        if ($index > 0) {
            $moveupurl = new moodle_url('/blocks/bloquecero/manage_guides.php', [
                'courseid' => $courseid,
                'blockid'  => $blockid,
                'action'   => 'moveup',
                'guideid'  => $guide->id,
                'sesskey'  => sesskey(),
            ]);
            $actions .= ' ' . html_writer::link($moveupurl, $OUTPUT->pix_icon('t/up', get_string('moveup')));
        }

        if ($index < $total - 1) {
            $movedownurl = new moodle_url('/blocks/bloquecero/manage_guides.php', [
                'courseid' => $courseid,
                'blockid'  => $blockid,
                'action'   => 'movedown',
                'guideid'  => $guide->id,
                'sesskey'  => sesskey(),
            ]);
            $actions .= ' ' . html_writer::link($movedownurl, $OUTPUT->pix_icon('t/down', get_string('movedown')));
        }

        $actions .= ' ' . html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')), [
            'onclick' => 'return confirm("' . get_string('confirmdeleteguide', 'block_bloquecero') . '");',
        ]);

        $labelhtml = !empty($guide->name)
            ? format_string($guide->name)
            : '<em>' . get_string('teacherguide', 'block_bloquecero') . '</em>';

        $table->data[] = [$labelhtml, $urlhtml, $actions];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
