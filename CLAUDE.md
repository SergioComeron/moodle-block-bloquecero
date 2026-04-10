# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Moodle block plugin (`block_bloquecero`) that provides a customized course header interface for Moodle courses. The block displays teacher information, course sections with activities, forums, bibliography, and integrates with external services (Zoom sessions via API).

**Key characteristics:**
- Designed for Moodle 3.11+ (version.php:6)
- Single large monolithic block class (~3300+ lines in block_bloquecero.php)
- Heavy inline JavaScript and CSS within PHP
- Spanish-primary codebase with English translations
- Complex DOM manipulation for hiding/showing course elements
- **Only one instance allowed per course** (`$this->instance_allow_multiple = false`)

## Architecture

### Core Files

**block_bloquecero.php** (main block class - ~120KB)
- `class block_bloquecero extends block_base` with two main methods:
  - `init()`: Sets block title and `$this->instance_allow_multiple = false`
  - `get_content()`: Generates all block HTML, JavaScript, and CSS (main rendering method)
- `get_cm_start_date($cm)`: Helper function to extract activity start dates based on module type (assign, quiz). For assign: uses `allowsubmissionsfromdate` or `duedate` as fallback. For quiz: uses `timeopen` or `timeclose` as fallback (so quizzes without open date but with close date still appear).
- Contains extensive inline HTML generation with embedded JavaScript for UI interactions
- Implements course section navigation with carousel-style week/topic browsing
- Handles teacher profile display with contact information toggles
- Integrates Zoom session fetching via fetch_sessions.php

**edit_form.php** (block configuration form)
- `class block_bloquecero_edit_form extends block_edit_form`
- Configuration fields:
  - Guide URL (course guide link)
  - Forum selectors (announcements, tutorials, students)
  - Per-teacher settings (phone, office hours) - with role-based visibility:
    - **Professors** see and edit only their own fields
    - **Managers/admins** (`moodle/role:assign` capability or `is_siteadmin()`) see all teachers with one collapsible header per teacher (`setExpanded(false)`), avoiding excessive scroll
  - Teacher selection checkboxes (`config_teacher_selected_{userid}`) - select which teachers to display
  - Bibliography management - Link to dedicated management page
  - Live sessions management - Link to dedicated management page
  - Max activities per section (1-10, default 4)
- Custom data loading in `set_data()` for editor fields — loads `userschedule_` for all editable teachers based on role

**manage_sessions.php** (live sessions management page)
- Dedicated interface for managing live sessions
- Table display of all sessions with edit/delete actions (columns: name, date, duration, description, calendar sync, actions)
- Requires `block/bloquecero:managesessions` capability
- Shows session sync status with calendar
- Links to edit_session.php for adding/editing

**edit_session.php** (session form page)
- Form for adding/editing individual sessions
- Handles calendar synchronization (create/update/delete events)
- Uses session_form.php for form definition

**session_form.php** (session form definition)
- MoodleForm for session fields:
  - Name (text, required)
  - Date/time (date_time_selector, required)
  - Duration (duration selector, default 1 hour)
  - Description (textarea, optional)
  - Sync with calendar (checkbox, default enabled)
- No past-date validation — sessions with past dates can be edited freely

**manage_bibliography.php** (bibliography management page)
- Dedicated interface for managing bibliography entries
- Table display with edit/delete/reorder actions
- Requires `block/bloquecero:managebibliography` capability
- Supports moving entries up/down to change display order

**edit_bibliography.php** (bibliography form page)
- Form for adding/editing individual bibliography entries
- Auto-prefixes URLs with https:// if missing protocol
- Uses bibliography_form.php for form definition

**bibliography_form.php** (bibliography form definition)
- MoodleForm for bibliography fields:
  - Name (text, required)
  - URL (text, optional)
  - Description (textarea, optional)

**lib.php** (plugin file handling)
- `block_bloquecero_pluginfile()`: Serves header background images stored in system context
- Only handles 'header_bg' filearea
- Requires login for access

**settings.php** (admin settings)
- Single setting: header background image upload (PNG, JPG, JPEG, GIF)
- Stored in system context with filearea 'header_bg'

**fetch_sessions.php** (Zoom integration endpoint)
- Fetches Zoom session data via OAuth2 API
- Determines user role (student/teacher/manager) across system/category/course contexts
- Returns HTML for live Zoom sessions with collapsible interface
- Can use test data mode via `block_zoom_udima/use_testdata` config
- **Note**: References `block_zoom_udima` config instead of `block_bloquecero` - indicates shared or copied code

### Database/Permissions

**db/access.php** (capabilities)
- `block/bloquecero:addinstance` - Add block to course (manager/admin)
- `block/bloquecero:myaddinstance` - Add to My Moodle page (manager/admin)
- `block/bloquecero:viewcourse` - View "show course" toggle button (teachers/managers)
- `block/bloquecero:managesessions` - Manage live sessions (managers/editing teachers)
- `block/bloquecero:managebibliography` - Manage bibliography (managers/editing teachers)

**db/install.xml** (database schema)
- Table: `block_bloquecero_sessions`
  - id, blockinstanceid, courseid
  - name, sessiondate, duration, description
  - calendarid (link to Moodle calendar event)
  - timecreated, timemodified
  - Index: courseid + sessiondate
- Table: `block_bloquecero_bibliography`
  - id, blockinstanceid, courseid
  - name, url, description
  - sortorder (for manual ordering)
  - timecreated, timemodified
  - Index: courseid + sortorder

**db/upgrade.php** (database upgrades)
- Version 2025061701: Creates block_bloquecero_sessions table
- Version 2025061702: Creates block_bloquecero_bibliography table + migrates existing config data
- Version 2025061703: Adds description field to bibliography table
- Version 2025061704: Safety net - ensures bibliography table exists for installs that skipped 2025061702

### Localization

**lang/en/** and **lang/es/** (translations)
- String definitions for all UI labels
- Help text for configuration options
- Activity/forum labels

## Key Behavioral Patterns

### Block Visibility and Context
- Block only displays on course main page (checks for `course-view-weeks`, `course-view-topics`, `course-view` pagetypes)
- Hidden when viewing specific sections (`section` parameter > 0)
- Toggle button shows/hides course elements (only visible when not editing)

### DOM Manipulation Strategy
The block extensively hides/shows Moodle UI elements:
- In editing mode: Forces display of all elements via JavaScript
- In normal mode: Provides toggle button to show/hide:
  - `#region-main`
  - All `.block` elements
  - Header selectors: `.page-header`, `.course-header`, etc.
  - Tab navigation: `.nav-tabs`, `.secondary-navigation`, etc.

### Teacher Information Display
- Fetches users with `moodle/course:update` capability
- **Teacher selection**: Checkboxes in edit_form.php (`config_teacher_selected_{userid}`) control which teachers are visible
  - If no selection config exists (retrocompatibility), all teachers are shown
  - If selection exists, only teachers with `teacher_selected_{id} = 1` are displayed
- Stores per-teacher config as `userphone_{userid}` and `userschedule_{userid}`
- **Role-based editing**: Teachers edit only their own data; managers/admins can edit all teachers
- Displays teacher cards with photo, contact info (toggleable via `.bloquecero-teacher-btn`)
- Teacher name buttons have a dotted underline and a circular `i` info icon (`.bloquecero-teacher-infoicon`) to signal interactivity
- Teachers separated by `·` middle dot instead of comma
- Teacher names and course dates are rendered **outside** the header image container (`.bloquecero-info-row`) to prevent clipping on smaller windows

### Section/Activity Navigation
- Implements carousel-style navigation for course sections (weeks/topics)
- **Subsections are excluded** from the carousel (`$section->component === 'mod_subsection'`); they are shown nested inside their parent section cards
- Filters activities per section based on `maxactivitiespersection` config
- Shows activity icons, types, dates (start/end)
- Groups activities by section with expand/collapse functionality
- **Section cards**: only the title link and individual activity links are clickable — the card container itself has `cursor: default` (`.bloquecero-section-card { cursor: default }`)
- **Hidden activity indicators**: teachers/managers with `moodle/course:update` see a "Hidden from students" badge on hidden activities in section cards, the weekly activities card, and the activities modal. Checked via `$can_view_hidden = has_capability('moodle/course:update', $coursecontext)`

### Bibliography Management (v0.3+)
**Database-driven with dedicated management interface:**
- Entries stored in `block_bloquecero_bibliography` table (one row per entry)
- Management page (manage_bibliography.php) accessible from block configuration
- Each entry has: name, url (optional), description (optional), sortorder
- Entries scoped to block instance and course
- Display in block via modal popup:
  - Shows all entries ordered by sortorder
  - Name displayed as link if URL provided, otherwise plain text
  - Description shown below name in smaller gray text
  - Shows "No se ha añadido bibliografía todavía" when empty

**Entry Management:**
- Add/edit entries via dedicated form (edit_bibliography.php)
- Auto-prefixes URLs with https:// if missing protocol
- Reorder entries using move up/down buttons in management table
- Delete entries with confirmation

### Live Sessions Management (v0.2+)
**Database-driven with dedicated management interface:**
- Sessions stored in `block_bloquecero_sessions` table (one row per session)
- Management page (manage_sessions.php) accessible from block configuration
- Each session has: name, date/time, duration, description, calendar sync status
- Sessions scoped to block instance and course
- Display in block (block_bloquecero.php:475-498):
  - Only shows future sessions or sessions within last 2 hours (7200 seconds)
  - Queries database directly using block instance ID
  - Ordered by date (chronological)
  - Shows "No hay sesiones programadas" when no active sessions

**Calendar Integration:**
- Optional sync with Moodle course calendar
- Creates/updates/deletes calendar events automatically
- Calendar event ID stored in session record
- Events marked as "course" type, visible to all course participants
- When session edited: calendar event updated
- When session deleted: calendar event removed
- Sync can be enabled/disabled per session

## Development Workflow

### Testing
No test files exist in this plugin. To test changes:
1. Install in Moodle blocks directory: `blocks/bloquecero/`
2. Navigate to Site administration > Notifications to trigger install/upgrade
3. Add block to a course page
4. Test configuration via block settings gear icon
5. Test as different user roles (student, teacher, manager)

### Moodle Requirements
- Minimum Moodle 3.11 (version.php: `requires = 2021051700`)
- Uses Moodle format library: `require_once($CFG->dirroot.'/course/format/weeks/lib.php')`
- Uses forum library: `require_once($CFG->dirroot . '/mod/forum/lib.php')`

### Plugin File Serving
Header background images are served via Moodle's pluginfile.php:
```
/pluginfile.php/{contextid}/block_bloquecero/header_bg/0/{filename}
```
- Context: system level only
- Item ID: always 0
- Configured in settings.php with `admin_setting_configstoredfile`

### Configuration Storage
Block instance config stored as object properties:
- Simple fields: `$this->config->guide_url`, `$this->config->forumid`
- Per-user fields: `$this->config->userphone_{userid}`, `$this->config->userschedule_{userid}`
- Teacher selection: `$this->config->teacher_selected_{userid}` (1 = visible, 0 = hidden)

**Sessions and Bibliography are stored in database tables** (not in config):
- `block_bloquecero_sessions` - Live sessions data
- `block_bloquecero_bibliography` - Bibliography entries
- Both linked by blockinstanceid and courseid
- Managed through dedicated UI pages
- More scalable than config arrays

## Code Organization Notes

### Inline vs. External Assets
- **No separate JS/CSS files** - all JavaScript and CSS are embedded in block_bloquecero.php as strings
- CSS uses inline `<style>` tags in generated HTML
- JavaScript uses `<script>` tags and `$PAGE->requires->js_init_code()`

### Naming Conventions
- PHP: snake_case for functions, properties
- CSS classes: kebab-case (e.g., `bloquecero-teacher-btn`)
- JavaScript: camelCase for functions (e.g., `toggleContactInfo()`)
- Config keys: underscore_separated (e.g., `userphone_`, `userschedule_`)

### Debugging
- fetch_sessions.php includes timing instrumentation via `microtime(true)`
- Check browser console for JavaScript errors
- Check Moodle debug output: Site administration > Development > Debugging

## Known Integration Points

### External Dependencies
- **Zoom API integration** via fetch_sessions.php (uses OAuth2 client credentials flow)
- Requires separate `block_zoom_udima` plugin configuration for:
  - `client_id`, `client_secret`, `token_url`, `scope`
  - `baseurl`, `campus`, `mostrar_api_url`, `use_testdata`

### Forum Integration
- Uses Moodle forum module functions: `forum_get_discussions_unread()`
- Requires course forum IDs configured in block settings
- Displays unread counts as badges

### Course Format Dependencies
- Heavily tied to weeks/topics course formats
- Uses course section structure from `$course->sections`
- Relies on Moodle's course format API for section data

## Common Modifications

### Adding Configuration Fields
1. Add form element in `edit_form.php::specific_definition()`
2. Set default with `$mform->setDefault('config_fieldname', 'value')`
3. Access in block via `$this->config->fieldname`
4. Add language string in `lang/*/block_bloquecero.php`

### Managing Live Sessions (v0.2+)
**Access Management Page:**
1. Go to block configuration (gear icon)
2. Under "Sesiones en directo" section, click "Gestionar sesiones" button
3. Opens manage_sessions.php in new tab

**Adding Sessions:**
- Click "Add another session" button
- Fill form: name, date/time, duration, description
- Enable "Sync with Moodle calendar" to add to course calendar
- Save - session appears in block and optionally in calendar

**Editing Sessions:**
- Click edit icon in sessions table
- Modify fields
- Calendar event auto-updates if sync enabled
- Can enable/disable calendar sync on edit

**Deleting Sessions:**
- Click delete icon, confirm
- Session removed from database
- Calendar event deleted if exists

**Display Logic:**
- block_bloquecero.php:475-498 queries database
- Filter: `sessiondate >= (current_time - 7200)` shows recent (2hr) and future
- Ordered by date ascending
- Empty state: "No hay sesiones programadas"

**Calendar Sync Implementation:**
- Function: `block_bloquecero_create_calendar_event()` in edit_session.php
- Creates `calendar_event` object with course scope
- Event type: 'course', visible to all course participants
- Duration calculated from session duration field
- Event ID stored in session.calendarid for updates/deletes
- Three scenarios covered automatically:
  1. Sync enabled + event exists → updates name, description, date, duration
  2. Sync enabled + no event → creates new event
  3. Sync disabled + event exists → deletes event, sets calendarid = null

**Sessions modal:**
- Shows description below session title (formatted HTML)
- Shows weekday name + full date + time + duration

### Managing Bibliography (v0.3+)
**Access Management Page:**
1. Go to block configuration (gear icon)
2. Under "Bibliografía" section, click "Gestionar bibliografía" button
3. Opens manage_bibliography.php in new tab

**Adding Entries:**
- Click "Añadir entrada de bibliografía" button
- Fill form: name (required), URL (optional), description (optional)
- Save - entry appears in block modal

**Editing Entries:**
- Click edit icon in bibliography table
- Modify fields
- Save changes

**Reordering Entries:**
- Use up/down arrow icons in management table
- Order is preserved via sortorder field

**Deleting Entries:**
- Click delete icon, confirm
- Entry removed from database

**Display Logic:**
- block_bloquecero.php queries `block_bloquecero_bibliography` table
- Ordered by sortorder ascending
- Displayed in modal popup when user clicks "Bibliografía" button
- Empty state: "No se ha añadido bibliografía todavía"

### Changing Activity Display Logic
Edit the section/activity rendering code in `block_bloquecero::get_content()`:
- Section carousel logic: ~lines 500-600
- Activity filtering: search for `maxactivitiespersection`
- Activity icons/dates: search for `get_cm_start_date()`

**Date display in "Actividades" card (week selector card):**
- Each activity shows `(Inicio: dd mmm yyyy · Fin: dd mmm yyyy)` when the end date differs from start
- Built in `$calendarActivities` list; `$duedateHtml` is appended when `$duedate !== $startdate`
- For assign: startdate = `allowsubmissionsfromdate`, duedate = `duedate`
- For quiz: startdate = `timeopen` (or `timeclose` fallback), duedate = `timeclose`

**Date display in activities modal table (calendar icon):**
- "Vence" column shows relative text ("En X días", "Vencida hace X días") + actual date below
- `dueDateStr` always shown when `activity.duedate` is non-zero (regardless of startdate equality)

### Modifying Teacher Display
- Teacher selection checkboxes: edit_form.php:75-83
- Teacher data collection and filtering: block_bloquecero.php:80-155
- Teacher card HTML: block_bloquecero.php:267-302
- Info row (dates + teachers): `.bloquecero-info-row` outside `.bloquecero-header-responsive`

### Styling Changes
Locate inline `<style>` blocks in block_bloquecero.php:
- Main block styles start around line 300+
- Carousel styles around line 500+
- Teacher card styles around line 230+
- Search for `.bloquecero-` class prefix

## Git Workflow

Branch strategy:
- **Feature/fix branches** → merge to `dev` via pull request
- **`dev`** → main development branch; merge to `master` for releases
- **`master`** → release branch; triggers CI in GitHub Actions on push

When creating pull requests, target the `dev` branch, not `master`.

After merging to master and pushing, always sync back to dev:
```bash
git checkout dev && git merge master && git push origin dev
```
If the CI commits back to master (e.g. CHANGELOG update) while you have local commits,
the branches diverge. Use `git pull --rebase origin master` to resolve it.

### Local hooks
Run once after cloning to install the pre-push hook:
```bash
bash scripts/install-hooks.sh
```
The hook (`scripts/pre-push`) runs on every push and does:
1. Code sniffer (phpcs) — blocks on style errors
2. PHPUnit — blocks if tests fail
3. **Auto version bump** (only on push to `master`):
   - Analyzes commits since last tag: any `feat:` → minor bump; otherwise → patch bump
   - Updates `$plugin->version` (YYYYMMDDXX) and `$plugin->release` in `version.php`
   - Commits the bump and **exits 1** (git does not include hook commits in the current push)
   - On the **second** `git push origin master` the hook detects the bump is already done and lets the push through

**Push to master workflow:**
```bash
git push origin master   # 1st attempt: bumps version, commits, exits with error
git push origin master   # 2nd attempt: detects bump done, pushes everything
```
If PHPUnit fails after the bump due to version mismatch, reinitialize the test environment:
```bash
php admin/tool/phpunit/cli/init.php
```

To autocorrect code style issues: `vendor/bin/phpcbf --standard=moodle --extensions=php --ignore=vendor .`

### Version numbering
- `$plugin->version` — build number in `YYYYMMDDXX` format (XX = sequence within the day)
- `$plugin->release` — semantic version (`MAJOR.MINOR` or `MAJOR.MINOR.PATCH`)
- Bump rules (auto-applied by hook on push to master):
  - Any `feat:` commit since last tag → **minor** bump (e.g. `0.4` → `0.5`)
  - Only `fix:`, `docs:`, etc. → **patch** bump (e.g. `0.5` → `0.5.1`)
  - **Major** bumps are always manual

### CI (GitHub Actions)
Three workflows defined in `.github/workflows/`:

**`ci.yml`** — triggers on push to `master`:
1. PHP syntax check
2. Moodle code style (moodle-cs / phpcs)
3. PHPUnit tests via `moodle-plugin-ci` (PHP 8.2 + 8.3, Moodle 4.5, pgsql)

**`auto-release.yml`** — triggers on push to `master` when `version.php` changes:
1. Extracts version from `$plugin->release`
2. Checks if the tag already exists (skips if so)
3. Generates a CHANGELOG entry grouped by commit type (`feat:` → Features, `fix:` → Fixes, `docs:` → Docs)
4. Commits the CHANGELOG update back to master (`[skip ci]`)
5. Creates and pushes the version tag (e.g. `v0.6`)

**`release.yml`** — triggers on new `v*` tag (created by auto-release):
1. PHP syntax check
2. Builds the plugin zip (excludes `.git`, `.github`, `vendor`, `CLAUDE.md`, etc.)
3. Extracts the changelog section for that version
4. Creates a GitHub Release with the zip attached

## Project Organization

### GitHub Integration
- **Issues**: Track bugs, features, and refactoring tasks
  - Use templates in `.github/ISSUE_TEMPLATE/`
  - Labels: bug, enhancement, refactor, urgente, wip, documentation
  - View at: https://github.com/SergioComeron/moodle-block-bloquecero/issues

- **Project Board**: https://github.com/users/SergioComeron/projects/1
  - Kanban-style task management
  - Linked to repository issues
  - Track development progress

### Documentation Files
- **README.md**: User-facing documentation and quick start
- **CLAUDE.md**: This file - technical context for AI assistants
- **CONTRIBUTING.md**: Contribution guidelines and workflow
- **CHANGELOG.md**: Version history following Keep a Changelog format

### Current Priority Issues
See GitHub issues for full list. Key technical debt:
1. [#1] Refactor: Separate inline CSS/JS to external files (~3300 lines in one file)
2. [#2] Refactor: Review block_zoom_udima dependency in fetch_sessions.php
3. [#3] Feature: Add PHPUnit and Behat automated tests

### Planned Features
- **Welcome message**: Field in block config for a teacher welcome message, displayed persistently in the block and auto-sent by email to each student at enrolment time via `\core\event\user_enrolment_created` observer + `email_to_user()`
