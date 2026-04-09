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
 * Manage bibliography for block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comeron <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$bibliographyid = optional_param('bibliographyid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/bloquecero:managebibliography', $context);

$PAGE->set_url('/blocks/bloquecero/manage_bibliography.php', ['courseid' => $courseid, 'blockid' => $blockid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managebibliography', 'block_bloquecero'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('managebibliography', 'block_bloquecero'));

// Handle delete action.
if ($action === 'delete' && $bibliographyid && confirm_sesskey()) {
    $DB->delete_records('block_bloquecero_bibliography', ['id' => $bibliographyid]);
    redirect($PAGE->url);
}

// Handle move up action.
if ($action === 'moveup' && $bibliographyid && confirm_sesskey()) {
    $current = $DB->get_record('block_bloquecero_bibliography', ['id' => $bibliographyid], '*', MUST_EXIST);
    $previous = $DB->get_record_sql(
        'SELECT * FROM {block_bloquecero_bibliography}
         WHERE blockinstanceid = ? AND courseid = ? AND sortorder < ?
         ORDER BY sortorder DESC LIMIT 1',
        [$blockid, $courseid, $current->sortorder]
    );
    if ($previous) {
        $DB->set_field('block_bloquecero_bibliography', 'sortorder', $current->sortorder, ['id' => $previous->id]);
        $DB->set_field('block_bloquecero_bibliography', 'sortorder', $previous->sortorder, ['id' => $current->id]);
    }
    redirect($PAGE->url);
}

// Handle move down action.
if ($action === 'movedown' && $bibliographyid && confirm_sesskey()) {
    $current = $DB->get_record('block_bloquecero_bibliography', ['id' => $bibliographyid], '*', MUST_EXIST);
    $next = $DB->get_record_sql(
        'SELECT * FROM {block_bloquecero_bibliography}
         WHERE blockinstanceid = ? AND courseid = ? AND sortorder > ?
         ORDER BY sortorder ASC LIMIT 1',
        [$blockid, $courseid, $current->sortorder]
    );
    if ($next) {
        $DB->set_field('block_bloquecero_bibliography', 'sortorder', $current->sortorder, ['id' => $next->id]);
        $DB->set_field('block_bloquecero_bibliography', 'sortorder', $next->sortorder, ['id' => $current->id]);
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('managebibliography', 'block_bloquecero'));

// Add bibliography button.
$addurl = new moodle_url('/blocks/bloquecero/edit_bibliography.php', ['courseid' => $courseid, 'blockid' => $blockid]);
echo html_writer::link($addurl, get_string('addbibliography', 'block_bloquecero'), ['class' => 'btn btn-primary mb-3']);

// Get bibliography entries for this course and block.
$entries = $DB->get_records(
    'block_bloquecero_bibliography',
    ['courseid' => $courseid, 'blockinstanceid' => $blockid],
    'sortorder ASC'
);

if (empty($entries)) {
    echo $OUTPUT->notification(get_string('nobibliographyyet', 'block_bloquecero'), 'info');
} else {
    // Create table.
    $table = new html_table();
    $table->head = [
        get_string('bookname', 'block_bloquecero'),
        get_string('bookdescription', 'block_bloquecero'),
        get_string('bookurl', 'block_bloquecero'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    $entriesarray = array_values($entries);
    $total = count($entriesarray);

    foreach ($entriesarray as $index => $entry) {
        $editurl = new moodle_url('/blocks/bloquecero/edit_bibliography.php', [
            'courseid' => $courseid,
            'blockid' => $blockid,
            'bibliographyid' => $entry->id,
        ]);
        $deleteurl = new moodle_url('/blocks/bloquecero/manage_bibliography.php', [
            'courseid' => $courseid,
            'blockid' => $blockid,
            'action' => 'delete',
            'bibliographyid' => $entry->id,
            'sesskey' => sesskey(),
        ]);

        $urlhtml = !empty($entry->url) ?
            html_writer::link($entry->url, shorten_text($entry->url, 50), ['target' => '_blank']) :
            '-';

        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));

        // Move up (if not first).
        if ($index > 0) {
            $moveupurl = new moodle_url('/blocks/bloquecero/manage_bibliography.php', [
                'courseid' => $courseid,
                'blockid' => $blockid,
                'action' => 'moveup',
                'bibliographyid' => $entry->id,
                'sesskey' => sesskey(),
            ]);
            $actions .= ' ' . html_writer::link($moveupurl, $OUTPUT->pix_icon('t/up', get_string('moveup')));
        }

        // Move down (if not last).
        if ($index < $total - 1) {
            $movedownurl = new moodle_url('/blocks/bloquecero/manage_bibliography.php', [
                'courseid' => $courseid,
                'blockid' => $blockid,
                'action' => 'movedown',
                'bibliographyid' => $entry->id,
                'sesskey' => sesskey(),
            ]);
            $actions .= ' ' . html_writer::link($movedownurl, $OUTPUT->pix_icon('t/down', get_string('movedown')));
        }

        $actions .= ' ' . html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')), [
            'onclick' => 'return confirm("' . get_string('confirmdeletebibliography', 'block_bloquecero') . '");',
        ]);

        $descriptionhtml = !empty($entry->description) ? shorten_text(format_string($entry->description), 60) : '-';

        $table->data[] = [
            format_string($entry->name),
            $descriptionhtml,
            $urlhtml,
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
