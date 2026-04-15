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
 * Form for editing block_bloquecero instances
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_bloquecero_edit_form extends block_edit_form {
    /**
     * Form fields specific to this type of block
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $COURSE, $DB, $USER;

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Campo para la URL de la guía docente.
        $mform->addElement('text', 'config_guide_url', get_string('guide_url', 'block_bloquecero'));
        $mform->setType('config_guide_url', PARAM_URL);
        $mform->setDefault('config_guide_url', '');
        $mform->addHelpButton('config_guide_url', 'guide_url', 'block_bloquecero');

        // --- Selector de foro para el tablón de anuncios ---
        // Obtener todos los foros del curso.
        $forums = [];
        if (!empty($COURSE->id)) {
            $records = $DB->get_records('forum', ['course' => $COURSE->id]);
            foreach ($records as $forum) {
                $forums[$forum->id] = format_string($forum->name);
            }
        }
        $mform->addElement('select', 'config_forumid', get_string('forum_announcement', 'block_bloquecero'), $forums);
        $mform->setDefault('config_forumid', '');
        $mform->addHelpButton('config_forumid', 'forum_announcement', 'block_bloquecero');

        // Selector de foro para tutorías
        $mform->addElement('select', 'config_forumtutoriasid', get_string('forum_tutorias', 'block_bloquecero'), $forums);
        $mform->setDefault('config_forumtutoriasid', '');
        $mform->addHelpButton('config_forumtutoriasid', 'forum_tutorias', 'block_bloquecero');

        // Selector de foro para estudiantes
        $mform->addElement('select', 'config_forumestudiantesid', get_string('forum_estudiantes', 'block_bloquecero'), $forums);
        $mform->setDefault('config_forumestudiantesid', '');
        $mform->addHelpButton('config_forumestudiantesid', 'forum_estudiantes', 'block_bloquecero');

        // Obtener los profesores del curso
        $context = context_course::instance($COURSE->id);
        $teachers = get_role_users(3, $context); // 3 suele ser el rol de editingteacher, ajusta si usas otro rol

        // Construir lista de profesores para mostrar (opcional, si quieres mostrar la lista)
        $teacherlist = [];
        foreach ($teachers as $teacher) {
            $teacherlist[$teacher->id] = fullname($teacher) . ' (' . $teacher->email . ')';
        }

        // Checkboxes para seleccionar qué profesores mostrar
        $mform->addElement('header', 'selectedteachersheader', get_string('selectedteachers', 'block_bloquecero'));
        $mform->addHelpButton('selectedteachersheader', 'selectedteachers', 'block_bloquecero');
        foreach ($teachers as $teacher) {
            $fieldname = 'config_teacher_selected_' . $teacher->id;
            $mform->addElement('advcheckbox', $fieldname, '', fullname($teacher) . ' (' . $teacher->email . ')');
            // Por defecto todos marcados
            $mform->setDefault($fieldname, 1);
        }

        // Determinar si el usuario actual puede editar los datos de todos los profesores
        $ismanager = has_capability('moodle/role:assign', $context) || is_siteadmin();

        // Si es manager/admin: mostrar campos de todos los profesores
        // Si es profesor: mostrar solo sus propios campos
        $teacherstoedit = [];
        if ($ismanager) {
            $teacherstoedit = $teachers;
        } else if (array_key_exists($USER->id, $teachers)) {
            $teacherstoedit = [$USER->id => $teachers[$USER->id]];
        }

        if (!empty($teacherstoedit)) {
            if (!$ismanager || count($teacherstoedit) === 1) {
                // Profesor editando sus propios datos: un único header
                $mform->addElement('header', 'teachercustom', get_string('teachercustom', 'block_bloquecero'));
                $teacher = reset($teacherstoedit);
                $mform->addElement('text', 'config_userphone_' . $teacher->id, get_string('userphone', 'block_bloquecero'));
                $mform->setType('config_userphone_' . $teacher->id, PARAM_TEXT);
                $mform->setDefault('config_userphone_' . $teacher->id, '');
                $mform->addHelpButton('config_userphone_' . $teacher->id, 'userphone', 'block_bloquecero');

                $mform->addElement('editor', 'config_userschedule_' . $teacher->id, get_string('userschedule', 'block_bloquecero'));
                $mform->setType('config_userschedule_' . $teacher->id, PARAM_RAW);
                $mform->setDefault('config_userschedule_' . $teacher->id, '');
                $mform->addHelpButton('config_userschedule_' . $teacher->id, 'userschedule', 'block_bloquecero');
            } else {
                // Manager: un header colapsable por profesor, cerrados por defecto
                foreach ($teacherstoedit as $teacher) {
                    $headerid = 'teachercustom_' . $teacher->id;
                    $mform->addElement('header', $headerid, get_string('teachercustom', 'block_bloquecero') . ': ' . fullname($teacher));
                    $mform->setExpanded($headerid, false);

                    $mform->addElement('text', 'config_userphone_' . $teacher->id, get_string('userphone', 'block_bloquecero'));
                    $mform->setType('config_userphone_' . $teacher->id, PARAM_TEXT);
                    $mform->setDefault('config_userphone_' . $teacher->id, '');
                    $mform->addHelpButton('config_userphone_' . $teacher->id, 'userphone', 'block_bloquecero');

                    $mform->addElement('editor', 'config_userschedule_' . $teacher->id, get_string('userschedule', 'block_bloquecero'));
                    $mform->setType('config_userschedule_' . $teacher->id, PARAM_RAW);
                    $mform->setDefault('config_userschedule_' . $teacher->id, '');
                    $mform->addHelpButton('config_userschedule_' . $teacher->id, 'userschedule', 'block_bloquecero');
                }
            }
        }

        // --- Configuración de bibliografía ---
        $mform->addElement('header', 'bibliographyheader', get_string('bibliography', 'block_bloquecero'));

        // Get block instance ID if available
        $blockinstanceid = 0;
        if (!empty($this->block->instance)) {
            $blockinstanceid = $this->block->instance->id;
        }

        // Link to manage bibliography page
        if ($blockinstanceid) {
            $managebiburl = new moodle_url('/blocks/bloquecero/manage_bibliography.php', [
                'courseid' => $COURSE->id,
                'blockid' => $blockinstanceid,
            ]);
            $managebiblink = html_writer::link(
                $managebiburl,
                get_string('managebibliography', 'block_bloquecero'),
                ['target' => '_blank', 'class' => 'btn btn-secondary']
            );
            $mform->addElement('static', 'managebibliographylink', '', $managebiblink);
        } else {
            $mform->addElement(
                'static',
                'managebibliographyinfo',
                '',
                get_string('saveblockfirst', 'block_bloquecero')
            );
        }

        // --- Configuración de sesiones en directo ---
        $mform->addElement('header', 'livesessionsheader', get_string('livesessions', 'block_bloquecero'));

        // Get block instance ID if available
        $blockinstanceid = 0;
        if (!empty($this->block->instance)) {
            $blockinstanceid = $this->block->instance->id;
        }

        // Link to manage sessions page
        if ($blockinstanceid) {
            $manageurl = new moodle_url('/blocks/bloquecero/manage_sessions.php', [
                'courseid' => $COURSE->id,
                'blockid' => $blockinstanceid,
            ]);
            $managelink = html_writer::link(
                $manageurl,
                get_string('managesessions', 'block_bloquecero'),
                ['target' => '_blank', 'class' => 'btn btn-secondary']
            );
            $mform->addElement('static', 'managesessionslink', '', $managelink);
        } else {
            $mform->addElement(
                'static',
                'managesessionsinfo',
                '',
                get_string('saveblockfirst', 'block_bloquecero')
            );
        }

        // --- Programación de secciones (solo para formatos sin fechas automáticas, no 'weeks') ---
        if ($COURSE->format !== 'weeks') {
        $mform->addElement('header', 'sectionschedulingheader', get_string('sectionscheduling', 'block_bloquecero'));
        $mform->setExpanded('sectionschedulingheader', false);
        $mform->addElement('static', 'sectionschedulingdesc', '', get_string('sectionscheduling_desc', 'block_bloquecero'));

        $sections = $DB->get_records_select(
            'course_sections',
            'course = ? AND section > 0 AND (component IS NULL OR component <> ?)',
            [$COURSE->id, 'mod_subsection'],
            'section ASC'
        );

        foreach ($sections as $sec) {
            $secname = !empty($sec->name)
                ? format_string($sec->name)
                : get_string('section', 'moodle') . ' ' . $sec->section;

            $enablekey = 'config_section_enabled_' . $sec->id;
            $startkey  = 'config_section_start_'   . $sec->id;
            $endkey    = 'config_section_end_'     . $sec->id;

            $mform->addElement('advcheckbox', $enablekey, $secname, get_string('sectionscheduling_enable', 'block_bloquecero'));
            $mform->setDefault($enablekey, 0);

            $mform->addElement('date_selector', $startkey, get_string('sectionstart', 'block_bloquecero'));
            $mform->setDefault($startkey, time());
            $mform->disabledIf($startkey, $enablekey, 'eq', 0);

            $mform->addElement('date_selector', $endkey, get_string('sectionend', 'block_bloquecero'));
            $mform->setDefault($endkey, time());
            $mform->disabledIf($endkey, $enablekey, 'eq', 0);
        } // end foreach sections

        } // end if format !== 'weeks'

        // --- Modo septiembre ---
        $mform->addElement('header', 'septembergheader', get_string('septembernotice_header', 'block_bloquecero'));
        $mform->addElement('advcheckbox', 'config_show_september_notice', get_string('septembernotice_enable', 'block_bloquecero'));
        $mform->setDefault('config_show_september_notice', 0);
        $mform->addHelpButton('config_show_september_notice', 'septembernotice_enable', 'block_bloquecero');

        // Selector para el número máximo de actividades a mostrar en cada ficha de sección.
        $mform->addElement('header', 'blokconfig', get_string('blocksconfig', 'block_bloquecero'));
        $maxactivitiesoptions = [];
        for ($i = 1; $i <= 10; $i++) {
            $maxactivitiesoptions[$i] = (string)$i;
        }
        $mform->addElement('select', 'config_maxactivitiespersection', get_string('maxactivitiespersection', 'block_bloquecero'), $maxactivitiesoptions);
        $mform->setDefault('config_maxactivitiespersection', 4);
        $mform->addHelpButton('config_maxactivitiespersection', 'maxactivitiespersection', 'block_bloquecero');
    }

    /**
     * Set form data (load existing configuration)
     */
    public function set_data($defaults) {
        global $USER, $COURSE;

        if (!empty($this->block->config)) {
            $context = context_course::instance($COURSE->id);
            $teachers = get_role_users(3, $context);
            $ismanager = has_capability('moodle/role:assign', $context) || is_siteadmin();

            $teacherstoload = [];
            if ($ismanager) {
                $teacherstoload = $teachers;
            } else if (array_key_exists($USER->id, $teachers)) {
                $teacherstoload = [$USER->id => $teachers[$USER->id]];
            }

            foreach ($teacherstoload as $teacher) {
                $schedulekey = 'userschedule_' . $teacher->id;
                if (!empty($this->block->config->$schedulekey)) {
                    if (is_array($this->block->config->$schedulekey)) {
                        $defaults->{"config_" . $schedulekey} = $this->block->config->$schedulekey;
                    } else {
                        $defaults->{"config_" . $schedulekey} = [
                            'text' => $this->block->config->$schedulekey,
                            'format' => FORMAT_HTML,
                        ];
                    }
                }
            }
        }

        parent::set_data($defaults);
    }

    /**
     * Validates that section date ranges do not overlap.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        global $COURSE, $DB;

        $errors = parent::validation($data, $files);

        if ($COURSE->format === 'weeks') {
            return $errors;
        }

        $sections = $DB->get_records_select(
            'course_sections',
            'course = ? AND section > 0 AND (component IS NULL OR component <> ?)',
            [$COURSE->id, 'mod_subsection'],
            'section ASC',
            'id, section'
        );

        // Collect configured ranges.
        $ranges = [];
        foreach ($sections as $sec) {
            $enablekey = 'config_section_enabled_' . $sec->id;
            $startkey  = 'config_section_start_'   . $sec->id;
            $endkey    = 'config_section_end_'     . $sec->id;

            if (empty($data[$enablekey])) {
                continue;
            }

            $startval = isset($data[$startkey]) ? (int)$data[$startkey] : 0;
            $endval   = isset($data[$endkey])   ? (int)$data[$endkey]   : 0;

            if ($endval < $startval) {
                $errors[$endkey] = get_string('sectionend_before_start', 'block_bloquecero');
                continue;
            }

            $ranges[] = [
                'secid'    => $sec->id,
                'startkey' => $startkey,
                'endkey'   => $endkey,
                'start'    => $startval,
                'end'      => $endval + 86399,
            ];
        }

        // Check for overlaps between every pair.
        for ($i = 0; $i < count($ranges); $i++) {
            for ($j = $i + 1; $j < count($ranges); $j++) {
                $a = $ranges[$i];
                $b = $ranges[$j];
                if ($a['start'] <= $b['end'] && $b['start'] <= $a['end']) {
                    $errors[$b['startkey']] = get_string('sectiondates_overlap', 'block_bloquecero');
                }
            }
        }

        return $errors;
    }
}
