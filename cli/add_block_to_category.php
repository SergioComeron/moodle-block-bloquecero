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
 * CLI script to add the bloquecero block to every course within a category
 * (and, recursively, its subcategories).
 *
 * Usage examples:
 *   php add_block_to_category.php --category=12
 *   php add_block_to_category.php --category=12 --dry-run
 *   php add_block_to_category.php --idnumber=GRADO_INF
 *   php add_block_to_category.php --category=12 --region=side-pre --weight=-10
 *
 * @package    block_bloquecero
 * @copyright  2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get the cli options.
[$options, $unrecognised] = cli_get_params(
    [
        'help'     => false,
        'category' => null,
        'idnumber' => null,
        'region'        => 'side-pre',
        'weight'        => -10,
        'dry-run'       => false,
        'move-existing' => false,
    ],
    [
        'h' => 'help',
        'c' => 'category',
        'i' => 'idnumber',
        'r' => 'region',
        'w' => 'weight',
        'd' => 'dry-run',
        'm' => 'move-existing',
    ]
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

$help = "Add the bloquecero block to all courses in a category and its subcategories.

The block is only added to courses that do not already have an instance of it
(bloquecero allows a single instance per course).

Options:
  -c, --category=ID    Category id to process (the category and all its descendants).
  -i, --idnumber=TEXT  Category idnumber, as an alternative to --category.
  -r, --region=NAME    Block region (internal name) where the block is placed.
                       Default: side-pre.
  -w, --weight=N       Block weight (ordering within the region). Default: -10 (top).
  -m, --move-existing  Force courses that already have the block into the given
                       region, even if a teacher moved it elsewhere. Updates
                       block_instances.defaultregion and any per-page override in
                       block_positions. Without this flag, existing blocks are left
                       untouched (skipped).
  -d, --dry-run        Show what would be done without writing to the database.
  -h, --help           Print this help.

Region names (use the INTERNAL name, not the visible label). Boost Union adds,
besides 'side-pre': content-upper, content-lower, outside-top, outside-bottom,
outside-left, outside-right, header, footer-left, footer-center, footer-right,
offcanvas-left, offcanvas-center, offcanvas-right.
NOTE: the chosen region must be ENABLED in the theme settings for the course
layout, otherwise the block is inserted but stays orphaned/hidden.

Examples:
  php add_block_to_category.php --category=12
  php add_block_to_category.php --category=12 --region=content-upper
  php add_block_to_category.php --idnumber=GRADO_INF --dry-run
";

if ($options['help'] || (empty($options['category']) && empty($options['idnumber']))) {
    cli_writeln($help);
    exit(0);
}

// Resolve the target category.
if (!empty($options['idnumber'])) {
    $catid = $DB->get_field('course_categories', 'id', ['idnumber' => $options['idnumber']], IGNORE_MISSING);
    if (!$catid) {
        cli_error("No category found with idnumber '{$options['idnumber']}'.");
    }
} else {
    $catid = (int)$options['category'];
}

try {
    $rootcategory = core_course_category::get($catid, MUST_EXIST, true);
} catch (Exception $e) {
    cli_error("Category with id {$catid} not found.");
}

// Make sure the block plugin is installed.
if (!array_key_exists('bloquecero', core_component::get_plugin_list('block'))) {
    cli_error("The block_bloquecero plugin is not installed in this site.");
}

$dryrun = !empty($options['dry-run']);
$region = $options['region'];
$weight = (int)$options['weight'];
$moveexisting = !empty($options['move-existing']);

// Soft validation against Boost Union course-layout regions, if that theme is present.
if (array_key_exists('boost_union', core_component::get_plugin_list('theme'))) {
    require_once($CFG->dirroot . '/theme/boost_union/locallib.php');
    if (function_exists('theme_boost_union_get_block_regions')) {
        $courseregions = theme_boost_union_get_block_regions('course');
        if (!in_array($region, $courseregions, true)) {
            cli_writeln("WARNING: region '{$region}' is not enabled in Boost Union for the "
                . "course layout. The block will be inserted but may stay hidden.");
            cli_writeln("Enabled course regions: " . implode(', ', $courseregions) . PHP_EOL);
        }
    }
}

// Collect the category and all its descendants.
$categoryids = array_merge([$rootcategory->id], $rootcategory->get_all_children_ids());

cli_heading('block_bloquecero - add to category');
cli_writeln("Root category : {$rootcategory->get_formatted_name()} (id {$rootcategory->id})");
cli_writeln("Categories    : " . count($categoryids) . " (including subcategories)");
cli_writeln("Region/Weight : {$region} / {$weight}");
cli_writeln("Mode          : " . ($dryrun ? 'DRY-RUN (no changes)' : 'LIVE') . PHP_EOL);

// Fetch all courses in those categories (excluding the site course).
[$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
$inparams['siteid'] = SITEID;
$courses = $DB->get_records_select(
    'course',
    "category {$insql} AND id <> :siteid",
    $inparams,
    'sortorder ASC',
    'id, fullname, shortname, category'
);

if (!$courses) {
    cli_writeln('No courses found in the selected categories. Nothing to do.');
    exit(0);
}

$created = 0;
$moved   = 0;
$skipped = 0;
$errors  = 0;

foreach ($courses as $course) {
    $coursecontext = context_course::instance($course->id);

    // Check whether the block already exists in this course.
    $existing = $DB->get_record('block_instances', [
        'blockname'       => 'bloquecero',
        'parentcontextid' => $coursecontext->id,
    ]);

    if ($existing) {
        // Detect whether the block (default region or any per-page override) sits
        // somewhere other than the requested region.
        $overrides = $DB->get_records_select(
            'block_positions',
            'blockinstanceid = :biid AND region <> :region',
            ['biid' => $existing->id, 'region' => $region]
        );
        $needsmove = ($existing->defaultregion !== $region) || !empty($overrides);

        if (!$needsmove) {
            $skipped++;
            cli_writeln("  [skip ] {$course->shortname} (id {$course->id}) - already in {$region}");
            continue;
        }

        if (!$moveexisting) {
            $skipped++;
            cli_writeln("  [skip ] {$course->shortname} (id {$course->id}) - present in "
                . "'{$existing->defaultregion}'" . (!empty($overrides) ? ' (+ page override)' : '')
                . " (use --move-existing to force into {$region})");
            continue;
        }

        if ($dryrun) {
            $moved++;
            cli_writeln("  [would] {$course->shortname} (id {$course->id}) - would be moved to {$region}");
            continue;
        }

        try {
            // Update the instance default region.
            if ($existing->defaultregion !== $region) {
                $DB->set_field('block_instances', 'defaultregion', $region, ['id' => $existing->id]);
            }
            // Force any per-page override (teacher manual placement) into the region.
            foreach ($overrides as $override) {
                $DB->set_field('block_positions', 'region', $region, ['id' => $override->id]);
            }
            $moved++;
            cli_writeln("  [moved] {$course->shortname} (id {$course->id}) - "
                . "'{$existing->defaultregion}' -> '{$region}'"
                . (!empty($overrides) ? ' (' . count($overrides) . ' override(s) forced)' : ''));
        } catch (Exception $e) {
            $errors++;
            cli_writeln("  [ERROR] {$course->shortname} (id {$course->id}) - " . $e->getMessage());
        }
        continue;
    }

    if ($dryrun) {
        $created++;
        cli_writeln("  [would] {$course->shortname} (id {$course->id}) - block would be added");
        continue;
    }

    try {
        $blockinstance = new stdClass();
        $blockinstance->blockname        = 'bloquecero';
        $blockinstance->parentcontextid  = $coursecontext->id;
        $blockinstance->showinsubcontexts = 0;
        $blockinstance->pagetypepattern  = 'course-view-*';
        $blockinstance->subpagepattern   = null;
        $blockinstance->defaultregion    = $region;
        $blockinstance->defaultweight    = $weight;
        $blockinstance->configdata       = '';
        $blockinstance->timecreated      = time();
        $blockinstance->timemodified     = $blockinstance->timecreated;
        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

        // Ensure the block context is created.
        context_block::instance($blockinstance->id);

        // Let the block run any additional setup.
        if ($block = block_instance('bloquecero', $blockinstance)) {
            $block->instance_create();
        }

        $created++;
        cli_writeln("  [added] {$course->shortname} (id {$course->id})");
    } catch (Exception $e) {
        $errors++;
        cli_writeln("  [ERROR] {$course->shortname} (id {$course->id}) - " . $e->getMessage());
    }
}

cli_writeln(PHP_EOL . str_repeat('-', 50));
cli_writeln("Courses processed : " . count($courses));
cli_writeln($dryrun ? "Would be added    : {$created}" : "Added             : {$created}");
cli_writeln($dryrun ? "Would be moved    : {$moved}" : "Moved             : {$moved}");
cli_writeln("Skipped           : {$skipped}");
if ($errors) {
    cli_writeln("Errors            : {$errors}");
}
cli_writeln('Done.');

exit($errors ? 1 : 0);
