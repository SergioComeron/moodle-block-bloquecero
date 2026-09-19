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
 * AJAX endpoint: returns combined Gantt HTML for the requested courses.
 *
 * POST params:
 *   courseids - JSON-encoded array of int course IDs
 *   sesskey   - Moodle session key
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php');

require_login();
require_sesskey();

// Collect and validate course IDs.
$courseidsjson = required_param('courseids', PARAM_RAW);
$courseids = json_decode($courseidsjson, true);
if (!is_array($courseids) || empty($courseids)) {
    echo json_encode(['html' => '', 'error' => 'No courses']);
    die;
}
$courseids = array_map('intval', $courseids);
$courseids = array_unique($courseids);

/**
 * Collect Gantt data (sections + activities + sessions) for one course.
 *
 * @param stdClass $course  Full course record (needs id, format, startdate, enddate).
 * @param stdClass|null $blockconfig  Unserialized block instance config (may be null).
 * @param int $userid
 * @param int $blockinstanceid  ID of the block_instances row (for session lookup).
 * @return array  Keys: coursename, allsections, activities, sessions, rangestart, rangeend.
 */
function bloquecero_gantt_course_data(stdClass $course, $blockconfig, int $userid, int $blockinstanceid = 0): array {
    global $DB, $OUTPUT;

    $modinfo = get_fast_modinfo($course, $userid);
    $format  = $course->format;

    // DST-safe base date for weeks format.
    $weeksbase = null;
    if ($format === 'weeks') {
        $tz = core_date::get_user_timezone_object();
        $weeksbase = new DateTime('@' . $course->startdate);
        $weeksbase->setTimezone($tz);
        $weeksbase->setTime(0, 0, 0);
    }

    $rangestart = 0;
    $rangeend   = 0;

    // --- Sections ---
    $allsections = []; // sectionnum => [name, start, end, sectionnum].
    foreach ($modinfo->get_section_info_all() as $sec) {
        if ($sec->section == 0) {
            continue;
        }
        if (!empty($sec->component) && $sec->component === 'mod_subsection') {
            continue;
        }
        if (!\block_bloquecero\visibility::show_section($sec)) {
            continue;
        }
        $secname  = format_string($sec->name ?: get_string('section', 'moodle') . ' ' . $sec->section);
        $secstart = 0;
        $secend   = 0;

        if ($format === 'weeks' && $weeksbase !== null) {
            $dtstart = clone $weeksbase;
            $dtstart->modify('+' . ($sec->section - 1) . ' weeks');
            $secstart = $dtstart->getTimestamp();
            $dtend    = clone $dtstart;
            $dtend->modify('+1 week');
            $secend = $dtend->getTimestamp() - 1;
        } else if (!empty($blockconfig)) {
            $enablekey = 'section_enabled_' . $sec->id;
            if (!empty($blockconfig->$enablekey)) {
                $startkey = 'section_start_' . $sec->id;
                $endkey   = 'section_end_'   . $sec->id;
                $secstart = isset($blockconfig->$startkey) ? (int)$blockconfig->$startkey : 0;
                $secend   = isset($blockconfig->$endkey) ? (int)$blockconfig->$endkey : 0;
            }
        }

        $allsections[(int)$sec->section] = [
            'name'       => $secname,
            'start'      => $secstart,
            'end'        => $secend,
            'sectionnum' => (int)$sec->section,
        ];

        if ($secstart > 0 && $secend > 0) {
            if ($rangestart === 0 || $secstart < $rangestart) {
                $rangestart = $secstart;
            }
            if ($secend > $rangeend) {
                $rangeend = $secend;
            }
        }
    }

    // --- Activities ---
    $activities = [];
    foreach (\block_bloquecero\course_order::listed_cms($modinfo) as $listed) {
        $cm = $listed['cm'];
        if (!\block_bloquecero\visibility::show_cm($cm)) {
            continue;
        }
        if (in_array($cm->modname, ['label', 'subsection'])) {
            continue;
        }

        $cmdates  = \block_bloquecero\activity_dates::for_cm($cm);
        $actstart = $cmdates['start'];
        $actend   = $cmdates['end'];

        if (!$actstart && !$actend) {
            continue;
        }

        if ($rangestart === 0 || $actstart < $rangestart) {
            $rangestart = $actstart;
        }
        if ($actend > $rangeend) {
            $rangeend = $actend;
        }

        $icon = $OUTPUT->pix_icon('icon', $cm->modfullname, $cm->modname, ['class' => 'activityicon']);

        $activities[] = [
            'name'       => format_string($cm->name),
            'icon'       => $icon,
            'start'      => $actstart,
            'end'        => $actend,
            'modname'    => $cm->modname,
            'sectionnum' => (int) $listed['sectionnum'],
            'restricted' => \block_bloquecero\visibility::is_restricted($cm),
            'cmid'       => (int) $cm->id,
            'pred_cmids' => \block_bloquecero\visibility::completion_predecessor_cmids($cm),
        ];
    }

    // --- Live sessions ---
    $sessions = [];
    $sessionrecs = \block_bloquecero\category_filter::get_sessions((int) $course->id, $blockinstanceid);
    foreach ($sessionrecs as $s) {
        $sessions[] = [
            'titulo' => $s->name,
            'fecha'  => (int)$s->sessiondate,
        ];
        // Extend range to include session date.
        if ($rangestart === 0 || (int)$s->sessiondate < $rangestart) {
            $rangestart = (int)$s->sessiondate;
        }
        if ((int)$s->sessiondate > $rangeend) {
            $rangeend = (int)$s->sessiondate;
        }
    }

    return [
        'coursename'  => format_string($course->fullname),
        'courseid'    => $course->id,
        'allsections' => $allsections,
        'activities'  => $activities,
        'sessions'    => $sessions,
        'rangestart'  => $rangestart,
        'rangeend'    => $rangeend,
    ];
}

// --- Collect data for each valid course ---
$coursesdata = [];
$globalrangestart = 0;
$globalrangeend   = 0;

foreach ($courseids as $cid) {
    $course = $DB->get_record('course', ['id' => $cid, 'visible' => 1]);
    if (!$course) {
        continue;
    }
    $coursecontext = context_course::instance($cid);
    if (!is_enrolled($coursecontext, $USER->id, '', true)) {
        continue;
    }

    // Load block instance config for this course.
    $blockinstance = $DB->get_record_sql(
        "SELECT bi.id, bi.configdata
           FROM {block_instances} bi
           JOIN {context} ctx ON ctx.id = bi.parentcontextid
          WHERE bi.blockname = 'bloquecero'
            AND ctx.contextlevel = ?
            AND ctx.instanceid = ?
          LIMIT 1",
        [CONTEXT_COURSE, $cid]
    );
    $blockconfig = null;
    if ($blockinstance && !empty($blockinstance->configdata)) {
        $blockconfig = unserialize(base64_decode($blockinstance->configdata));
    }

    $blockinstanceid = $blockinstance ? (int)$blockinstance->id : 0;
    $data = bloquecero_gantt_course_data($course, $blockconfig, $USER->id, $blockinstanceid);

    $coursesdata[] = $data;

    if ($data['rangestart'] > 0) {
        if ($globalrangestart === 0 || $data['rangestart'] < $globalrangestart) {
            $globalrangestart = $data['rangestart'];
        }
    }
    if ($data['rangeend'] > $globalrangeend) {
        $globalrangeend = $data['rangeend'];
    }
}

if (empty($coursesdata) || $globalrangestart === 0) {
    echo json_encode(['html' => '<p>' . get_string('nosessionsscheduled', 'block_bloquecero') . '</p>']);
    die;
}

// --- Build unified week columns (DST-safe) ---
$tz = core_date::get_user_timezone_object();
$dt = new DateTime('@' . $globalrangestart);
$dt->setTimezone($tz);
$dt->setTime(0, 0, 0);
// Rewind to Monday of that week.
$dow = (int)$dt->format('N'); // 1=Mon … 7=Sun.
if ($dow > 1) {
    $dt->modify('-' . ($dow - 1) . ' days');
}

$ganttweeks    = [];
$ganttweekends = [];
while ($dt->getTimestamp() <= $globalrangeend && count($ganttweeks) < 80) {
    $ganttweeks[] = $dt->getTimestamp();
    $dt->modify('+1 week');
    $ganttweekends[] = $dt->getTimestamp() - 1;
}

$numweeks = count($ganttweeks);
if ($numweeks === 0) {
    echo json_encode(['html' => '']);
    die;
}

// --- Render combined HTML ---
$now            = time();
$currentweekidx = -1;
foreach ($ganttweeks as $idx => $wts) {
    if ($now >= $wts && $now <= $ganttweekends[$idx]) {
        $currentweekidx = $idx;
        break;
    }
}

$html  = '<div style="overflow-x:auto;">';
$html .= '<table class="bloquecero-gantt-table">';

// Header row.
$html .= '<thead><tr><th class="bloquecero-gantt-sectioncol">' . get_string('section', 'moodle') . '</th>';
foreach ($ganttweeks as $idx => $wts) {
    $weekend     = $ganttweekends[$idx];
    $label       = userdate($wts, '%d/%m') . '<br>' . userdate($weekend, '%d/%m');
    $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
    $html .= '<th class="bloquecero-gantt-weekcol' . $currentclass . '">' . $label . '</th>';
}
$html .= '</tr></thead><tbody>';

// One group per course.
foreach ($coursesdata as $cdata) {
    // Course header row (full span).
    $colspan = $numweeks + 1;
    $html .= '<tr><td colspan="' . $colspan . '" class="bloquecero-gantt-courseheader">'
        . htmlspecialchars($cdata['coursename']) . '</td></tr>';

    $activitiesbysection = [];
    foreach ($cdata['activities'] as $act) {
        $activitiesbysection[$act['sectionnum']][] = $act;
    }

    foreach ($cdata['allsections'] as $sectionnum => $sec) {
        $hasactivities = !empty($activitiesbysection[$sectionnum]);
        $hasdates      = ($sec['start'] > 0 && $sec['end'] > 0);
        if (!$hasdates && !$hasactivities) {
            continue;
        }

        // Section row.
        $html .= '<tr data-gantt-type="section"><td class="bloquecero-gantt-sectionname">' . htmlspecialchars($sec['name']) . '</td>';
        foreach ($ganttweeks as $idx => $wts) {
            $weekend      = $ganttweekends[$idx];
            $active       = ($hasdates && $sec['start'] <= $weekend && $sec['end'] >= $wts);
            $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
            $cellclass    = 'bloquecero-gantt-cell' . ($active ? ' bloquecero-gantt-active' : '') . $currentclass;
            $html .= '<td class="' . $cellclass . '"></td>';
        }
        $html .= '</tr>';

        // Activity rows.
        if ($hasactivities) {
            $sectionacts = \block_bloquecero\visibility::annotate_gantt_chains($activitiesbysection[$sectionnum]);
            foreach ($sectionacts as $act) {
                $modtype = htmlspecialchars($act['modname'] ?? 'other');
                $rowclasses = [];
                if (!empty($act['restricted'])) {
                    $rowclasses[] = 'bloquecero-restricted';
                }
                if (!empty($act['chain'])) {
                    $rowclasses[] = 'bloquecero-gantt-chained';
                }
                $rowclass = $rowclasses ? ' class="' . implode(' ', $rowclasses) . '"' : '';
                $html .= '<tr data-gantt-type="' . $modtype . '"' . $rowclass . '><td class="bloquecero-gantt-sectionname bloquecero-gantt-activityname">'
                    . \block_bloquecero\visibility::gantt_activity_label_html($act) . '</td>';
                foreach ($ganttweeks as $idx => $wts) {
                    $weekend      = $ganttweekends[$idx];
                    $active       = ($act['start'] <= $weekend && $act['end'] >= $wts);
                    $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
                    $cellclass    = 'bloquecero-gantt-cell' . ($active ? ' bloquecero-gantt-activity' : '') . $currentclass;
                    $html .= '<td class="' . $cellclass . '"></td>';
                }
                $html .= '</tr>';
            }
        }
    }

    // Sessions row for this course.
    if (!empty($cdata['sessions'])) {
        $html .= '<tr data-gantt-type="livesession"><td class="bloquecero-gantt-sectionname bloquecero-gantt-sessionrow">'
            . get_string('livesessions', 'block_bloquecero') . '</td>';
        foreach ($ganttweeks as $idx => $wts) {
            $weekend      = $ganttweekends[$idx] + 1;
            $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
            $weeksessions = array_filter($cdata['sessions'], function ($s) use ($wts, $weekend) {
                return $s['fecha'] >= $wts && $s['fecha'] < $weekend;
            });
            if (!empty($weeksessions)) {
                $titles = implode('&#10;', array_map(function ($s) {
                    return htmlspecialchars($s['titulo']) . ' (' . userdate($s['fecha'], '%d/%m %H:%M') . ')';
                }, $weeksessions));
                $count  = count($weeksessions);
                $marker = $count > 1 ? $count : '&#9679;';
                $html .= '<td class="bloquecero-gantt-cell bloquecero-gantt-session' . $currentclass
                    . '" title="' . $titles . '" data-bs-toggle="tooltip" data-bs-placement="top">' . $marker . '</td>';
            } else {
                $html .= '<td class="bloquecero-gantt-cell' . $currentclass . '"></td>';
            }
        }
        $html .= '</tr>';
    }
}

$html .= '</tbody></table></div>';

echo json_encode(['html' => $html]);
