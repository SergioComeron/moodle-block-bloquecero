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
 * Block bloquecero — customized course header for Moodle courses.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/weeks/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');


/**
 * Returns the start date timestamp for a course module based on its type.
 *
 * @param cm_info $cm Course module info object.
 * @return int Unix timestamp of start date, or 0 if not applicable.
 */
function get_cm_start_date($cm) {
    global $DB;
    $time = 0;
    switch ($cm->modname) {
        case 'assign':
            // En asignaciones, se usa allowsubmissionsfromdate o duedate como fallback
            $assignment = $DB->get_record('assign', ['id' => $cm->instance], 'allowsubmissionsfromdate, duedate', MUST_EXIST);
            $time = $assignment->allowsubmissionsfromdate ? $assignment->allowsubmissionsfromdate : $assignment->duedate;
            break;
        case 'quiz':
            // En cuestionarios, se usa timeopen o timeclose como fallback
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'timeopen, timeclose', MUST_EXIST);
            $time = $quiz->timeopen ? $quiz->timeopen : $quiz->timeclose;
            break;
        case 'forum':
            // En foros, se usa assesstimestart (fecha de inicio del rango de calificación)
            $forum = $DB->get_record('forum', ['id' => $cm->instance], 'assesstimestart, assesstimefinish');
            $time = $forum ? $forum->assesstimestart : 0;
            break;
        // Agregar otros casos según el tipo de actividad
        default:
            // Si no se define fecha de inicio para ese tipo, se deja en 0 o se puede devolver NULL
            $time = 0;
            break;
    }
    return (int)$time;
}

/**
 * Block bloquecero class.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_bloquecero extends block_base {
    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_bloquecero');
    }

    /**
     * Ejecuta las acciones automáticas del Modo Septiembre al guardar la configuración.
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB, $CFG, $USER;

        $enabling = !empty($data->show_september_notice);
        $alreadydone = !empty($this->config->september_mode_setup_done);

        if ($enabling && !$alreadydone) {
            require_once($CFG->dirroot . '/mod/forum/lib.php');
            require_once($CFG->dirroot . '/course/modlib.php');

            // Obtener el curso.
            $courseid = $this->page->course->id;
            $course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

            // -------------------------------------------------------
            // 1. Enviar anuncio al Tablón de Anuncios
            // -------------------------------------------------------
            $forumid = isset($data->forumid) ? (int)$data->forumid : 0;
            if ($forumid) {
                $forum = $DB->get_record('forum', ['id' => $forumid, 'course' => $course->id]);
                if ($forum) {
                    $discussion                = new stdClass();
                    $discussion->course        = $course->id;
                    $discussion->forum         = $forum->id;
                    $discussion->name          = get_string('sept_announcement_subject', 'block_bloquecero');
                    $discussion->message       = get_string('sept_announcement_body', 'block_bloquecero');
                    $discussion->messageformat = FORMAT_HTML;
                    $discussion->messagetrust  = 0;
                    $discussion->mailnow       = 1;
                    $discussion->groupid       = 0;
                    $discussion->timestart     = 0;
                    $discussion->timeend       = 0;
                    forum_add_discussion($discussion);
                }
            }

            // -------------------------------------------------------
            // 2. Crear "Foro Convocatoria de Septiembre" en la misma
            // sección que los foros configurados en el bloque.
            // -------------------------------------------------------
            $septforumname = get_string('sept_forum_name', 'block_bloquecero');
            $existingforum = $DB->get_record('forum', ['course' => $course->id, 'name' => $septforumname]);
            if ($existingforum) {
                // Ya existe: asegurarse de que está visible.
                $septcm = get_coursemodule_from_instance('forum', $existingforum->id, $course->id);
                if ($septcm && !$septcm->visible) {
                    set_coursemodule_visible($septcm->id, 1);
                }
            }
            if (!$existingforum) {
                // Determinar la sección a partir de uno de los foros configurados.
                $targetsection = 0;
                $refforumids = array_filter([
                    isset($data->forumid) ? (int)$data->forumid : 0,
                    isset($data->forumtutoriasid) ? (int)$data->forumtutoriasid : 0,
                    isset($data->forumestudiantesid) ? (int)$data->forumestudiantesid : 0,
                ]);
                foreach ($refforumids as $refid) {
                    $refcm = get_coursemodule_from_instance('forum', $refid, $course->id);
                    if ($refcm) {
                        $targetsection = $DB->get_field(
                            'course_sections',
                            'section',
                            ['id' => $refcm->section]
                        );
                        break;
                    }
                }

                $moduleinfo                   = new stdClass();
                $moduleinfo->modulename       = 'forum';
                $moduleinfo->module           = $DB->get_field('modules', 'id', ['name' => 'forum']);
                $moduleinfo->name             = $septforumname;
                $moduleinfo->course           = $course->id;
                $moduleinfo->section          = $targetsection;
                $moduleinfo->visible          = 1;
                $moduleinfo->type             = 'general';
                $moduleinfo->intro            = '';
                $moduleinfo->introformat      = FORMAT_HTML;
                $moduleinfo->forcesubscribe   = 1;
                $moduleinfo->assessed         = 0;
                $moduleinfo->scale            = 0;
                $moduleinfo->assesstimestart  = 0;
                $moduleinfo->assesstimefinish = 0;
                add_moduleinfo($moduleinfo, $course);
            }

            // -------------------------------------------------------
            // 3. Bloquear permisos de estudiante en todos los foros
            // -------------------------------------------------------
            $studentrole = $DB->get_record('role', ['shortname' => 'student']);
            if ($studentrole) {
                rebuild_course_cache($course->id, true);
                $modinfo = get_fast_modinfo($course);
                foreach ($modinfo->get_instances_of('forum') as $forumcm) {
                    $fcontext = context_module::instance($forumcm->id);
                    role_change_permission($studentrole->id, $fcontext, 'mod/forum:replypost', CAP_PREVENT);
                    role_change_permission($studentrole->id, $fcontext, 'mod/forum:startdiscussion', CAP_PREVENT);
                }
            }

            // -------------------------------------------------------
            // 4. Ampliar plazos de actividades y controles al 31 de agosto
            // -------------------------------------------------------
            $aug31 = mktime(23, 59, 0, 8, 31, (int)date('Y'));

            $assigns = $DB->get_records('assign', ['course' => $course->id]);
            foreach ($assigns as $assign) {
                if (!empty($assign->duedate)) {
                    $DB->set_field('assign', 'duedate', $aug31, ['id' => $assign->id]);
                }
                if (!empty($assign->cutoffdate)) {
                    $DB->set_field('assign', 'cutoffdate', $aug31, ['id' => $assign->id]);
                }
            }

            $quizzes = $DB->get_records('quiz', ['course' => $course->id]);
            foreach ($quizzes as $quiz) {
                if (!empty($quiz->timeclose)) {
                    $DB->set_field('quiz', 'timeclose', $aug31, ['id' => $quiz->id]);
                }
            }

            // Marcar setup como completado para no repetirlo.
            $data->september_mode_setup_done = 1;
        }

        // -------------------------------------------------------
        // Desactivación del modo septiembre
        // -------------------------------------------------------
        $wasenabled = !empty($this->config->show_september_notice);
        $isdisabling = empty($data->show_september_notice);

        if ($wasenabled && $isdisabling) {
            require_once($CFG->dirroot . '/mod/forum/lib.php');

            $courseid = $this->page->course->id;
            $course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

            $studentrole = $DB->get_record('role', ['shortname' => 'student']);

            // Restaurar permisos de estudiante en todos los foros.
            if ($studentrole) {
                $modinfo = get_fast_modinfo($course);
                foreach ($modinfo->get_instances_of('forum') as $forumcm) {
                    $fcontext = context_module::instance($forumcm->id);
                    role_change_permission($studentrole->id, $fcontext, 'mod/forum:replypost', CAP_INHERIT);
                    role_change_permission($studentrole->id, $fcontext, 'mod/forum:startdiscussion', CAP_INHERIT);
                }
            }

            // Ocultar el foro de convocatoria de septiembre.
            $septforumname = get_string('sept_forum_name', 'block_bloquecero');
            $septforum = $DB->get_record('forum', ['course' => $course->id, 'name' => $septforumname]);
            if ($septforum) {
                $septcm = get_coursemodule_from_instance('forum', $septforum->id, $course->id);
                if ($septcm) {
                    set_coursemodule_visible($septcm->id, 0);
                }
            }

            $data->september_mode_setup_done = 0;
        }

        return parent::instance_config_save($data, $nolongerused);
    }

    /**
     * Return the block content.
     *
     * @return stdClass Block content object.
     */
    public function get_content() {
        // phpcs:disable moodle.Files.LineLength.MaxExceeded
        global $COURSE, $DB, $USER, $CFG, $OUTPUT;
        $section = optional_param('section', 0, PARAM_INT);
        if ($section > 0) {
            // Estamos en una sección, no mostrar el bloque
            return null;
        }
        // Oculta el bloque si no estamos en la página principal del curso (ni en weeks ni en topics)
        $pagetype = $this->page->pagetype;
        if ($pagetype !== 'course-view-weeks' && $pagetype !== 'course-view-topics' && $pagetype !== 'course-view') {
            return null;
        }

        // Verificación adicional: solo mostrar en course/view.php
        $scriptname = basename($_SERVER['SCRIPT_NAME']);
        if ($scriptname !== 'view.php' || strpos($_SERVER['SCRIPT_NAME'], '/course/') === false) {
            return null;
        }

        $isediting = $this->page->user_is_editing();

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // -------------------------------------------------------
        // Detección de metacurso: si este curso es hijo por metaenlace,
        // mostrar solo el header y un mensaje con enlace al curso padre.
        // -------------------------------------------------------
        // Detectar si este curso es fuente de un metaenlace.
        // Cursos de los que este curso recibe alumnos por metaenlace (este curso = destino).
        $metachildlinks = [];
        $metachildenrols = $DB->get_records('enrol', [
            'courseid' => $COURSE->id,
            'enrol'    => 'meta',
        ]);
        foreach ($metachildenrols as $enrol) {
            $sourcecourse = $DB->get_record('course', ['id' => $enrol->customint1]);
            if ($sourcecourse) {
                $metachildlinks[] = format_string($sourcecourse->fullname);
            }
        }

        $ismetacourse = false;
        $metacourselinks = [];
        if (!$isediting) {
            $metaenrols = $DB->get_records('enrol', [
                'enrol'      => 'meta',
                'customint1' => $COURSE->id,
            ]);
            if (!empty($metaenrols)) {
                $ismetacourse = true;
                foreach ($metaenrols as $enrol) {
                    $linkedcourse = $DB->get_record('course', ['id' => $enrol->courseid]);
                    if ($linkedcourse) {
                        $linkedurl = new moodle_url('/course/view.php', ['id' => $linkedcourse->id]);
                        $metacourselinks[] = '<a href="' . $linkedurl . '" class="bloquecero-meta-link">'
                            . format_string($linkedcourse->fullname) . '</a>';
                    }
                }
                // Ocultar el curso automáticamente si todavía está visible.
                if ($COURSE->visible) {
                    $coursedata = new stdClass();
                    $coursedata->id = $COURSE->id;
                    $coursedata->visible = 0;
                    update_course($coursedata);
                }
            }
        }

        $coursecontext = context_course::instance($COURSE->id);
        $canviewhidden = has_capability('moodle/course:update', $coursecontext);
        require_once($CFG->dirroot . '/user/lib.php');
        $teachersraw = get_enrolled_users($coursecontext, 'moodle/course:update');

        $fieldsapi = \core_user\fields::for_userpic();
        $requiredfields = $fieldsapi->get_required_fields();
        foreach ($teachersraw as $teacher) {
            foreach ($requiredfields as $field) {
                if (!isset($teacher->$field)) {
                    $teacher->$field = '';
                }
            }
        }

        global $OUTPUT;
        $teachersp = [];
        $teachersp = [];
        foreach ($teachersraw as $teacher) {
            $userpic = $OUTPUT->user_picture($teacher, ['size' => 80, 'class' => 'teacher-photo']);

            // Obtener teléfono y horario guardados en la configuración del bloque
            $phone = '';
            $schedule = '';

            // Buscar el teléfono guardado para este profesor
            $phonekey = 'userphone_' . $teacher->id;
            if (!empty($this->config->$phonekey)) {
                $phone = $this->config->$phonekey;
            }

            // Buscar el horario guardado para este profesor
            $schedulekey = 'userschedule_' . $teacher->id;
            if (!empty($this->config->$schedulekey)) {
                if (is_array($this->config->$schedulekey)) {
                    // Es un editor, tiene formato array con 'text' y 'format'
                    $schedule = $this->config->$schedulekey['text'];
                } else {
                    $schedule = $this->config->$schedulekey;
                }
            }

            $teachersp[] = (object)[
                'id'          => $teacher->id,
                'fullname'    => fullname($teacher),
                'email'       => $teacher->email,
                'phone'       => $phone,
                'schedule'    => $schedule,
                'picturehtml' => $userpic, // Cambiado de 'userpic' a 'picturehtml'
            ];
        }

        // Filtrar profesores según los checkboxes de selección
        $hasselection = false;
        if (!empty($this->config)) {
            foreach ($this->config as $key => $value) {
                if (strpos($key, 'teacher_selected_') === 0) {
                    $hasselection = true;
                    break;
                }
            }
        }
        if ($hasselection) {
            $teachersp = array_filter($teachersp, function ($t) {
                $key = 'teacher_selected_' . $t->id;
                return !empty($this->config->$key);
            });
            $teachersp = array_values($teachersp);
        }

        // URLs de los foros y demás secciones
        $forumanunciosurl = '';
        if (!empty($this->config->forumid)) {
            $idforumanuncios = $this->config->forumid;
            // Verificar que el foro existe antes de acceder
            if ($DB->record_exists('forum', ['id' => $idforumanuncios])) {
                $cmforumanuncios = get_coursemodule_from_instance('forum', $idforumanuncios);
                if ($cmforumanuncios) {
                    // Usar cm_info para verificar visibilidad respetando permisos del usuario
                    $cminfoanuncios = \cm_info::create($cmforumanuncios);
                    if ($cminfoanuncios->uservisible) {
                        $forumanunciosurl = new moodle_url('/mod/forum/view.php', ['id' => $cmforumanuncios->id]);
                        $countanuncios = forum_get_discussions_unread($cmforumanuncios);
                    }
                }
            }
        }

        $forumtutoriasurl = '';
        if (!empty($this->config->forumtutoriasid)) {
            $idforumtutorias = $this->config->forumtutoriasid;
            // Verificar que el foro existe antes de acceder
            if ($DB->record_exists('forum', ['id' => $idforumtutorias])) {
                $cmforumtutorias = get_coursemodule_from_instance('forum', $idforumtutorias);
                if ($cmforumtutorias) {
                    // Usar cm_info para verificar visibilidad respetando permisos del usuario
                    $cminfotutorias = \cm_info::create($cmforumtutorias);
                    if ($cminfotutorias->uservisible) {
                        $forumtutoriasurl = new moodle_url('/mod/forum/view.php', ['id' => $cmforumtutorias->id]);
                        $counttutorias = forum_get_discussions_unread($cmforumtutorias);
                    }
                }
            }
        }

        $forumestudiantesurl = '';
        if (!empty($this->config->forumestudiantesid)) {
            $idforumestudiantes = $this->config->forumestudiantesid;
            // Verificar que el foro existe antes de acceder
            if ($DB->record_exists('forum', ['id' => $idforumestudiantes])) {
                $cmforumestudiantes = get_coursemodule_from_instance('forum', $idforumestudiantes);
                if ($cmforumestudiantes) {
                    // Usar cm_info para verificar visibilidad respetando permisos del usuario
                    $cminfoestudiantes = \cm_info::create($cmforumestudiantes);
                    if ($cminfoestudiantes->uservisible) {
                        $forumestudiantesurl = new moodle_url('/mod/forum/view.php', ['id' => $cmforumestudiantes->id]);
                        $countestudiantes = forum_get_discussions_unread($cmforumestudiantes);
                    }
                }
            }
        }
        // Foro Convocatoria de Septiembre (solo si el modo septiembre está activo).
        $forumseptiembreurl = '';
        if (!empty($this->config->show_september_notice)) {
            $septforumname = get_string('sept_forum_name', 'block_bloquecero');
            $septforumrecord = $DB->get_record('forum', ['course' => $COURSE->id, 'name' => $septforumname]);
            if ($septforumrecord) {
                $cmforumseptiembre = get_coursemodule_from_instance('forum', $septforumrecord->id, $COURSE->id);
                if ($cmforumseptiembre) {
                    $cminfoseptiembre = \cm_info::create($cmforumseptiembre);
                    if ($cminfoseptiembre->uservisible) {
                        $forumseptiembreurl = new moodle_url('/mod/forum/view.php', ['id' => $cmforumseptiembre->id]);
                        $countseptiembre = forum_get_discussions_unread($cmforumseptiembre);
                    }
                }
            }
        }

        $guideurl = !empty($this->config->guide_url) ? $this->config->guide_url : '#';
        // $bibliography_url = !empty($this->config->bibliography_url) ? $this->config->bibliography_url : '#';

        $zoomurl = new moodle_url('/path/to/zoom');
        $tasksurl = new moodle_url('/path/to/tasks');

        $strshowcourse = get_string('showcourse', 'block_bloquecero');
        $strhidecourse = get_string('hidecourse', 'block_bloquecero');

        $togglebuttonhtml = '';
        if (!$isediting) {
            $togglebuttonhtml = '
                <div class="moodle-toggle-centering">
                    <button id="bloquecero-mostrarcurso-btn"
                        type="button"
                        onclick="event.preventDefault(); window.bloquecero_toggle()"
                        class="moodle-toggle-btn"
                        aria-expanded="false"
                        aria-label="' . get_string('togglecourse', 'block_bloquecero') . '"
                        title="' . get_string('togglecourse', 'block_bloquecero') . '">
                        <span class="moodle-toggle-circle">
                            <svg id="bloquecero-mostrarcurso-icon" class="moodle-toggle-chevron" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                                <polyline points="9 6 15 12 9 18" fill="none" stroke="#1655A0" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span id="bloquecero-mostrarcurso-text" class="moodle-toggle-label">' . $strshowcourse . '</span>
                    </button>
                    </div>';
        }

        if ($isediting) {
            $this->page->requires->js_init_code("
                document.addEventListener('DOMContentLoaded', function() {
                    var region = document.getElementById('region-main');
                    if (region) region.style.display = '';
                    // Los bloques laterales siempre visibles, no necesitan reset
                    [
                        '.page-header','.page-context-header','.course-header','.page-header-headings','.page-title','.course-title'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = ''; });
                    });
                    [
                        '.nav-tabs','.nav-tabs-line','.secondary-navigation','.secondary-nav'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = ''; });
                    });
                });
            ");
        }

        if ($ismetacourse) {
            $this->page->requires->js_init_code("
                document.addEventListener('DOMContentLoaded', function() {
                    var region = document.getElementById('region-main');
                    if (region) region.style.display = 'none';
                    [
                        '.page-header','.page-context-header','.course-header',
                        '.page-header-headings','.page-title','.course-title'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = 'none'; });
                    });
                    [
                        '.nav-tabs','.nav-tabs-line','.secondary-navigation','.secondary-nav'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = 'none'; });
                    });
                });
            ");
        }

        // URLs de las imágenes
        $context = context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_bloquecero', 'header_bg', 0, 'itemid, filepath, filename', false);

        // Si hay archivos, usa el primero como imagen de fondo.
        if (!empty($files)) {
            $file = reset($files);
            $fondocabeceraimg = moodle_url::make_pluginfile_url(
                $context->id,
                'block_bloquecero',
                'header_bg',
                0,
                $file->get_filepath(),
                $file->get_filename()
            );
        } else {
            $fondocabeceraimg = '';
        }
        // Generar botones de contacto para cada profesor (lista separada por comas).
        $teacherslist = [];
        $contactblockshtml = '';
        foreach ($teachersp as $teacher) {
            $uniqueid = 'contact-info-' . $teacher->id;
            $teacherslist[] = '
                <button class="bloquecero-teacher-btn" type="button"
                    onclick="toggleContactInfo(\'' . $uniqueid . '\')"
                    aria-expanded="false"
                    aria-controls="' . $uniqueid . '">
                    <span>' . format_string($teacher->fullname) . '</span><span class="bloquecero-teacher-infoicon" aria-hidden="true">i</span>
                </button>';

            // Bloque de información de contacto para este profesor (oculto por defecto).
            $contactblockshtml .= '
                <div id="' . $uniqueid . '" role="region"
                    aria-hidden="true"
                    aria-label="' . get_string('contactinfo', 'block_bloquecero', format_string($teacher->fullname)) . '"
                    style="
                    display: none;
                    margin: 20px 40px;
                    text-align: left;
                    font-size: 0.9em;
                    color: #333;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    padding: 15px;
                    background-color: #f9f9f9;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    opacity: 0;
                    transform: scaleY(0);
                    transform-origin: top;
                    transition: transform 0.3s ease, opacity 0.3s ease;
                ">
                    <div style="margin-bottom: 10px;">' . $teacher->picturehtml . '</div>
                    <p><strong>E-mail:</strong> ' . $teacher->email . '</p>
                    ' . (empty($this->config->show_september_notice) ? '
                    <p><strong>Teléfono:</strong> ' . $teacher->phone . '</p>
                    <p><strong>Horario:</strong></p>' . $teacher->schedule : '') . '
                </div>';
        }

        $contactbuttonshtml = implode(
            '<span aria-hidden="true" style="margin: 0 4px; color: #888;">·</span>',
            array_map(fn($btn) => '<span style="white-space:nowrap">' . $btn . '</span>', $teacherslist)
        );

        // Inicializar completion_info para mostrar estado de finalización
        $completion = new completion_info($COURSE);
        $completionenabled = $completion->is_enabled() && !empty($COURSE->showcompletionconditions);
        $currentuserid = $USER->id;

        // Inicializar array para almacenar las actividades de cada sección (clave: id de la sección)
        $sectionsactivitiesdata = [];

        // Auto-destacado de sección por fechas programadas por el profesor.
        // Solo aplica en formatos sin fechas automáticas de sección (no 'weeks').
        // Se ejecuta antes de get_fast_modinfo para que el marcador esté actualizado.
        if (!empty($this->config) && $COURSE->format !== 'weeks') {
            $now = time();
            $hasdates = false;
            $activesectionnumber = null;

            $dbsections = $DB->get_records_select(
                'course_sections',
                'course = ? AND section > 0 AND (component IS NULL OR component <> ?)',
                [$COURSE->id, 'mod_subsection'],
                'section ASC',
                'id, section'
            );

            foreach ($dbsections as $dbsec) {
                $enablekey = 'section_enabled_' . $dbsec->id;
                $startkey  = 'section_start_'   . $dbsec->id;
                $endkey    = 'section_end_'     . $dbsec->id;

                if (empty($this->config->$enablekey)) {
                    continue;
                }

                $hasdates = true;
                $startval = isset($this->config->$startkey) ? (int)$this->config->$startkey : 0;
                $endval   = isset($this->config->$endkey) ? (int)$this->config->$endkey : 0;

                if ($startval > 0 && $endval > 0 && $now >= $startval && $now < $endval + 86400) {
                    $activesectionnumber = (int)$dbsec->section;
                    break;
                }
            }

            if ($hasdates) {
                $targetmarker = ($activesectionnumber !== null) ? $activesectionnumber : 0;
                if ((int)$COURSE->marker !== $targetmarker) {
                    course_set_marker($COURSE->id, $targetmarker);
                }
            }
        }

        // Carrusel de tarjetas de secciones (sin mostrar las actividades inline)
        $sectionscarousel = '<div class="sections-carousel">';
        $modinfo = get_fast_modinfo($COURSE);

        // Obtener formato y sección destacada/actual
        $format = $modinfo->get_course()->format;
        $highlightedsection = ($format === 'topics') ? $COURSE->marker : null;
        $todaysection = null;
        if ($format === 'weeks') {
            $startdate = $modinfo->get_course()->startdate;
            $now = time();
            $weekduration = 7 * 24 * 60 * 60;
            $todaysection = floor(($now - $startdate) / $weekduration) + 1;
            if ($todaysection < 1) {
                $todaysection = 1;
            }
        }

        // Mapa de fechas programadas por sección (solo para formatos no semanales).
        $sectionschedulemap = [];
        if (!empty($this->config) && $format !== 'weeks') {
            foreach ($modinfo->get_section_info_all() as $sec) {
                if ($sec->section == 0) {
                    continue;
                }
                $enablekey = 'section_enabled_' . $sec->id;
                if (empty($this->config->$enablekey)) {
                    continue;
                }
                $startkey = 'section_start_' . $sec->id;
                $endkey   = 'section_end_'   . $sec->id;
                $startval = isset($this->config->$startkey) ? (int)$this->config->$startkey : 0;
                $endval   = isset($this->config->$endkey) ? (int)$this->config->$endkey : 0;
                if ($startval > 0 && $endval > 0) {
                    $sectionschedulemap[$sec->id] = [
                        'start' => $startval,
                        'end'   => $endval,
                    ];
                }
            }
        }

        // --- Datos para el cronograma Gantt ---
        $ganttsections = [];
        $ganttallsections = []; // Mapa sectionnum => datos, incluye secciones sin fechas.
        $ganttrangestart = 0;
        $ganttrangeend = 0;

        // Para weeks: pre-calcular la base en medianoche hora local (igual que las columnas).
        $ganttweeksbase = null;
        if ($format === 'weeks') {
            $tz = core_date::get_user_timezone_object();
            $ganttweeksbase = new DateTime('@' . $modinfo->get_course()->startdate);
            $ganttweeksbase->setTimezone($tz);
            $ganttweeksbase->setTime(0, 0, 0);
        }

        foreach ($modinfo->get_section_info_all() as $sec) {
            if ($sec->section == 0) {
                continue;
            }
            if (!empty($sec->component) && $sec->component === 'mod_subsection') {
                continue;
            }
            if (!$sec->uservisible && !$canviewhidden) {
                continue;
            }
            $secname = format_string($sec->name ?: get_string('section', 'moodle') . ' ' . $sec->section);
            $secstart = 0;
            $secend = 0;

            if ($format === 'weeks' && $ganttweeksbase !== null) {
                $dtstart = clone $ganttweeksbase;
                $dtstart->modify('+' . ($sec->section - 1) . ' weeks');
                $secstart = $dtstart->getTimestamp();
                $dtend = clone $dtstart;
                $dtend->modify('+1 week');
                $secend = $dtend->getTimestamp() - 1;
            } else if (!empty($this->config)) {
                $enablekey = 'section_enabled_' . $sec->id;
                if (!empty($this->config->$enablekey)) {
                    $startkey = 'section_start_' . $sec->id;
                    $endkey   = 'section_end_'   . $sec->id;
                    $secstart = isset($this->config->$startkey) ? (int)$this->config->$startkey : 0;
                    $secend   = isset($this->config->$endkey) ? (int)$this->config->$endkey : 0;
                }
            }

            // Registrar siempre la sección (con o sin fechas) para agrupar actividades.
            $ganttallsections[(int)$sec->section] = [
                'name'       => $secname,
                'start'      => $secstart,
                'end'        => $secend,
                'sectionnum' => (int)$sec->section,
            ];

            if ($secstart > 0 && $secend > 0) {
                $ganttsections[] = ['name' => $secname, 'start' => $secstart, 'end' => $secend, 'sectionnum' => (int)$sec->section];
                if ($ganttrangestart === 0 || $secstart < $ganttrangestart) {
                    $ganttrangestart = $secstart;
                }
                if ($secend > $ganttrangeend) {
                    $ganttrangeend = $secend;
                }
            }
        }

        // Las semanas se generan después de incorporar actividades y sesiones.
        $ganttweeks = [];
        $ganttweekends = []; // Último segundo de cada semana (inicio_semana_siguiente - 1).

        $sectioncards = [];
        $sectioncount = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) {
                continue;
            }
            if (!$section->uservisible) {
                continue;
            }
            if (!empty($section->component) && $section->component === 'mod_subsection') {
                continue;
            }
            $sectionurl = new moodle_url('/course/section.php', ['id' => $section->id]);
            $course = $modinfo->get_course();
            if ($course->format == 'weeks' && empty($section->name)) {
                $startdate = $course->startdate;
                $weekduration = 7 * 24 * 60 * 60;
                $sectionstart = $startdate + (($section->section - 1) * $weekduration);
                $sectionend = $sectionstart + $weekduration;
                if (!empty($course->enddate) && $sectionend > $course->enddate) {
                    $sectionend = $course->enddate;
                }
                $sectiontitle = userdate($sectionstart, '%d %b %Y') . ' - ' . userdate($sectionend - 1, '%d %b %Y');
            } else {
                $sectiontitle = format_string($section->name ?: get_string('section', 'moodle') . ' ' . $section->section);
            }

            // --- NUEVO BLOQUE: preview/expand actividades sección ---
            $maxactivities = !empty($this->config->maxactivitiespersection) && is_numeric($this->config->maxactivitiespersection)
                ? (int)$this->config->maxactivitiespersection
                : 3; // Valor por defecto si no está configurado
            $visibleactivities = 0;
            $totalactivities = 0;
            $allactivitiesarray = [];
            $sectioncompleted = 0;
            $sectiontotalwithcompletion = 0;
            // print_r($modinfo);
            if (!empty($modinfo->sections[$section->section])) {
                // Crear un mapa de secciones por ID usando get_section_info_all()
                $sectionmap = [];
                foreach ($modinfo->get_section_info_all() as $sectioninfo) {
                    $sectionmap[$sectioninfo->id] = $sectioninfo;
                }

                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];

                    if (!$cm->uservisible) {
                        continue; // Saltar módulos no visibles para el usuario
                    }
                    if ($cm->modname === 'label') {
                        continue; // Saltar actividades de texto y media
                    }

                    if ($cm->modname !== 'subsection') {
                        // Actividad normal (no subsección)
                        $icon = $OUTPUT->pix_icon('icon', $cm->modfullname, $cm->modname, ['class' => 'activityicon']);
                        $completionhtml = '';
                        if ($completionenabled && $completion->is_enabled($cm)) {
                            $completiondata = $completion->get_data($cm);
                            $sectiontotalwithcompletion++;
                            // Obtener condiciones de finalización
                            $cmdetails = \core_completion\cm_completion_details::get_instance($cm, $currentuserid);
                            $conditionparts = [];
                            if ($cmdetails->is_automatic()) {
                                foreach ($cmdetails->get_details() as $detail) {
                                    $conditionparts[] = $detail->description;
                                }
                            }
                            if ($completiondata->completionstate == COMPLETION_COMPLETE || $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                                $completionhtml = '<span class="bloquecero-completion-icon bloquecero-completion-done" aria-label="' . get_string('completed', 'block_bloquecero') . '">&#10003;</span>';
                                $sectioncompleted++;
                            } else if ($completiondata->completionstate == COMPLETION_COMPLETE_FAIL) {
                                $completionhtml = '<span class="bloquecero-completion-icon bloquecero-completion-fail" aria-label="' . get_string('failed', 'block_bloquecero') . '">&#10007;</span>';
                            } else {
                                $completionhtml = '<span class="bloquecero-completion-icon bloquecero-completion-pending" aria-label="' . get_string('pending', 'block_bloquecero') . '">&#9675;</span>';
                            }
                            if (!empty($conditionparts)) {
                                $completionhtml .= '<div class="bloquecero-completion-conditions">' . implode(' &middot; ', array_map('htmlspecialchars', $conditionparts)) . '</div>';
                            }
                        }
                        $cmhiddenhtml = (!$cm->visible && $canviewhidden)
                            ? ' <span class="bloquecero-activity-hidden">' . get_string('hiddenfromstudents', 'moodle') . '</span>'
                            : '';
                        $cmliclass = (!$cm->visible && $canviewhidden) ? ' class="bloquecero-item-hidden"' : '';
                        $allactivitiesarray[] = '<li' . $cmliclass . '>' . $icon . ' <a href="' . $cm->url . '">' . format_string($cm->name) . '</a>' . $cmhiddenhtml . $completionhtml . '</li>';
                        $visibleactivities++;
                        $totalactivities++;
                    } else {
                        // Subsección encontrada
                        if (!empty($cm->name)) {
                            // Añadir el nombre de la subsección con estilo
                            $allactivitiesarray[] = '<li class="bloquecero-subsection-name" style="font-weight:600; color:#004D35; margin:8px 0 4px 0;">' . format_string($cm->name) . '</li>';

                            // Obtener la sección vinculada a la subsección
                            $subsectionid = $cm->customdata['sectionid'] ?? null;
                            if ($subsectionid && isset($sectionmap[$subsectionid])) {
                                $subsection = $sectionmap[$subsectionid];

                                // Listar las actividades dentro de la subsección con sangría
                                if (!empty($modinfo->sections[$subsection->section])) {
                                    foreach ($modinfo->sections[$subsection->section] as $subcmid) {
                                        $subcm = $modinfo->cms[$subcmid];
                                        if (!$subcm->uservisible) {
                                            continue; // Saltar actividades no visibles
                                        }

                                        // Generar el icono y el enlace para la actividad de la subsección
                                        $subicon = $OUTPUT->pix_icon('icon', $subcm->modfullname, $subcm->modname, ['class' => 'activityicon']);
                                        $subcompletionhtml = '';
                                        if ($completionenabled && $completion->is_enabled($subcm)) {
                                            $subcompletiondata = $completion->get_data($subcm);
                                            $sectiontotalwithcompletion++;
                                            // Obtener condiciones de finalización
                                            $subcmdetails = \core_completion\cm_completion_details::get_instance($subcm, $currentuserid);
                                            $subconditionparts = [];
                                            if ($subcmdetails->is_automatic()) {
                                                foreach ($subcmdetails->get_details() as $detail) {
                                                    $subconditionparts[] = $detail->description;
                                                }
                                            }
                                            if ($subcompletiondata->completionstate == COMPLETION_COMPLETE || $subcompletiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                                                $subcompletionhtml = '<span class="bloquecero-completion-icon bloquecero-completion-done" aria-label="' . get_string('completed', 'block_bloquecero') . '">&#10003;</span>';
                                                $sectioncompleted++;
                                            } else if ($subcompletiondata->completionstate == COMPLETION_COMPLETE_FAIL) {
                                                $subcompletionhtml = '<span class="bloquecero-completion-icon bloquecero-completion-fail" aria-label="' . get_string('failed', 'block_bloquecero') . '">&#10007;</span>';
                                            } else {
                                                $subcompletionhtml = '<span class="bloquecero-completion-icon bloquecero-completion-pending" aria-label="' . get_string('pending', 'block_bloquecero') . '">&#9675;</span>';
                                            }
                                            if (!empty($subconditionparts)) {
                                                $subcompletionhtml .= '<div class="bloquecero-completion-conditions">' . implode(' &middot; ', array_map('htmlspecialchars', $subconditionparts)) . '</div>';
                                            }
                                        }
                                        // Añadir sangría con una clase CSS
                                        $allactivitiesarray[] = '<li class="bloquecero-subsection-activity" style="margin-left: 20px;">' . $subicon . ' <a href="' . $subcm->url . '">' . format_string($subcm->name) . '</a>' . $subcompletionhtml . '</li>';
                                        $visibleactivities++;
                                        $totalactivities++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // Generar previews y full lists
            $activitiespreview = array_slice($allactivitiesarray, 0, $maxactivities);
            $remaining = count($allactivitiesarray) - $maxactivities;
            if ($remaining > 0) {
                // $activitiespreview[] = '<li class="bloquecero-vermas">+' . $remaining . ' más</li>';
                    $activitiespreview[] = '<li class="bloquecero-vermas"><button type="button" class="bloquecero-vermas-btn" onclick="toggleSectionCard(this)" aria-expanded="false">+' . $remaining . ' ' . get_string('more', 'block_bloquecero') . '</button></li>';
                $allactivitiesarray[] = '<li class="bloquecero-vermas"><button type="button" class="bloquecero-vermas-btn" onclick="toggleSectionCard(this)" aria-expanded="true">' . get_string('showless', 'block_bloquecero') . '</button></li>';
            }
            $activitieslist = '<ul class="bloquecero-section-activities" data-preview="1" style="margin: 12px 0 0 0; padding-left: 0; list-style: none;">' . implode('', $activitiespreview) . '</ul>';
            $activitieslistfull = '<ul class="bloquecero-section-activities" data-full="1" style="margin: 12px 0 0 0; padding-left: 0; list-style: none; display:none;">' . implode('', $allactivitiesarray) . '</ul>';
            if (!$allactivitiesarray) {
                $activitieslist = '<div style="margin-top:12px; color:#595959; font-size:0.95em;" class="bloquecero-section-activities" data-preview="1">' . get_string('noactivities', 'block_bloquecero') . '</div>';
                $activitieslistfull = '<div style="margin-top:12px; color:#595959; font-size:0.95em; display:none;" class="bloquecero-section-activities" data-full="1">' . get_string('noactivities', 'block_bloquecero') . '</div>';
            }
            // print_r($allactivitiesarray);
            // Guardar el contenido HTML de las actividades para esta sección con un id único
            $sectionid = 'section-activities-' . $sectioncount;
            $sectionsactivitiesdata[$sectionid] = $activitieslistfull;
            // print_r($sectionsactivitiesdata);
            // Colores alternos por índice de tarjeta
            $tarjetacolores = [
                ['bg' => '#F2F5F3', 'linea' => '#B7C65C'], // gris verdoso claro
                ['bg' => '#F8FBED', 'linea' => '#B7C65C'], // verde clarito
                ['bg' => '#FAFAFA', 'linea' => '#B7C65C'], // blanco-gris
            ];
            $tarjetaidx = $sectioncount % count($tarjetacolores);
            $bgcolor = $tarjetacolores[$tarjetaidx]['bg'];
            $linecolor = $tarjetacolores[$tarjetaidx]['linea'];

            // Badge destacado si corresponde
            $badge = null;
            if ($format === 'weeks' && $section->section == $todaysection) {
                $badge = get_string('current', 'block_bloquecero');
            } else if ($format === 'topics' && $section->section == $highlightedsection) {
                $badge = get_string('highlighted', 'block_bloquecero');
            }

            // Construir la tarjeta como string (con badge si corresponde)
            $hiddenclass = (!$section->visible && $canviewhidden) ? ' bloquecero-item-hidden' : '';
            $cardhtml = '<div class="bloquecero-section-card' . $hiddenclass . '" style="background: ' . $bgcolor . '">';
            if ($hiddenclass) {
                $cardhtml .= '<span class="bloquecero-hidden-badge">' . get_string('hiddenfromstudents', 'moodle') . '</span>';
            }
            if ($badge) {
                $cardhtml .= '<span class="bloquecero-section-badge">' . $badge . '</span>';
            }
            $cardhtml .= '
                <div class="bloquecero-section-header-flex" style="display:flex;flex-direction:column;width:100%;gap:4px;margin-bottom:8px;">
                    <div style="min-width:0;overflow:hidden;">' . (function () use ($sectionurl, $sectiontitle, $section, $sectionschedulemap) {
                        $titleattr = htmlspecialchars(strip_tags($sectiontitle));
                        $tooltipattrs = '';
                if (isset($sectionschedulemap[$section->id])) {
                    $datestart = userdate($sectionschedulemap[$section->id]['start'], get_string('strftimedate', 'langconfig'));
                    $dateend   = userdate($sectionschedulemap[$section->id]['end'], get_string('strftimedate', 'langconfig'));
                    $tooltiptext = htmlspecialchars($datestart . ' – ' . $dateend);
                    $titleattr   = $tooltiptext;
                    $tooltipattrs = ' data-bs-toggle="tooltip" data-bs-placement="top"';
                }
                        return '<a href="' . $sectionurl . '" class="bloquecero-section-number" title="' . $titleattr . '"' . $tooltipattrs . '>' . $sectiontitle . '</a>';
            })() . '</div>
                </div>
                <div class="bloquecero-section-line" style="background: ' . $linecolor . '; margin-bottom:12px;"></div>
                <div class="bloquecero-section-content">
                    ' . $activitieslist . '
                    ' . $activitieslistfull . '
                </div>';
            // Barra de progreso de completion
            if ($completionenabled && $sectiontotalwithcompletion > 0) {
                $progresspct = round(($sectioncompleted / $sectiontotalwithcompletion) * 100);
                $cardhtml .= '
                <div class="bloquecero-progress-wrapper">
                    <div class="bloquecero-progress-bar" role="progressbar" aria-valuenow="' . $progresspct . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . get_string('completionprogress', 'block_bloquecero') . '">
                        <div class="bloquecero-progress-fill" style="width: ' . $progresspct . '%"></div>
                    </div>
                    <span class="bloquecero-progress-text">' . $sectioncompleted . '/' . $sectiontotalwithcompletion . ' completadas</span>
                </div>';
            }
            $cardhtml .= '
            </div>';

            // Añadir todas las tarjetas al mismo array, sin separar.
            $sectioncards[] = $cardhtml;
            $sectioncount++;
        }
        $sectionscarousel .= implode('', $sectioncards);
        $sectionscarousel .= '</div>';

        // Envolver el carrusel en un contenedor con botones laterales
        $carouselcontainer = '
            <div class="carousel-container" role="region" aria-label="' . get_string('coursesections', 'block_bloquecero') . '" aria-roledescription="carousel" style="position: relative; display: flex; align-items: center; margin-bottom: 20px; padding: 0 40px;">
                 <button class="carousel-btn carousel-btn-left" onclick="scrollCarousel(-1)" aria-label="' . get_string('previoussection', 'block_bloquecero') . '">&#10094;</button>
                 ' . $sectionscarousel . '
                 <button class="carousel-btn carousel-btn-right" onclick="scrollCarousel(1)" aria-label="' . get_string('nextsection', 'block_bloquecero') . '">&#10095;</button>
            </div>
        ';

        // Bloque vacío para mostrar las actividades de la sección seleccionada (oculto inicialmente)
        // $activitiesBlockHtml = '<div id="section-activities-container" style="margin: 20px 40px; text-align: left; font-size: 0.9em; color: #333; border: 1px solid #ddd; border-radius: 3px; padding: 15px; background-color: #f9f9f9; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); display: none;"></div>';

        // Inyectar la definición del array de actividades en JavaScript
        $sectionsactivitiesjson = json_encode($sectionsactivitiesdata);
        // print_r($sectionsactivitiesdata);

        $coursedates = '';
        if (!empty($COURSE->startdate)) {
            $coursedates = userdate($COURSE->startdate, '%d %b %Y');
            if (!empty($COURSE->enddate)) {
                $coursedates .= ' - ' . userdate($COURSE->enddate, '%d %b %Y');
            }
        }

        // Bloque para mostrar el Calendario de actividades (mismo ancho que el carrusel)
        $calendaractivities = '';
        $activitiesdata = []; // Array para pasar a JavaScript con información completa

        // Precargar actividades calificables del curso y notas del usuario (2 consultas)
        $gradedmodules = [];
        $graderows = $DB->get_records('grade_items', ['courseid' => $COURSE->id, 'itemtype' => 'mod'], '', 'id, itemmodule, iteminstance');
        foreach ($graderows as $row) {
            $gradedmodules[$row->itemmodule . '_' . $row->iteminstance] = $row->id;
        }
        $usergraded = [];
        if (!empty($gradedmodules)) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_values($gradedmodules));
            $inparams[] = $USER->id;
            $usergraderows = $DB->get_records_sql(
                "SELECT gi.itemmodule, gi.iteminstance
                   FROM {grade_items} gi
                   JOIN {grade_grades} gg ON gg.itemid = gi.id
                  WHERE gi.id $insql AND gg.userid = ? AND gg.finalgrade IS NOT NULL",
                $inparams
            );
            foreach ($usergraderows as $row) {
                $usergraded[$row->itemmodule . '_' . $row->iteminstance] = true;
            }
        }

        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $startdate = get_cm_start_date($cm);
            $isgraded = isset($gradedmodules[$cm->modname . '_' . $cm->instance]);
            if ($startdate || $isgraded) {
                $activitytime = $startdate ? userdate($startdate, '%d %b %Y') : null;
                $icon = $OUTPUT->pix_icon('icon', $cm->modfullname, $cm->modname, ['class' => 'activityicon']);

                // Determinar fecha de vencimiento y estado de entrega
                $duedate = 0;
                $submitted = false;
                $modname = $cm->modname;

                // Para tareas (assign)
                if ($modname === 'assign' && $cm->instance) {
                    $assignment = $DB->get_record('assign', ['id' => $cm->instance]);
                    if ($assignment) {
                        $duedate = $assignment->duedate;
                        // Verificar si hay entrega
                        $submission = $DB->get_record('assign_submission', [
                            'assignment' => $cm->instance,
                            'userid' => $USER->id,
                            'latest' => 1,
                        ]);
                        $submitted = $submission && $submission->status === 'submitted';
                    }
                } else if ($modname === 'quiz' && $cm->instance) {
                    // Para cuestionarios (quiz)
                    $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
                    if ($quiz) {
                        $duedate = $quiz->timeclose;
                        // Verificar si tiene intentos
                        $attempts = $DB->count_records('quiz_attempts', [
                            'quiz' => $cm->instance,
                            'userid' => $USER->id,
                        ]);
                        $submitted = $attempts > 0;
                    }
                } else if ($modname === 'forum' && $cm->instance) {
                    // Para foros (forum)
                    $forum = $DB->get_record('forum', ['id' => $cm->instance], 'assesstimestart, assesstimefinish');
                    if ($forum) {
                        $duedate = $forum->assesstimefinish;
                        $postcount = $DB->count_records_sql(
                            "SELECT COUNT(*) FROM {forum_posts} fp
                               JOIN {forum_discussions} fd ON fd.id = fp.discussion
                              WHERE fd.forum = ? AND fp.userid = ?",
                            [$cm->instance, $USER->id]
                        );
                        $submitted = $postcount > 0;
                    }
                }

                // Si no hay duedate, usar startdate
                if (!$duedate) {
                    $duedate = $startdate;
                }

                // Construir objeto de actividad para JavaScript
                $activitiesdata[] = [
                    'name' => format_string($cm->name),
                    'url' => $cm->url->out(),
                    'icon' => $icon,
                    'modname' => $modname,
                    'modfullname' => format_string($cm->modfullname),
                    'startdate' => (int)$startdate,
                    'duedate' => (int)$duedate,
                    'submitted' => $submitted,
                    'hidden' => (!$cm->visible && $canviewhidden),
                    'sectionnum' => (int)$cm->sectionnum,
                ];

                $duedatehtml = '';
                if ($duedate && $duedate !== $startdate) {
                    $duedatehtml = ' · Fin: ' . userdate($duedate, '%d %b %Y');
                }
                $datetext = $activitytime ? 'Inicio: ' . $activitytime . $duedatehtml : '';
                $secondline = '<div style="display:flex;justify-content:space-between;align-items:center;padding-left:22px;margin-top:2px;">'
                    . '<span style="font-size:0.78em;color:#999;">' . $datetext . '</span>'
                    . '<span class="bloquecero-act-status"></span>'
                    . '</div>';
                $hasusergrade = isset($usergraded[$cm->modname . '_' . $cm->instance]) ? '1' : '0';
                $hassubmitted = $submitted ? '1' : '0';
                $calhidden = (!$cm->visible && $canviewhidden);
                $calliclass = $calhidden ? ' class="bloquecero-item-hidden"' : '';
                $calhiddenhtml = $calhidden ? ' <span class="bloquecero-activity-hidden">' . get_string('hiddenfromstudents', 'moodle') . '</span>' : '';
                $calendaractivities .= '<li data-timestamp="' . (int)$startdate . '" data-duedate="' . (int)$duedate . '" data-graded="' . $hasusergrade . '" data-submitted="' . $hassubmitted . '"' . $calliclass . ' style="margin-bottom: 8px;">'
                    . '<div>' . $icon . ' <a href="' . $cm->url . '">' . format_string($cm->name) . '</a>' . $calhiddenhtml . '</div>'
                    . $secondline
                    . '</li>';
            }
        }

        if ($calendaractivities) {
            $calendaractivities = '<ul id="activities-list" style="margin: 12px 0 0 0; padding-left: 18px; list-style: none;">'
                . $calendaractivities . '</ul>';
        } else {
            $calendaractivities = '<div style="margin-top:12px; color:#595959; font-size:0.95em;">' .
                get_string('noactivities', 'block_bloquecero') . '</div>';
        }

        // --- Actividades para el Gantt ---
        // Mapa subsección → sección padre (para actividades dentro de mod_subsection).
        $subsectionsectionmap = [];
        $subsectionrecs = $DB->get_records('course_sections',
            ['course' => $COURSE->id, 'component' => 'mod_subsection'], '', 'section, itemid');
        if (!empty($subsectionrecs)) {
            $cmids = array_column((array)$subsectionrecs, 'itemid');
            list($insql, $inparams) = $DB->get_in_or_equal($cmids);
            $inparams[] = $COURSE->id;
            $parentcms = $DB->get_records_sql(
                "SELECT cm.id, cs.section as parentsecnum
                   FROM {course_modules} cm
                   JOIN {course_sections} cs ON cs.id = cm.section
                  WHERE cm.id $insql AND cm.course = ?", $inparams);
            foreach ($subsectionrecs as $subsec) {
                if (!empty($parentcms[$subsec->itemid])) {
                    $subsectionsectionmap[(int)$subsec->section] = (int)$parentcms[$subsec->itemid]->parentsecnum;
                }
            }
        }

        $ganttactivities = [];
        foreach ($activitiesdata as $act) {
            $actstart = (int)$act['startdate'];
            $actend   = (int)$act['duedate'];
            if (!$actstart && !$actend) {
                continue;
            }
            if (!$actstart) {
                $actstart = $actend;
            }
            if (!$actend) {
                $actend = $actstart;
            }
            if ($ganttrangestart === 0 || $actstart < $ganttrangestart) {
                $ganttrangestart = $actstart;
            }
            if ($actend > $ganttrangeend) {
                $ganttrangeend = $actend;
            }
            $sectionnum = (int)$act['sectionnum'];
            // Si la actividad está en una subsección, usar la sección padre.
            if (isset($subsectionsectionmap[$sectionnum])) {
                $sectionnum = $subsectionsectionmap[$sectionnum];
            }
            $ganttactivities[] = [
                'name'       => $act['name'],
                'icon'       => $act['icon'],
                'start'      => $actstart,
                'end'        => $actend,
                'hidden'     => $act['hidden'],
                'sectionnum' => $sectionnum,
            ];
        }

        // Generar columnas semanales con el rango final (secciones + actividades).
        // --- SESIONES EN DIRECTO ---
        // Preparar sesiones en directo desde la base de datos (PRIMERO, antes de calcular semanas)
        $sesioneszoom = [];
        $blockinstanceid = $this->instance->id ?? 0;

        if ($blockinstanceid) {
            $sessions = $DB->get_records(
                'block_bloquecero_sessions',
                ['blockinstanceid' => $blockinstanceid, 'courseid' => $COURSE->id],
                'sessiondate ASC'
            );

            foreach ($sessions as $session) {
                $calendarurl = '';
                if (!empty($session->calendarid)) {
                    $calendarurl = (new moodle_url('/calendar/view.php', [
                        'view' => 'day',
                        'time' => $session->sessiondate,
                    ]))->out(false);
                }
                $sesioneszoom[] = [
                    'titulo' => $session->name,
                    'fecha' => $session->sessiondate,
                    'duracion' => (int)($session->duration ?? 0),
                    'descripcion' => !empty($session->description) ? format_text($session->description, FORMAT_HTML) : '',
                    'calendarurl' => $calendarurl,
                ];
            }
        }

        // Incorporar sesiones al rango del Gantt y generar columnas semanales.
        foreach ($sesioneszoom as $ses) {
            $sesdate = (int)$ses['fecha'];
            if ($sesdate > 0) {
                if ($ganttrangestart === 0 || $sesdate < $ganttrangestart) {
                    $ganttrangestart = $sesdate;
                }
                if ($sesdate > $ganttrangeend) {
                    $ganttrangeend = $sesdate;
                }
            }
        }
        if ($ganttrangestart > 0 && $ganttrangeend > 0) {
            // Usar la zona horaria del usuario para calcular el lunes correcto.
            $tz = core_date::get_user_timezone_object();
            $dt = new DateTime('@' . $ganttrangestart);
            $dt->setTimezone($tz);
            $dt->setTime(0, 0, 0);
            $dow = (int)$dt->format('N'); // 1=lunes … 7=domingo
            if ($dow > 1) {
                $dt->modify('-' . ($dow - 1) . ' days');
            }
            // Avanzar semana a semana con modify('+1 week') para respetar cambios de hora (DST).
            while ($dt->getTimestamp() <= $ganttrangeend && count($ganttweeks) < 60) {
                $ganttweeks[] = $dt->getTimestamp();
                $dt->modify('+1 week');
                $ganttweekends[] = $dt->getTimestamp() - 1;
            }
        }

        // --- Calcular semanas para la tarjeta de ACTIVIDADES ---
        $activitydates = [];
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $startdate = get_cm_start_date($cm);
            if ($startdate) {
                $activitydates[] = $startdate;
            }
        }
        if (!empty($activitydates)) {
            $minactdate = min($activitydates);
            $maxactdate = max($activitydates);
            $activitiesstart = strtotime('last monday', $minactdate + 86400);
            if ($activitiesstart > $minactdate) {
                $activitiesstart = strtotime('last monday', $minactdate);
            }
            $activitiesend = strtotime('next sunday', $maxactdate);
        } else {
            $activitiesstart = $COURSE->startdate ?: time();
            $activitiesend = !empty($COURSE->enddate) ? $COURSE->enddate : time();
        }
        $activitiesweeks = max(1, (int)ceil(($activitiesend - $activitiesstart) / (7 * 24 * 60 * 60)));

        // --- Calcular semanas para la tarjeta de SESIONES ---
        if (!empty($sesioneszoom)) {
            $allsesiondates = array_column($sesioneszoom, 'fecha');
            $minsesdate = min($allsesiondates);
            $maxsesdate = max($allsesiondates);
            $sessionsstart = strtotime('last monday', $minsesdate + 86400);
            if ($sessionsstart > $minsesdate) {
                $sessionsstart = strtotime('last monday', $minsesdate);
            }
            $sessionsend = strtotime('next sunday', $maxsesdate);
            $sessionsweeks = max(1, (int)ceil(($sessionsend - $sessionsstart) / (7 * 24 * 60 * 60)));
        } else {
            $sessionsstart = $COURSE->startdate ?: time();
            $sessionsweeks = 1;
        }

        $sesioneszoomlist = '';
        if (!empty($sesioneszoom)) {
            foreach ($sesioneszoom as $sesion) {
                $fecha = userdate($sesion['fecha'], get_string('strftimedaydatetime', 'langconfig'));
                $titulo = format_string($sesion['titulo']);
                if (!empty($sesion['calendarurl'])) {
                    $titulo = '<a href="' . $sesion['calendarurl'] . '" style="color:#004D35;text-decoration:none;">' . $titulo . '</a>';
                }
                $sesioneszoomlist .= '<li data-timestamp="' . $sesion['fecha'] . '" style="margin-bottom: 6px;">' .
                    $OUTPUT->pix_icon('i/calendar', '', '', ['class' => 'activityicon']) .
                    ' <strong>' . $titulo . '</strong> <span style="font-size:0.93em; color:#555;">(' . $fecha . ')</span></li>';
            }
            $sesioneszoomlist = '<ul id="sesiones-list" style="margin: 12px 0 0 0; padding-left: 18px; list-style: none;">' . $sesioneszoomlist . '</ul>';
        } else {
            $sesioneszoomlist = '<div style="margin: 12px 0; padding: 12px; text-align: center; color: #555; font-size: 0.9em;">' .
                get_string('nosessionsscheduled', 'block_bloquecero') . '</div>';
        }

        // --- NUEVA estructura de la tarjeta de calendario de actividades ---
        $calendarioactividades = '
            <div class="udima-maincard calendario-actividades-maincard">
                <div class="calendario-actividades-header">
                    <div class="bloquecero-card-title-row">
                        <h3>' . get_string('activities', 'block_bloquecero') . '</h3>
                        <button type="button" class="calendario-actividades-calendaricon" aria-label="' . get_string('viewallactivities', 'block_bloquecero') . '">
                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex-shrink:0;"><rect x="3" y="5" width="18" height="16" rx="3" fill="currentColor" /><rect x="7" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="7" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/></svg>
                            <span>' . get_string('viewall', 'block_bloquecero') . '</span>
                        </button>
                    </div>
                    <div class="bloquecero-card-line"></div>
                    <div class="week-selector">
                        <button id="prev-week" aria-label="' . get_string('previousweek', 'block_bloquecero') . '">&#10094;</button>
                        <span id="week-label" aria-live="polite"></span>
                        <button id="next-week" aria-label="' . get_string('nextweek', 'block_bloquecero') . '">&#10095;</button>
                    </div>
                </div>

                <div class="calendario-actividades-container">
                    <div id="activities-week-content"></div>
                </div>
            </div>
            <script>
            document.addEventListener("DOMContentLoaded", function(){
                const courseStart = ' . $activitiesstart . ';
                const totalWeeks = ' . $activitiesweeks . ';
                const now = Math.floor(Date.now() / 1000);
                let currentWeek = Math.floor((now - courseStart) / (7 * 24 * 60 * 60)) + 1;
                if (currentWeek < 1) {
                    currentWeek = 1;
                } else if (currentWeek > totalWeeks) {
                    currentWeek = totalWeeks;
                }
                // El listado original de actividades
                const originalListHTML = ' . json_encode($calendaractivities) . ';
                const contentContainer = document.getElementById("activities-week-content");
                const weekLabel = document.getElementById("week-label");
                const prevBtn = document.getElementById("prev-week");
                const nextBtn = document.getElementById("next-week");
                function formatDate(ts) {
                    const d = new Date(ts * 1000);
                    const day = d.getDate();
                    const monthNames = ' . json_encode(explode(',', get_string('monthnames', 'block_bloquecero'))) . ';
                    return day + " " + monthNames[d.getMonth()];
                }
                function filterActivities(week) {
                    const weekStart = courseStart + (week - 1) * 7 * 24 * 60 * 60;
                    const weekEnd = weekStart + 7 * 24 * 60 * 60;
                    // Parseamos el HTML original
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(originalListHTML, "text/html");
                    const ul = doc.querySelector("ul");
                    if (ul) {
                        let weeklyNodes = [];
                        let nodateItems = \'\';
                        ul.querySelectorAll("li").forEach(function(li){
                            const ts = parseInt(li.getAttribute("data-timestamp"), 10);
                            if (ts === 0) {
                                const graded = li.getAttribute("data-graded") === "1";
                                if (graded) {
                                    const clone = li.cloneNode(true);
                                    clone.style.opacity = "0.55";
                                    const check = document.createElement("span");
                                    check.style.cssText = "margin-left:6px;color:#5cb85c;font-weight:600;font-size:0.85em;";
                                    check.textContent = "✓ " + bloqueceroI18n.graded;
                                    clone.appendChild(check);
                                    nodateItems += clone.outerHTML;
                                } else {
                                    nodateItems += li.outerHTML;
                                }
                            } else if (!isNaN(ts)) {
                                const dd = parseInt(li.getAttribute("data-duedate"), 10) || 0;
                                const inStartWeek = ts >= weekStart && ts < weekEnd;
                                const stillOpen = dd > 0 && ts < weekEnd && dd >= weekStart;
                                if (inStartWeek || stillOpen) {
                                    const isSubmitted = li.getAttribute("data-submitted") === "1";
                                    const isGraded = li.getAttribute("data-graded") === "1";
                                    const clone = li.cloneNode(true);
                                    const statusEl = clone.querySelector(".bloquecero-act-status");
                                    if (statusEl) {
                                        if (isGraded) {
                                            statusEl.style.cssText = "color:#5cb85c;font-weight:600;font-size:0.78em;white-space:nowrap;";
                                            statusEl.textContent = "✓ " + bloqueceroI18n.graded;
                                        } else if (isSubmitted) {
                                            statusEl.style.cssText = "color:#5bc0de;font-weight:600;font-size:0.78em;white-space:nowrap;";
                                            statusEl.textContent = "✓ " + bloqueceroI18n.submitted;
                                        }
                                    }
                                    weeklyNodes.push({ts: ts, dd: dd, html: clone.outerHTML});
                                }
                            }
                        });
                        weeklyNodes.sort(function(a, b) {
                            if (a.ts !== b.ts) return a.ts - b.ts;
                            const ddA = a.dd || Infinity;
                            const ddB = b.dd || Infinity;
                            return ddA - ddB;
                        });
                        const weeklyItems = weeklyNodes.map(function(n){ return n.html; }).join(\'\');
                        let html = \'\';
                        if (weeklyItems) {
                            html += \'<ul style="margin:12px 0 0 0;padding-left:0;list-style:none;">\' + weeklyItems + \'</ul>\';
                        } else {
                            html += \'<div style="margin-top:12px;color:#595959;font-size:0.95em;text-align:center;">No hay actividades para esta semana.</div>\';
                        }
                        if (nodateItems) {
                            html += \'<hr style="border:none;border-top:1px solid #ddd;margin:12px 0 8px 0;">\';
                            html += \'<ul style="margin:0;padding-left:0;list-style:none;">\' + nodateItems + \'</ul>\';
                        }
                        contentContainer.innerHTML = html;
                    } else {
                        // Si no hay actividades en absoluto
                        contentContainer.innerHTML = doc.body.innerHTML;
                    }
                    const startStr = formatDate(weekStart);
                    const endStr = formatDate(weekEnd - 1);
                    weekLabel.innerHTML = startStr + "<br>" + endStr;
                }
                prevBtn.addEventListener("click", function(){
                    if(currentWeek > 1) {
                        currentWeek--;
                        filterActivities(currentWeek);
                    }
                });
                nextBtn.addEventListener("click", function(){
                    if(currentWeek < totalWeeks) {
                        currentWeek++;
                        filterActivities(currentWeek);
                    }
                });
                filterActivities(currentWeek);
            });
            </script>
            ';

        // --- SESIONES EN DIRECTO: genera el bloque con selector de semana ---
        // Calcular semanas para las sesiones (solo si hay sesiones)
        $sesionesdirecto = '
        <div class="udima-maincard sesiones-directo-maincard">
            <div class="sesiones-directo-header">
                <div class="bloquecero-card-title-row">
                    <h3>' . get_string('livesessions', 'block_bloquecero') . '</h3>
                    <button type="button" class="sesiones-directo-calendaricon" aria-label="' . get_string('viewallsessions', 'block_bloquecero') . '">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex-shrink:0;"><rect x="3" y="5" width="18" height="16" rx="3" fill="currentColor" /><rect x="7" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="7" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/></svg>
                        <span>' . get_string('viewall', 'block_bloquecero') . '</span>
                    </button>
                </div>
                <div class="bloquecero-card-line"></div>
                <div class="sesiones-directo-selector">
                    <button id="prev-sesion" aria-label="' . get_string('previoussession', 'block_bloquecero') . '">&#10094;</button>
                    <span id="sesion-label" aria-live="polite"></span>
                    <button id="next-sesion" aria-label="' . get_string('nextsession', 'block_bloquecero') . '">&#10095;</button>
                </div>
            </div>
            <div class="sesiones-directo-container">
                <div id="sesiones-list-content"></div>
            </div>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function(){
            const courseStart = ' . $sessionsstart . ';
            const totalWeeks = ' . $sessionsweeks . ';
            const now = Math.floor(Date.now() / 1000);
            let currentWeek = Math.floor((now - courseStart) / (7 * 24 * 60 * 60)) + 1;
            if (currentWeek < 1) {
                currentWeek = 1;
            } else if (currentWeek > totalWeeks) {
                currentWeek = totalWeeks;
            }
            // El listado original de sesiones
            const originalSesionesHTML = ' . json_encode($sesioneszoomlist) . ';
            const sesionesContainer = document.getElementById("sesiones-list-content");
            const sesionLabel = document.getElementById("sesion-label");
            const prevBtn = document.getElementById("prev-sesion");
            const nextBtn = document.getElementById("next-sesion");
            function formatDate(ts) {
                const d = new Date(ts * 1000);
                const day = d.getDate();
                const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                return day + " " + monthNames[d.getMonth()];
            }
            function filterSesiones(week) {
                const weekStart = courseStart + (week - 1) * 7 * 24 * 60 * 60;
                const weekEnd = weekStart + 7 * 24 * 60 * 60;
                // Parsear el HTML original
                const parser = new DOMParser();
                const doc = parser.parseFromString(originalSesionesHTML, "text/html");
                const ul = doc.querySelector("ul");
                let anyVisible = false;
                if (ul) {
                    ul.querySelectorAll("li").forEach(function(li){
                        const ts = parseInt(li.getAttribute("data-timestamp"), 10);
                        if (!isNaN(ts) && ts >= weekStart && ts < weekEnd) {
                            li.style.display = "list-item";
                            anyVisible = true;
                        } else {
                            li.style.display = "none";
                        }
                    });
                    if(anyVisible) {
                        sesionesContainer.innerHTML = "<ul>" + ul.innerHTML + "</ul>";
                    } else {
                        sesionesContainer.innerHTML = \'<div style="margin-top:12px; color:#595959; font-size:0.95em; text-align:center;">No hay sesiones para esta semana.</div>\';
                    }
                } else {
                    sesionesContainer.innerHTML = doc.body.innerHTML;
                }
                const startStr = formatDate(weekStart);
                const endStr = formatDate(weekEnd - 1);
                sesionLabel.innerHTML = startStr + "<br>" + endStr;
            }
            prevBtn.addEventListener("click", function(){
                if(currentWeek > 1) {
                    currentWeek--;
                    filterSesiones(currentWeek);
                }
            });
            nextBtn.addEventListener("click", function(){
                if(currentWeek < totalWeeks) {
                    currentWeek++;
                    filterSesiones(currentWeek);
                }
            });
            filterSesiones(currentWeek);
        });
        </script>
        ';

        // HTML principal del bloque (se añade debajo del carrusel el bloque para las actividades)

        $this->content->text =
            '<link href="https://fonts.googleapis.com/css?family=Inter:700,600,400&display=swap" rel="stylesheet">
            <style>
            .week-selector,
            .sesiones-directo-selector {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 0.93em;
                font-weight: 500;
                flex-shrink: 1;
                min-width: 40px;
                max-width: 180px;
            }
            /* Asegurar que el encabezado del bloque se alinee a la izquierda */
            .block_bloquecero .header {
                text-align: left !important;
            }
            </style>
            ';

        $metahide = $ismetacourse ? ' style="display:none"' : '';
        $this->content->text .= '
            <nav class="udima-menu-bar" aria-label="' . get_string('coursemenu', 'block_bloquecero') . '"' . $metahide . '>
            <a href="' . new moodle_url('/grade/report/grader/index.php', ['id' => $COURSE->id]) . '" class="udima-menu-link">
                ' . $OUTPUT->pix_icon('t/grades', '', 'moodle', ['class' => 'menu-icon']) . '
                <span>' . get_string('grades', 'block_bloquecero') . '</span>
            </a>
            <a href="' . new moodle_url('/user/index.php', ['id' => $COURSE->id]) . '" class="udima-menu-link">
                ' . $OUTPUT->pix_icon('i/users', '', 'moodle', ['class' => 'menu-icon']) . '
                <span>' . get_string('participants', 'block_bloquecero') . '</span>
            </a>
            <a href="#" id="bloquecero-bibliografia-btn" class="udima-menu-link">
                ' . $OUTPUT->pix_icon('book', '', 'moodle', ['class' => 'menu-icon']) . '
                <span>' . get_string('bibliography', 'block_bloquecero') . '</span>
            </a>' . (!empty($ganttweeks) ? '
            <a href="#" id="bloquecero-gantt-btn" class="udima-menu-link">
                ' . $OUTPUT->pix_icon('i/scheduled', '', 'moodle', ['class' => 'menu-icon']) . '
                <span>' . get_string('gantt', 'block_bloquecero') . '</span>
            </a>' : '') . '
            <a href="' . $guideurl . '" class="udima-menu-link" target="_blank">
                ' . $OUTPUT->pix_icon('i/info', '', 'moodle', ['class' => 'menu-icon']) . '
                <span>' . get_string('teacherguide', 'block_bloquecero') . '</span>
            </a>' . ((has_capability('moodle/course:update', $coursecontext)) ? '
            <a href="' . (new moodle_url('/course/edit.php', ['id' => $COURSE->id])) . '" class="udima-menu-link">
                ' . $OUTPUT->pix_icon('i/settings', '', 'moodle', ['class' => 'menu-icon']) . '
                <span>' . get_string('settings', 'block_bloquecero') . '</span>
            </a>' : '') .
            '</nav>
            <div class="bloquecero-main-wrapper" style="padding: 0 20px; font-family: Arial, sans-serif;">
            <!-- Resto del contenido del bloque -->
            <div class="bloquecero-header-responsive">
                ' . ($fondocabeceraimg ? '<img src="' . $fondocabeceraimg . '" alt="" role="presentation" class="bloquecero-header-bg-img">' : '') . '
                <div class="bloquecero-header-content">
                    <h2 class="bloquecero-header-title">' . format_string(trim(explode(' - ', $COURSE->fullname, 2)[0])) . '</h2>
                </div>
            </div>
            ' . ($ismetacourse ? '
            <!-- Aviso de metacurso -->
            <div class="bloquecero-meta-notice" role="alert">
                <strong>' . get_string('metacourse_notice_title', 'block_bloquecero') . '</strong>
                <p>' . get_string('metacourse_notice', 'block_bloquecero') . '</p>
                <p>' . implode('<br>', $metacourselinks) . '</p>
            </div>' : '') . '
            <!-- Fechas y equipo docente fuera del header para evitar recorte -->
            <div class="bloquecero-info-row"' . $metahide . '>
                ' . (!empty($metachildlinks) ? '<p class="bloquecero-metachild-notice">' . get_string('metachild_notice', 'block_bloquecero') . '<br>' . implode('<br>', $metachildlinks) . '</p>' : '') . '
                ' . ($coursedates ? '<p class="bloquecero-header-dates">' . $coursedates . '</p>' : '') . '
                <p class="bloquecero-header-teachers">' . get_string('teachingteam', 'block_bloquecero') . ': ' . $contactbuttonshtml . '</p>
            </div>
            ' . (!empty($this->config->show_september_notice) ? '
            <!-- Aviso convocatoria de septiembre -->
            <div class="bloquecero-september-notice" role="alert">
                <strong>' . get_string('septembernotice_title', 'block_bloquecero') . '</strong>
                <p>' . get_string('septembernotice_text', 'block_bloquecero') . '</p>
            </div>' : '') . '
            <!-- Bloques de información de contacto de cada profesor -->
            ' . (!$ismetacourse ? $contactblockshtml : '') . '


            <!-- Sección de foros y demás secciones -->
            <div class="bloquecero-forums-wrapper" style="padding: 0 40px;' . ($ismetacourse ? 'display:none;' : '') . '">
                <nav class="bloquecero-tabs" aria-label="' . get_string('courseforums', 'block_bloquecero') . '">'
                    . (!empty($forumanunciosurl) ? '
                    <a href="' . $forumanunciosurl . '" class="bloquecero-tab">
                        ' . get_string('noticeboard', 'block_bloquecero')
                            . (isset($countanuncios) && is_array($countanuncios) && array_sum($countanuncios) > 0
                                ? ' <span style="display:inline-block;min-width:22px;height:22px;line-height:22px;background:#4E6A1E;color:#fff;font-weight:600;font-size:0.98em;border-radius:50%;text-align:center;margin-left:7px;vertical-align:middle;" aria-label="' . array_sum($countanuncios) . ' ' . get_string('unreadposts', 'block_bloquecero') . '">' . array_sum($countanuncios) . '</span>'
                                : '') . '
                    </a>' : '')
                    . (!empty($forumtutoriasurl) ? '
                    <a href="' . $forumtutoriasurl . '" class="bloquecero-tab">
                        ' . get_string('forum_tutorias', 'block_bloquecero')
                        . (isset($counttutorias) && is_array($counttutorias) && array_sum($counttutorias) > 0
                            ? ' <span style="display:inline-block;min-width:22px;height:22px;line-height:22px;background:#4E6A1E;color:#fff;font-weight:600;font-size:0.98em;border-radius:50%;text-align:center;margin-left:7px;vertical-align:middle;" aria-label="' . array_sum($counttutorias) . ' ' . get_string('unreadposts', 'block_bloquecero') . '">' . array_sum($counttutorias) . '</span>'
                            : '') . '
                    </a>' : '')
                    . (!empty($forumestudiantesurl) ? '
                    <a href="' . $forumestudiantesurl . '" class="bloquecero-tab">
                        ' . get_string('forum_estudiantes', 'block_bloquecero')
                        . (isset($countestudiantes) && is_array($countestudiantes) && array_sum($countestudiantes) > 0
                            ? ' <span style="display:inline-block;min-width:22px;height:22px;line-height:22px;background:#4E6A1E;color:#fff;font-weight:600;font-size:0.98em;border-radius:50%;text-align:center;margin-left:7px;vertical-align:middle;" aria-label="' . array_sum($countestudiantes) . ' ' . get_string('unreadposts', 'block_bloquecero') . '">' . array_sum($countestudiantes) . '</span>'
                            : '') . '
                    </a>' : '')
                    . (!empty($forumseptiembreurl) ? '
                    <a href="' . $forumseptiembreurl . '" class="bloquecero-tab bloquecero-tab-september">
                        ' . get_string('sept_forum_name', 'block_bloquecero')
                        . (isset($countseptiembre) && is_array($countseptiembre) && array_sum($countseptiembre) > 0
                            ? ' <span style="display:inline-block;min-width:22px;height:22px;line-height:22px;background:#4E6A1E;color:#fff;font-weight:600;font-size:0.98em;border-radius:50%;text-align:center;margin-left:7px;vertical-align:middle;" aria-label="' . array_sum($countseptiembre) . ' ' . get_string('unreadposts', 'block_bloquecero') . '">' . array_sum($countseptiembre) . '</span>'
                            : '') . '
                    </a>' : '') . '
                </nav>
            </div>
            <!-- Bloques divididos en dos columnas -->
            <div class="bloquecero-maincards-row" style="' . ($ismetacourse ? 'display:none;' : '') . '">
                <div style="width: 50%; box-sizing: border-box;">
                    ' . $sesionesdirecto . '
                </div>
                <div style="width: 50%; box-sizing: border-box;">
                    ' . $calendarioactividades . '
                </div>
            </div>
                    <!-- Carrusel de tarjetas de secciones -->
                    <div class="bloquecero-sections-title" style="text-align: left; padding: 0 40px; margin-bottom: 10px;' . ($ismetacourse ? 'display:none;' : '') . '">
            <h3 style="color: #004D35; margin-top: 0;">' . get_string('coursesections', 'block_bloquecero') . '</h3>
            </div>' .
            (!$ismetacourse ? $carouselcontainer : '') . '


                        <style>
            @media (max-width: 900px) {
            .udima-maincard, .sesiones-directo-maincard {
                padding: 18px 10px !important;
                font-size: 0.97em;
                min-width: 0;
            }
            .calendario-actividades-header h3,
            .sesiones-directo-header h3 {
                font-size: 1.07em;
            }
            .week-selector,
            .sesiones-directo-selector {
                font-size: 0.95em;
            }
            }
            @media (max-width: 660px) {
            .bloquecero-forums-wrapper,
            .bloquecero-sections-title {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .bloquecero-maincards-row {
                flex-direction: column !important;
                gap: 10px !important;
            }
            .bloquecero-maincards-row > div {
                width: 100% !important;
                margin-bottom: 14px;
            }
            .carousel-container {
                padding: 0 24px !important;
            }
            .udima-maincard,
            .sesiones-directo-maincard {
                font-size: 0.96em;
                padding: 12px 8px !important;
                min-width: 0;
            }
            .calendario-actividades-header h3,
            .sesiones-directo-header h3 {
                font-size: 0.99em;
            }
            .week-selector,
            .sesiones-directo-selector {
                font-size: 0.94em;
            }
            }
            @media (max-width: 500px) {
            .calendario-actividades-header h3,
            .sesiones-directo-header h3 {
                font-size: 0.92em;
                white-space: normal;
            }
            .week-selector,
            .sesiones-directo-selector {
                font-size: 0.92em;
                white-space: normal;
            }
            }
            .bloquecero-maincards-row {
            display: flex;
            gap: 20px;
            margin: 20px 40px;
            align-items: stretch;
            }
            @media (max-width: 660px) {
            .bloquecero-maincards-row {
                margin: 12px 0 !important;
                gap: 10px !important;
            }
            }
            .bloquecero-maincards-row > div {
            display: flex;
            }
            .udima-maincard,
            .calendario-actividades-maincard,
            .sesiones-directo-maincard {
            flex: 1;
            display: flex;
            flex-direction: column;
            }
            .calendario-actividades-container,
            .sesiones-directo-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            }

            </style>
            ' . ((has_capability('block/bloquecero:viewcourse', $coursecontext)) ? $togglebuttonhtml : '') . '
            <script>
            window.bloquecero_toggle = function() {
                var btn = document.getElementById(\'bloquecero-mostrarcurso-btn\');
                var region = document.getElementById(\'region-main\');
                var btntext = document.getElementById(\'bloquecero-mostrarcurso-text\');
                var isHidden = region && region.style.display === \'none\';

                if (isHidden) {
                    if(btn) { btn.classList.add(\'open\'); btn.setAttribute(\'aria-expanded\', \'true\'); }
                    if(btntext) btntext.innerHTML = \'' . $strhidecourse . '\';
                    if (region) {
                        region.style.display = \'\';
                        region.classList.remove(\'bloquecero-fadein\');
                        setTimeout(function() {
                            region.classList.add(\'bloquecero-fadein\');
                        }, 10);
                    }
                    // Los bloques laterales siempre visibles, no necesitan reset
                    [
                        \'.page-header\',\'.page-context-header\',\'.course-header\',\'.page-header-headings\',\'.page-title\',\'.course-title\'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = \'\'; });
                    });
                    [
                        \'.nav-tabs\',\'.nav-tabs-line\',\'.secondary-navigation\',\'.secondary-nav\'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = \'\'; });
                    });
                    // Cambia texto a "ocultar curso"
                    var btn = document.getElementById(\'bloquecero-mostrarcurso-btn\');
                    if(btn) {
                        btn.classList.toggle(\'cerrado\', !isHidden);
                    }
                    if(btntext) btntext.innerHTML = \'' . $strhidecourse . '\';
                } else {
                    if(btn) { btn.classList.remove(\'open\'); btn.setAttribute(\'aria-expanded\', \'false\'); }
                    if(btntext) btntext.innerHTML = \'' . $strshowcourse . '\';
                    if (region) {
                        region.style.display = \'none\';
                        region.classList.remove(\'bloquecero-fadein\');
                    }
                    // Los bloques laterales siempre visibles
                    [
                        \'.page-header\',\'.page-context-header\',\'.course-header\',\'.page-header-headings\',\'.page-title\',\'.course-title\'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = \'none\'; });
                    });
                    [
                        \'.nav-tabs\',\'.nav-tabs-line\',\'.secondary-navigation\',\'.secondary-nav\'
                    ].forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){ e.style.display = \'none\'; });
                    });
                    // Cambia texto a "mostrar curso"
                    if(btntext) btntext.innerHTML = \'' . $strshowcourse . '\';
                }
            };
            // Al cargar, oculta el curso y ajusta el botón
            document.addEventListener(\'DOMContentLoaded\', function() {
                var region = document.getElementById(\'region-main\');
                var btnicon = document.getElementById(\'bloquecero-mostrarcurso-icon\');
                var btntext = document.getElementById(\'bloquecero-mostrarcurso-text\');
                if (region) region.style.display = \'none\';
                // Los bloques laterales siempre visibles
                [
                    \'.page-header\',\'.page-context-header\',\'.course-header\',\'.page-header-headings\',\'.page-title\',\'.course-title\'
                ].forEach(function(selector){
                    document.querySelectorAll(selector).forEach(function(e){ e.style.display = \'none\'; });
                });
                [
                    \'.nav-tabs\',\'.nav-tabs-line\',\'.secondary-navigation\',\'.secondary-nav\'
                ].forEach(function(selector){
                    document.querySelectorAll(selector).forEach(function(e){ e.style.display = \'none\'; });
                });
                if(btntext) btntext.innerHTML = \'' . $strshowcourse . '\';
            });
            </script>
            <style>
            .drawer-toggler.drawer-left-toggle.open-nav.d-print-none {
                display: none !important;
            }
            #page-navbar {
                display: none !important;
            }
            .block_calendar_month.block.card.mb-3 {
                display: block !important;
            }
            .bloquecero-section-header-flex {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 12px;
                margin-bottom: 8px;
            }

            .bloquecero-section-badge {
                display: block;
                margin: -18px -16px 14px -16px;
                padding: 6px 16px;
                background: #4E6A1E;
                color: #fff;
                font-weight: 600;
                font-size: 0.78em;
                text-align: center;
                border-radius: 3px 3px 0 0;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }
            .bloquecero-section-card {
                position: relative;
            }
            .forum-card:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
            }
            .ghost-button {
                display: flex;
                align-items: center;
                background: #fff;
                color: #004D35;
                border: 2px solid #004D35;
                border-radius: 3px;
                padding: 10px 20px;
                font-weight: 600;
                gap: 10px;
                min-width: 160px;
                transition: background 0.2s, box-shadow 0.2s;
                box-shadow: none;
                cursor: pointer;
                font-size: 1em;
                text-decoration: none !important;
                width: 220px;
                justify-content: center;
            }
                .ghost-button:visited,
                .ghost-button:focus,
                .ghost-button:hover,
                .ghost-button:active {
                    text-decoration: none !important;
                    color: #004D35;
                }
                .ghost-button:hover,
                .ghost-button:focus {
                    background: #004D35 !important;
                    color: #fff !important;
                    border-color: #004D35 !important;
                    box-shadow: 0 6px 16px rgba(0, 77, 53, 0.10);
                    outline: none;
                    transition: background 0.2s, color 0.2s, border-color 0.2s;
                }
                .carousel-container {
                    padding: 0 40px;
                    box-sizing: border-box;
                }
                .carousel-btn {
                    position: absolute;
                    z-index: 2;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    border: none;
                    background: #fff;
                    color: #004D35;
                    font-size: 1.1em;
                    font-weight: 700;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
                    transition: box-shadow 0.2s, transform 0.2s;
                    padding: 0;
                }
                .carousel-btn:hover {
                    box-shadow: 0 4px 14px rgba(0,0,0,0.28);
                    transform: translateY(-50%) scale(1.1);
                }
                .carousel-btn:focus-visible {
                    outline: 3px solid #004D35;
                    outline-offset: 2px;
                }
                .carousel-btn-left {
                    left: 0;
                }
                .carousel-btn-right {
                    right: 0;
                }
                .sections-carousel {
                    display: flex;
                    gap: 18px;
                    overflow-x: auto;
                    scroll-snap-type: x mandatory;
                    /* Ocultar scrollbar en Firefox */
                    scrollbar-width: none;
                    /* Ocultar scrollbar en IE, Edge */
                    -ms-overflow-style: none;
                    width: 100%;
                }

                /* Ocultar scrollbar en Chrome, Safari y Opera */
                .sections-carousel::-webkit-scrollbar {
                    display: none;
                }

                /* Calculamos el ancho para que siempre quepan 4 tarjetas dejando 3 gaps de 18px (54px total) */
                .section-card {
                    flex: 1 1 0;
                    min-width: 0;
                    max-width: 100%;
                    background: #004D35; /* nuevo fondo verde */
                    border: 1.5px solid #004D35;
                    border-radius: 10px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                    color: #fff; /* texto en blanco para buen contraste */
                    font-weight: 600;
                    font-size: 0.9em;
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    justify-content: flex-start;
                    transition: box-shadow 0.2s;
                    cursor: pointer;
                    padding: 0;
                    overflow: hidden;
                    scroll-snap-align: start;
                }
                .section-card.active-section {
                    background: rgba(225, 255, 209, 0.75) !important;
                    border: 2.5px solid #1abc9c;
                    box-shadow: 0 4px 16px rgba(225, 255, 209, 0.75);
                }
                .section-card.marker-section {
                    border: 2.5px solid #FFD600 !important;
                    box-shadow: 0 4px 16px rgba(255,214,0,0.12);
                }
                .section-title-header {
                    background: transparent !important;
                    color: #fff !important;
                    border: none;
                    width: 100%;
                    text-align: left;
                    padding: 14px 16px;
                    margin: 0;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    font-size: 1em;
                    outline: none;
                    border-radius: 10px 10px 0 0;
                    transition: background 0.2s, color 0.2s;
                }
                .sections-carousel:has(.section-card:nth-child(n+5)) .section-card {
                    flex: 0 0 calc((100% - 54px) / 4);
                    max-width: calc((100% - 54px) / 4);
                    min-width: calc((100% - 54px) / 4);
                }
                @media (max-width: 660px) {
                    .section-card,
                    .sections-carousel:has(.section-card:nth-child(n+5)) .section-card {
                        flex: 0 0 72vw !important;
                        max-width: 72vw !important;
                        min-width: 0 !important;
                        box-sizing: border-box;
                    }
                }
                @media (max-width: 480px) {
                    .section-card,
                    .sections-carousel:has(.section-card:nth-child(n+5)) .section-card {
                        flex: 0 0 80vw !important;
                        max-width: 80vw !important;
                        min-width: 0 !important;
                        box-sizing: border-box;
                    }
                }
                .section-title-header .section-title-text {
                    color: #fff !important;
                    font-weight: 600;
                    font-size: 1em;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: block;
                    width: 100%;
                    max-width: 100%;
                }
                .section-title-header .section-arrow {
                    color: #004D35 !important;
                    font-size: 1.1em;
                    margin-left: 8px;
                    transition: transform 0.3s;
                }
                .section-title-btn.open .section-arrow {
                    transform: rotate(90deg);
                }
                .section-title-btn.open {
                    background: rgba(225, 255, 209, 0.75) !important; /* Verde claro */
                }
                .section-title-btn.open .section-title-text {
                    color: #004D35 !important; /* Ajusta el color del texto si es necesario */
                }
                .section-activities {
                    background: #fff;
                    transition: max-height 0.3s ease, opacity 0.3s;
                    overflow: hidden;
                    opacity: 1;
                    max-height: 1000px;
                    border-radius: 0 0 10px 10px;
                    padding-bottom: 8px;
                }
                .section-activities.collapsed {
                    opacity: 0;
                    max-height: 0;
                    padding: 0 !important;
                }

                .udima-card {
                    background: #fff !important;
                    border: 1.5px solid #E2EDE4 !important;
                    border-radius: 3px !important;
                    box-shadow: 0 2px 14px rgba(89,157,74,0.05);
                    color: #0C3B2E !important;
                    font-family: \'Inter\', Arial, sans-serif;
                    font-size: 1.1em;
                    font-weight: 400;
                    padding: 30px 24px 22px 24px !important;
                    margin-bottom: 16px;
                    min-width: 260px;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    justify-content: flex-start;
                    transition: box-shadow 0.2s, border-color 0.2s;
                    position: relative;
                }
                .udima-forum-card {
                    background: #fff !important;
                    border: 1.5px solid #E2EDE4 !important;
                    border-radius: 3px !important;
                    box-shadow: 0 2px 14px rgba(89,157,74,0.05);
                    color: #0C3B2E !important;
                    font-family: \'Inter\', Arial, sans-serif;
                    font-size: 1.08em;
                    font-weight: 500;
                    padding: 24px 18px 20px 18px !important;
                    margin-bottom: 0;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    transition: box-shadow 0.18s, border-color 0.18s;
                    position: relative;
                    text-align: center;
                    cursor: pointer;
                }
                .udima-forum-card h3 {
                    font-size: 1.17em;
                    font-weight: 600;
                    margin: 0;
                    color: #0C3B2E !important;
                    letter-spacing: 0.01em;
                }
                a:has(.udima-forum-card):hover .udima-forum-card,
                .udima-forum-card:hover {
                    border-color: #B7C65C !important;
                    box-shadow: 0 8px 32px rgba(183,198,92,0.12);
                    background: #F7FAF6 !important;
                    color: #0C3B2E !important;
                }
                .udima-card .section-number {
                    color: #6B7D2E !important;
                    font-size: 2em;
                    font-weight: 700;
                    margin-bottom: 16px;
                    display: block;
                    line-height: 1;
                    letter-spacing: 0.01em;
                }
                .udima-card .section-title-header {
                    background: none !important;
                    color: #0C3B2E !important;
                    border: none;
                    font-weight: 600;
                    font-size: 1.14em;
                    padding: 0;
                    margin: 0;
                    text-align: left;
                    width: 100%;
                    justify-content: space-between;
                    display: flex;
                    align-items: center;
                    outline: none;
                    border-radius: 0;
                    transition: color 0.2s;
                }
                .udima-card .section-arrow {
                    color: #6B7D2E !important;
                    font-size: 1.6em;
                    margin-left: 18px;
                    transition: transform 0.3s;
                }
                .udima-card.active-section, .udima-card .section-title-btn.open {
                    border-color: #B7C65C !important;
                    box-shadow: 0 6px 24px rgba(183,198,92,0.08);
                    background: #F7FAF6 !important;
                }
                .udima-card:hover {
                    box-shadow: 0 10px 36px rgba(89,157,74,0.11);
                    border-color: #B7C65C !important;
                }
                .udima-maincard {
                    background: #fff !important;
                    border: 1.5px solid #E2EDE4 !important;
                    border-radius: 3px !important;
                    box-shadow: 0 2px 14px rgba(89,157,74,0.05);
                    color: #0C3B2E !important;
                    font-family: \'Inter\', Arial, sans-serif;
                    font-size: 1.04em;
                    font-weight: 400;
                    padding: 28px 22px 22px 22px !important;
                    margin-bottom: 0;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    justify-content: flex-start;
                    transition: box-shadow 0.18s, border-color 0.18s;
                    position: relative;
                    text-align: left;
                }
                .udima-maincard h3 {
                    font-size: 1.19em;
                    font-weight: 600;
                    margin: 0 0 12px 0;
                    color: #0C3B2E !important;
                    letter-spacing: 0.01em;
                    min-width: 0;
                    overflow-wrap: break-word;
                }
                .udima-maincard:hover {
                    border-color: #B7C65C !important;
                    box-shadow: 0 8px 32px rgba(183,198,92,0.11);
                    background: #F7FAF6 !important;
                }
                .udima-menu-bar {
                    display: flex;
                    gap: 28px;
                    justify-content: flex-start;
                    align-items: center;
                    padding: 8px 24px 0 24px;
                    background: none;
                    margin-bottom: 8px;
                    border-bottom: 1.5px solid #E2EDE4;
                    min-height: 38px;
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    overflow-y: hidden;
                    white-space: nowrap;
                    scrollbar-width: thin;
                    scrollbar-color: #B7C65C #f0f0f0;
                }
                .udima-menu-bar::-webkit-scrollbar {
                    height: 6px;
                }
                .udima-menu-bar::-webkit-scrollbar-thumb {
                    background: #B7C65C;
                    border-radius: 3px;
                }
                .udima-menu-bar::-webkit-scrollbar-track {
                    background: #f0f0f0;
                }
                .udima-menu-link {
                    display: flex;
                    align-items: center;
                    gap: 7px;
                    background: none;
                    color: #0C3B2E;
                    border: none;
                    border-radius: 0;
                    font-size: 1em;
                    font-weight: 500;
                    padding: 0 4px 3px 4px;
                    text-decoration: none !important;
                    transition: color 0.13s, border-bottom 0.15s;
                    box-shadow: none;
                    position: relative;
                    height: 36px;
                }
                .udima-menu-link .menu-icon {
                    width: 18px;
                    height: 18px;
                    font-size: 1.07em;
                    margin-right: 3px;
                    display: inline-block;
                    color: #3D7A1C;
                }
                .udima-menu-link:hover,
                .udima-menu-link:focus {
                    color: #6B7D2E;
                    border-bottom: 2.5px solid #B7C65C;
                    outline: none;
                    background: none;
                }
                .udima-menu-link:active {
                    color: #004D35;
                }
                @media (max-width: 800px) {
                    .udima-menu-bar {
                        gap: 4px;
                        justify-content: flex-start;
                        overflow-x: visible;
                        white-space: normal;
                        padding: 4px 8px;
                    }
                    .udima-menu-link {
                        height: 36px;
                        width: 40px;
                        justify-content: center;
                        padding: 0;
                        gap: 0;
                    }
                    .udima-menu-link span {
                        display: none;
                    }
                    .udima-menu-link .menu-icon {
                        width: 20px;
                        height: 20px;
                        font-size: 1.15em;
                        margin-right: 0;
                    }
                }

                .moodle-toggle-centering {
                    display: flex;
                    justify-content: center;
                    margin: 32px 0 18px 0;
                    width: 100%;
                }
                .moodle-toggle-btn {
                    display: flex;
                    align-items: center;
                    gap: 18px;
                    background: none;
                    border: none;
                    outline: none;
                    cursor: pointer;
                    padding: 0;
                    font-size: 1.3em;
                    font-family: inherit;
                    transition: filter 0.17s;
                }
                .moodle-toggle-btn:focus,
                .moodle-toggle-btn:hover {
                    filter: brightness(0.95);
                }
                .moodle-toggle-circle {
                    width: 44px;
                    height: 44px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #EDF3FB;
                    border-radius: 50%;
                    box-shadow: 0 2px 8px rgba(22,85,160,0.06);
                    transition: background 0.18s;
                }
                .moodle-toggle-btn:hover .moodle-toggle-circle {
                    background: #e2eefe;
                }
                .moodle-toggle-chevron {
                    transition: transform 0.22s cubic-bezier(.4,2,.6,1), stroke 0.18s;
                    transform: rotate(0deg);
                    display: block;
                }
                .moodle-toggle-btn.open .moodle-toggle-chevron {
                    transform: rotate(90deg);
                }
                .moodle-toggle-label {
                    font-weight: 700;
                    color: #222;
                    font-size: 1.16em;
                    letter-spacing: 0.01em;
                    line-height: 1.18;
                    margin-top: 1px;
                }
                .bloquecero-section-card {
                    border-radius: 3px;
                    box-shadow: 0 2px 12px rgba(185,200,160,0.10);
                    padding: 18px 16px 16px 16px;
                    margin: 0 8px 24px 0;
                    width: 370px;
                    min-width: 320px;
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-start;
                    align-items: flex-start;
                    transition: box-shadow 0.19s;
                }
                .bloquecero-section-card:hover {
                    box-shadow: 0 6px 22px rgba(183,198,92,0.12);
                }
                .bloquecero-section-header {
                    width: 100%;
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    margin-bottom: 28px;
                }
                .bloquecero-section-number {
                    font-size: 1.0em;
                    font-weight: 400;
                    color: #6B7D2E;
                    flex: none;
                    letter-spacing: 0.05em;
                }
                .bloquecero-section-number,
                .bloquecero-section-number:link,
                .bloquecero-section-number:visited {
                    font-size: 1.0em;
                    font-weight: 400;
                    color: #6B7D2E;
                    flex: none;
                    letter-spacing: 0.05em;
                    text-decoration: none;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    white-space: normal;
                }
                .bloquecero-section-number:hover,
                .bloquecero-section-number:focus {
                    color: #6B7D2E;
                }
                .bloquecero-section-line {
                    width: 100%;
                    height: 2px;
                    background: #B7C65C;
                    border-radius: 2px;
                    margin: 0 0 12px 0;
                }
                .bloquecero-section-title {
                    font-size: 1.11em;
                    font-weight: 500;
                    color: #222;
                    flex: none;
                    white-space: nowrap;
                    margin-left: 0;
                }
                .bloquecero-section-content {
                    flex: 1;
                    font-size: 1.09em;
                    color: #333;
                    margin-top: 0;
                    display: block;
                    height: auto;
                }
                .bloquecero-section-content ul {
                    padding-left: 0;
                    margin: 0;
                    list-style: none;
                }
                .bloquecero-section-content li {
                    margin-bottom: 12px;
                }
            </style>
            <style>
            /* Sesiones en directo (igual que calendario, pero .sesiones-directo-*) */
            .sesiones-directo-maincard {
                min-height: 180px;
                height: 100%;
                display: flex;
                flex-direction: column;
                padding: 28px 22px 22px 22px !important;
                background: #fff;
                border: 1.5px solid #E2EDE4;
                border-radius: 3px;
                box-shadow: 0 2px 14px rgba(89,157,74,0.05);
            }
            .calendario-actividades-header {
                display: flex;
                flex-direction: column;
                gap: 0;
                min-width: 0;
                width: 100%;
                margin-bottom: 10px;
            }
            .calendario-actividades-header h3 {
                margin: 0;
                font-size: 1.19em;
                font-weight: 600;
                color: #0C3B2E;
                letter-spacing: 0.01em;
                min-width: 0;
            }
            /* .week-selector y .sesiones-directo-selector: regla conjunta arriba */
            #week-label {
                white-space: nowrap;
                text-align: center;
                line-height: 1.14;
                font-size: 0.75em;
                min-width: 58px;
                max-width: 100px;
                font-weight: 600;
                color: #004D35;
                letter-spacing: 0.01em;
            }
            .sesiones-directo-header {
                display: flex;
                flex-direction: column;
                gap: 0;
                min-width: 0;
                width: 100%;
                margin-bottom: 10px;
            }
            .sesiones-directo-header h3 {
                margin: 0;
                font-size: 1.19em;
                font-weight: 600;
                color: #0C3B2E;
                letter-spacing: 0.01em;
                min-width: 0;
            }
            /* .week-selector y .sesiones-directo-selector: regla conjunta arriba */
            .sesiones-directo-selector button,
            .week-selector button {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                border: none;
                background: #fff;
                color: #004D35;
                font-size: 0.95em;
                font-weight: 700;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                transition: box-shadow 0.2s, transform 0.2s;
                padding: 0;
                flex-shrink: 0;
            }
            .sesiones-directo-selector button:hover,
            .week-selector button:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.25);
                transform: scale(1.1);
            }
            .sesiones-directo-selector button:focus-visible,
            .week-selector button:focus-visible {
                outline: 3px solid #004D35;
                outline-offset: 2px;
            }
            #sesion-label {
                white-space: nowrap;
                text-align: center;
                line-height: 1.14;
                font-size: 0.75em;
                min-width: 58px;
                max-width: 100px;
                font-weight: 600;
                color: #004D35;
                letter-spacing: 0.01em;
            }
            .week-selector,
            .sesiones-directo-selector {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 1em;
                font-weight: 500;
                margin-left: auto;
                flex-shrink: 0;
                white-space: nowrap;
            }
            .calendario-actividades-calendaricon,
            .sesiones-directo-calendaricon {
                flex-shrink: 0;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                border: 1px solid #B7C65C;
                border-radius: 20px;
                padding: 4px 10px 4px 8px;
                background: #fff;
                color: #6B7D2E;
                font-size: 0.78em;
                font-weight: 600;
                box-shadow: 0 1px 4px rgba(0,0,0,0.10);
                transition: background 0.15s, box-shadow 0.15s, color 0.15s;
            }
            .calendario-actividades-calendaricon:hover,
            .sesiones-directo-calendaricon:hover {
                background: #B7C65C;
                color: #fff;
                box-shadow: 0 2px 8px rgba(107,125,46,0.18);
            }
            .sesiones-directo-container {
                width: 100%;
                padding: 0;
                margin: 0;
            }
            #sesiones-list-content ul {
                padding-left: 0;
                margin: 0;
                width: 100%;
                list-style: none;
            }
            #sesiones-list-content li {
                margin-bottom: 8px;
                font-size: 1em;
                display: flex;
                align-items: center;
                color: #222;
            }
            #sesiones-list-content li a {
                color: #004D35;
                text-decoration: none;
                transition: color 0.14s;
            }
            #sesiones-list-content li:hover,
            #sesiones-list-content li:hover a {
                color: #6B7D2E;
            }
            #sesiones-list-content li:hover a {
                text-decoration: none !important;
            }
            /* Forzar tamaño de fuente en spans de week-selector y sesiones-directo-selector */
            .week-selector span,
            .sesiones-directo-selector span {
                font-size: 0.93em !important;
            }
            </style>
            <style>
            .bloquecero-header-responsive {
                position: relative;
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 20px;
                width: 100%;
                aspect-ratio: 5 / 1;
                min-height: 120px;
                background: #fff;
            }
            .bloquecero-header-bg-img {
                position: absolute;
                top: 0; left: 0; width: 100%; height: 100%;
                object-fit: contain;
                z-index: 0;
                pointer-events: none;
                opacity: 0.9;
            }
            .bloquecero-header-content {
                position: absolute;
                top: 0; left: 0; width: 100%; height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                align-items: flex-end;
                padding: 30px;
                z-index: 2;
            }
            .bloquecero-header-title {
                font-family: \'Inter\', Arial, sans-serif !important;
                font-weight: 400 !important;
                font-size: clamp(2rem, 5.5vw, 2.5rem) !important;
                color: #222 !important;
                letter-spacing: -0.02em !important;
                line-height: 1.05;
                text-shadow: none !important;
                margin: 0 0 10px 0;
            }
            .bloquecero-header-dates {
                margin: 0 0 10px 0;
                font-size: 1em;
                color: black;
            }
            .bloquecero-info-row {
                padding: 0 20px;
                text-align: right;
            }
            .bloquecero-header-teachers {
                margin: 0 0 10px 0;
                font-size: 1.2em;
                color: black;
                font-weight: 500;
                line-height: 1.6;
            }
            .bloquecero-september-notice {
                margin: 10px 20px 10px 20px;
                padding: 14px 18px;
                background-color: #fff3cd;
                border: 2px solid #e6a817;
                border-radius: 6px;
                color: #6b4c00;
            }
            .bloquecero-september-notice strong {
                display: block;
                font-size: 1.05em;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .bloquecero-september-notice p {
                margin: 0;
                font-size: 0.95em;
                line-height: 1.5;
            }
            .bloquecero-item-hidden {
                opacity: 0.55;
            }
            .bloquecero-hidden-badge {
                display: inline-block;
                font-size: 0.7em;
                font-weight: 600;
                background: rgba(0,0,0,0.35);
                color: #fff;
                padding: 2px 7px;
                border-radius: 3px;
                margin: 6px 6px 2px;
                letter-spacing: 0.03em;
                text-transform: uppercase;
            }
            .bloquecero-activity-hidden {
                font-size: 0.72em;
                font-weight: 600;
                background: #e0e0e0;
                color: #555;
                padding: 1px 5px;
                border-radius: 3px;
                margin-left: 5px;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                vertical-align: middle;
            }
            .bloquecero-metachild-notice {
                margin: 0 0 6px;
                padding: 0;
                font-size: 0.8em;
                color: #888;
                line-height: 1.6;
            }
            .bloquecero-meta-notice {
                margin: 14px 20px 0;
                padding: 16px 20px;
                background: #1655A0;
                border-radius: 6px;
                font-size: 1em;
                color: #fff;
                line-height: 1.6;
            }
            .bloquecero-meta-notice strong {
                display: block;
                font-size: 1.05em;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .bloquecero-meta-notice p { margin: 0 0 8px; }
            .bloquecero-meta-notice p:last-child { margin: 0; }
            .bloquecero-meta-link {
                font-weight: 700;
                color: #fff;
                text-decoration: underline;
                font-size: 1.05em;
            }
            .bloquecero-meta-link:hover { color: #cce0ff; }
            @media (max-width: 600px) {
                .bloquecero-main-wrapper {
                    padding: 0 6px !important;
                }
                .bloquecero-info-row {
                    padding: 0 4px;
                    text-align: right;
                }
                .bloquecero-header-dates {
                    font-size: 0.88em;
                    text-align: right;
                }
                .bloquecero-header-teachers {
                    font-size: 0.92em;
                    text-align: right;
                }
                .carousel-container {
                    padding: 0 22px !important;
                }
                .carousel-btn {
                    width: 30px !important;
                    height: 30px !important;
                    font-size: 1em !important;
                }
            }

            @media (max-width: 800px) {
                .bloquecero-header-bg-img {
                    display: none;
                }
                .bloquecero-header-responsive {
                    aspect-ratio: unset;
                    min-height: auto;
                }
                .bloquecero-header-content {
                    position: relative;
                    top: auto; left: auto;
                    width: 100%;
                    height: auto;
                    align-items: flex-start;
                    padding: 8px 0 4px 0;
                }
                .bloquecero-header-title {
                    line-height: 1.18;
                    margin-bottom: 5px;
                    text-shadow: none;
                }
                .bloquecero-header-dates {
                    font-size: 0.92em;
                    margin-bottom: 5px;
                }
                .bloquecero-header-teachers {
                    font-size: 1em;
                    margin-bottom: 5px;
                }
            }

            .bloquecero-teacher-btn {
                background: none;
                border: none;
                color: #004D35;
                font-size: 1em;
                cursor: pointer;
                padding: 0 2px;
                margin: 0 2px;
                display: inline;
                font-weight: 500;
                transition: color 0.18s;
                font-family: inherit;
                outline: none;
                text-decoration: underline;
                text-decoration-style: dotted;
                text-underline-offset: 3px;
            }
            .bloquecero-teacher-btn:hover,
            .bloquecero-teacher-btn:focus {
                color: #6B7D2E;
                text-decoration-style: solid;
            }
            .bloquecero-teacher-infoicon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 14px;
                height: 14px;
                border-radius: 50%;
                background: #004D35;
                color: #fff;
                font-size: 0.65em;
                font-style: italic;
                font-weight: 700;
                margin-left: 5px;
                vertical-align: middle;
                transition: background 0.18s;
                line-height: 1;
            }
            .bloquecero-teacher-btn:hover .bloquecero-teacher-infoicon,
            .bloquecero-teacher-btn:focus .bloquecero-teacher-infoicon,
            .bloquecero-teacher-btn[aria-expanded="true"] .bloquecero-teacher-infoicon {
                background: #6B7D2E;
            }
            </style>
            <style>
                .bloquecero-tabs {
                    display: flex;
                    gap: 18px;
                    justify-content: flex-end;
                    margin: 0 0 18px 0;
                }
                .bloquecero-tab {
                    display: flex;
                    align-items: center;
                    padding: 7px 18px;
                    border: none;
                    border-bottom: 2px solid #B7C65C;
                    color: #004D35;
                    font-size: 1.05em;
                    font-weight: 500;
                    border-radius: 4px 4px 0 0;
                    text-decoration: none !important;
                    transition: color 0.13s, border-bottom 0.15s;
                }
                .bloquecero-tab:hover,
                .bloquecero-tab:focus {
                    color: #6B7D2E;
                    border-bottom: 2.5px solid #B7C65C;
                }
                @media (max-width: 600px) {
                    .bloquecero-tabs {
                        gap: 8px;
                        flex-wrap: wrap;
                        justify-content: flex-end;
                    }
                    .bloquecero-tab {
                        font-size: 1em;
                        padding: 6px 10px;
                    }
                }
                    .sesiones-directo-calendaricon {
                    margin-left: 14px;
                    display: inline-block;
                    vertical-align: middle;
                }

                #bibliografia-content a:hover {
                    color: #6B7D2E !important;
                    text-decoration: underline !important;
                }
                #bibliografia-content ul li {
                    border-bottom: 1px solid #f0f0f0;
                    padding-bottom: 10px;
                }
                #bibliografia-content ul li:last-child {
                    border-bottom: none;
                }
            </style>

            <script>
                // Datos con las actividades de cada sección (clave: id de la sección)
                const sectionsActivitiesData = ' . $sectionsactivitiesjson . ';

                function scrollCarousel(direction) {
                    var carousel = document.querySelector(".sections-carousel");
                    var card = carousel.querySelector(".section-card");
                    var scrollAmount = card ? card.offsetWidth + 18 : 240;
                    carousel.scrollBy({ left: direction * scrollAmount, behavior: "smooth" });
                    setTimeout(updateCarouselArrows, 500);
                }
                // Variable para almacenar la sección actualmente mostrada
                let lastSectionShown = null;

                function showSectionActivities(sectionId, btn) {
                    var container = document.getElementById("section-activities-container");
                    // Quitar la clase "open" de todos los botones.
                    document.querySelectorAll(\'.section-title-btn\').forEach(function(b){
                        b.classList.remove(\'open\');
                    });
                    // Si se pulsa la misma sección que ya está activa y el bloque es visible, se oculta.
                    if (lastSectionShown === sectionId && container.style.display === "block") {
                        container.innerHTML = "";
                        container.style.display = "none";
                        lastSectionShown = null;
                    } else {
                        if (sectionsActivitiesData[sectionId]) {
                            container.innerHTML = sectionsActivitiesData[sectionId];
                            container.style.display = "block";
                            // Agregar la clase "open" al botón actual para girar la flecha.
                            btn.classList.add(\'open\');
                            lastSectionShown = sectionId;
                        } else {
                            container.innerHTML = "";
                            container.style.display = "none";
                            lastSectionShown = null;
                        }
                    }
                }
                function updateCarouselArrows() {
                    var carousel = document.querySelector(".sections-carousel");
                    var leftArrow = document.querySelector(".carousel-btn-left");
                    var rightArrow = document.querySelector(".carousel-btn-right");
                    if (carousel.scrollLeft <= 0) {
                        leftArrow.style.display = "none";
                    } else {
                        leftArrow.style.display = "block";
                    }
                    if (carousel.scrollWidth <= carousel.clientWidth + carousel.scrollLeft) {
                        rightArrow.style.display = "none";
                    } else {
                        rightArrow.style.display = "block";
                    }
                }
                window.addEventListener("load", updateCarouselArrows);
                window.addEventListener("resize", updateCarouselArrows);
                document.querySelector(".sections-carousel").addEventListener("scroll", updateCarouselArrows);
                window.addEventListener("load", function() {
                    var carousel = document.querySelector(\'.sections-carousel\');
                    var badgeCard = carousel ? carousel.querySelector(\'.bloquecero-section-card .bloquecero-section-badge\') : null;
                    if (carousel && badgeCard) {
                        var card = badgeCard.closest(\'.bloquecero-section-card\');
                        if (card) {
                            // Calcula el padding-left del carrusel para ajustar el scroll
                            var leftPadding = parseInt(window.getComputedStyle(carousel).paddingLeft) || 0;
                            var scrollLeft = card.offsetLeft - leftPadding;
                            carousel.scrollTo({left: scrollLeft, behavior: \'smooth\'});
                        }
                    }
                    updateCarouselArrows();
                });
            </script>
            <!-- Fila de botones adicionales para otras secciones -->


            <script>
                function scrollCarousel(direction) {
                    var carousel = document.querySelector(".sections-carousel");
                    var card = carousel.querySelector(".section-card");
                    var scrollAmount = card ? card.offsetWidth + 18 : 240;
                    carousel.scrollBy({ left: direction * scrollAmount, behavior: "smooth" });
                    setTimeout(updateCarouselArrows, 500);
                }
                function toggleContactInfo(id) {
            // Oculta todas las fichas de profesor y resetea sus botones
            document.querySelectorAll(\'div[id^="contact-info-"]\').forEach(function(block) {
                if (block.id !== id) {
                    block.style.opacity = "0";
                    block.style.transform = "scaleY(0)";
                    block.setAttribute("aria-hidden", "true");
                    setTimeout(function() {
                        block.style.display = "none";
                    }, 300);
                }
            });
            document.querySelectorAll(\'.bloquecero-teacher-btn\').forEach(function(b) {
                if (b.getAttribute("aria-controls") !== id) {
                    b.setAttribute("aria-expanded", "false");
                }
            });
            // Activa/desactiva solo la ficha pulsada
            const contactInfo = document.getElementById(id);
            const triggerBtn = document.querySelector(\'[aria-controls="\' + id + \'"]\');
            const isHidden = contactInfo.style.display === "none" || contactInfo.style.opacity === "0";
            if (isHidden) {
                contactInfo.style.display = "block";
                contactInfo.setAttribute("aria-hidden", "false");
                if (triggerBtn) triggerBtn.setAttribute("aria-expanded", "true");
                setTimeout(() => {
                    contactInfo.style.opacity = "1";
                    contactInfo.style.transform = "scaleY(1)";
                }, 10);
            } else {
                contactInfo.style.opacity = "0";
                contactInfo.style.transform = "scaleY(0)";
                contactInfo.setAttribute("aria-hidden", "true");
                if (triggerBtn) triggerBtn.setAttribute("aria-expanded", "false");
                setTimeout(() => {
                    contactInfo.style.display = "none";
                }, 300);
            }
        }
                function toggleSectionActivities(id, btn) {
                    var content = document.getElementById(id);
                    var isCollapsed = content.style.display === "none" || content.classList.contains(\'collapsed\');
                    document.querySelectorAll(\'.section-activities\').forEach(function(div) {
                        div.style.display = "none";
                        div.classList.add(\'collapsed\');
                    });
                    document.querySelectorAll(\'.section-title-btn\').forEach(function(b) {
                        b.classList.remove(\'open\');
                    });
                    if (isCollapsed) {
                        content.style.display = "block";
                        setTimeout(function() {
                            content.classList.remove(\'collapsed\');
                        }, 10);
                        btn.classList.add(\'open\');
                    }
                }
                function updateCarouselArrows() {
                    var carousel = document.querySelector(".sections-carousel");
                    var leftArrow = document.querySelector(".carousel-btn-left");
                    var rightArrow = document.querySelector(".carousel-btn-right");
                    if (carousel.scrollLeft <= 0) {
                        leftArrow.style.display = "none";
                    } else {
                        leftArrow.style.display = "block";
                    }
                    if (carousel.scrollWidth <= carousel.clientWidth + carousel.scrollLeft) {
                        rightArrow.style.display = "none";
                    } else {
                        rightArrow.style.display = "block";
                    }
                }
                window.addEventListener(\'load\', updateCarouselArrows);
                window.addEventListener(\'resize\', updateCarouselArrows);
                document.querySelector(".sections-carousel").addEventListener(\'scroll\', updateCarouselArrows);
                // Navegación por teclado del carrusel
                var carouselContainer = document.querySelector(\'.carousel-container\');
                if (carouselContainer) {
                    carouselContainer.addEventListener(\'keydown\', function(e) {
                        if (e.key === \'ArrowLeft\') { e.preventDefault(); scrollCarousel(-1); }
                        else if (e.key === \'ArrowRight\') { e.preventDefault(); scrollCarousel(1); }
                    });
                }
                window.addEventListener("load", function() {
                    var carousel = document.querySelector(\'.sections-carousel\');
                    var badgeCard = carousel ? carousel.querySelector(\'.bloquecero-section-card .bloquecero-section-badge\') : null;
                    if (carousel && badgeCard) {
                        var card = badgeCard.closest(\'.bloquecero-section-card\');
                        var allCards = Array.from(carousel.querySelectorAll(\'.bloquecero-section-card\'));
                        var idx = allCards.indexOf(card);
                        if (card && idx > 0) {
                            var prevCard = allCards[idx - 1];
                            var leftPadding = parseInt(window.getComputedStyle(carousel).paddingLeft) || 0;
                            var scrollLeft = prevCard.offsetLeft - leftPadding;
                            carousel.scrollTo({left: scrollLeft, behavior: \'smooth\'});
                        } else if (card) {
                            // Si la marcada es la primera, deja scroll al principio
                            carousel.scrollTo({left: 0, behavior: \'smooth\'});
                        }
                    }
                    updateCarouselArrows();
                });
            function toggleSectionCard(btn) {
                // Encuentra la tarjeta de sección correspondiente
                var card = btn.closest(\'.bloquecero-section-card\');
                var preview = card.querySelector(\'.bloquecero-section-activities[data-preview="1"]\');
                var full = card.querySelector(\'.bloquecero-section-activities[data-full="1"]\');
                // Si está expandida, colapsa
                if (card.classList.contains(\'expanded\')) {
                    if (preview && full) {
                        preview.style.display = "block";
                        full.style.display = "none";
                    }
                    card.classList.remove("expanded");
                    btn.setAttribute("aria-expanded", "false");
                } else {
                    // Si está colapsada, expande
                    if (preview && full) {
                        preview.style.display = "none";
                        full.style.display = "block";
                    }
                    card.classList.add("expanded");
                    btn.setAttribute("aria-expanded", "true");
                }
            }
            </script>
        </div>
    ';

        // --- Generar contenido de bibliografía desde BD ---
        $bibliografiahtml = '';
        $bibliographies = $DB->get_records(
            'block_bloquecero_bibliography',
            ['blockinstanceid' => $this->instance->id, 'courseid' => $COURSE->id],
            'sortorder ASC'
        );

        if (!empty($bibliographies)) {
            $bibliografiahtml = '<ul style="list-style:none; padding-left:0; margin:0;">';
            foreach ($bibliographies as $entry) {
                $bookname = trim($entry->name);
                if (!empty($bookname)) {
                    $bookurl = !empty($entry->url) ? trim($entry->url) : '';
                    $bookdesc = !empty($entry->description) ? trim($entry->description) : '';

                    $bibliografiahtml .= '<li style="margin-bottom:14px; display:flex; align-items:flex-start; gap:10px;">';
                    $bibliografiahtml .= '<span style="color:#6B7D2E; font-size:1.3em; flex-shrink:0;" aria-hidden="true">📚</span>';
                    $bibliografiahtml .= '<div style="flex:1;">';

                    if (!empty($bookurl)) {
                        $bibliografiahtml .= '<a href="' . s($bookurl) . '" target="_blank" style="color:#004D35; font-weight:500; text-decoration:none; transition:color 0.14s;">' . s($bookname) . '</a>';
                    } else {
                        $bibliografiahtml .= '<span style="color:#333; font-weight:400;">' . s($bookname) . '</span>';
                    }

                    if (!empty($bookdesc)) {
                        $bibliografiahtml .= '<p style="margin:4px 0 0 0; color:#555; font-size:0.9em;">' . s($bookdesc) . '</p>';
                    }

                    $bibliografiahtml .= '</div></li>';
                }
            }
            $bibliografiahtml .= '</ul>';
        } else {
            $bibliografiahtml = '<p style="color:#595959; font-style:italic;">' . get_string('nobibliographyyet', 'block_bloquecero') . '</p>';
        }

        // Justo antes de cerrar el div principal del bloque, añade el HTML del modal:
        $this->content->text .= '
        <!-- Modal Cronograma Gantt -->
        <div id="bloquecero-gantt-modal" role="dialog" aria-modal="true" aria-labelledby="bloquecero-gantt-modal-title" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.35); align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:10px; padding:28px 24px; max-width:95vw; width:auto; max-height:90vh; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative; text-align:left; display:flex; flex-direction:column;">
                <button onclick="bloqueceroModal.close(\'bloquecero-gantt-modal\')" aria-label="' . get_string('close', 'block_bloquecero') . '" style="position:absolute; top:10px; right:14px; background:none; border:none; font-size:1.5em; color:#595959; cursor:pointer;">&times;</button>
                <h2 id="bloquecero-gantt-modal-title" style="margin-top:0; color:#004D35; font-size:1.3em; margin-bottom:16px;">' . get_string('gantt', 'block_bloquecero') . '</h2>
                <div id="bloquecero-gantt-content" style="overflow:auto; flex:1;"></div>
            </div>
        </div>
        <!-- Modal de Bibliografía -->
        <div id="bloquecero-bibliografia-modal" role="dialog" aria-modal="true" aria-labelledby="bloquecero-bibliografia-modal-title" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.35); align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:260px; max-width:90vw; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative; text-align:left;">
                <button onclick="bloqueceroModal.close(\'bloquecero-bibliografia-modal\')" aria-label="' . get_string('close', 'block_bloquecero') . '" style="position:absolute; top:10px; right:14px; background:none; border:none; font-size:1.5em; color:#595959; cursor:pointer;">&times;</button>
                <h2 id="bloquecero-bibliografia-modal-title" style="margin-top:0; color:#004D35; font-size:1.3em;">' . get_string('bibliography', 'block_bloquecero') . '</h2>
                <div id="bibliografia-content" style="margin-top:20px; max-height:60vh; overflow-y:auto;"></div>
            </div>
        </div>
        <div id="modal-sesiones-todas" role="dialog" aria-modal="true" aria-labelledby="modal-sesiones-title" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.32); align-items:center; justify-content:center;">
            <div class="bloquecero-modal-inner" style="background:#fff; border-radius:10px; padding:28px 24px; min-width:600px; max-width:90vw; width:auto; box-shadow:0 8px 32px rgba(0,0,0,0.16); position:relative; text-align:left;">
                <button onclick="bloqueceroModal.close(\'modal-sesiones-todas\')" aria-label="' . get_string('close', 'block_bloquecero') . '" style="position:absolute; top:10px; right:16px; background:none; border:none; font-size:1.3em; color:#595959; cursor:pointer;">&times;</button>
                <h2 id="modal-sesiones-title" style="margin-top:0; font-size:1.3em; color:#004D35; margin-bottom:20px;">Todas las sesiones en directo</h2>
                <div id="modal-sesiones-list" style="margin-top:20px; max-height:60vh; overflow-y:auto; overflow-x:auto;"></div>
            </div>
        </div>
        <div id="modal-actividades-todas" role="dialog" aria-modal="true" aria-labelledby="modal-actividades-title" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.32); align-items:center; justify-content:center;">
        <div class="bloquecero-modal-inner" style="background:#fff; border-radius:10px; padding:28px 24px; min-width:700px; max-width:90vw; width:auto; box-shadow:0 8px 32px rgba(0,0,0,0.16); position:relative; text-align:left;">
            <button onclick="bloqueceroModal.close(\'modal-actividades-todas\')" aria-label="' . get_string('close', 'block_bloquecero') . '" style="position:absolute; top:10px; right:16px; background:none; border:none; font-size:1.3em; color:#595959; cursor:pointer;">&times;</button>
            <h2 id="modal-actividades-title" style="margin-top:0; font-size:1.3em; color:#004D35; margin-bottom:10px;">Todas las actividades</h2>
            <div style="margin-bottom:20px;">
                <label for="filter-tipo-actividad" style="font-size:0.9em; color:#555; margin-right:10px;">Filtrar por tipo:</label>
                <select id="filter-tipo-actividad" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; font-size:0.9em;">
                    <option value="">Todas las actividades</option>
                </select>
            </div>
            <div id="modal-actividades-list" style="margin-top:20px; max-height:55vh; overflow-y:auto; overflow-x:auto;"></div>
        </div>
    </div>
    ';

        // PASAR PHP ARRAY DE SESIONES Y ACTIVIDADES A JS GLOBAL (antes del cierre del div principal)
        $this->content->text .= '
    <script>
    window.bloquecero_sesionesZoom = ' . json_encode($sesioneszoom) . ';
    window.bloquecero_activitiesData = ' . json_encode($activitiesdata) . ';
    </script>
    ';

        // Construir HTML del Gantt.
        $gantthtml = '';
        if (!empty($ganttweeks) && (!empty($ganttsections) || !empty($ganttactivities))) {
            $now = time();
            // Índice de la semana actual.
            $currentweekidx = -1;
            foreach ($ganttweeks as $idx => $wts) {
                if ($now >= $wts && $now <= $ganttweekends[$idx]) {
                    $currentweekidx = $idx;
                    break;
                }
            }

            $gantthtml .= '<div style="overflow-x:auto;">';
            $gantthtml .= '<table class="bloquecero-gantt-table">';

            // Cabecera: semanas.
            $gantthtml .= '<thead><tr><th class="bloquecero-gantt-sectioncol">' . get_string('section', 'moodle') . '</th>';
            foreach ($ganttweeks as $idx => $wts) {
                $weekend = $ganttweekends[$idx];
                $label = userdate($wts, '%d/%m') . '<br>' . userdate($weekend, '%d/%m');
                $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
                $gantthtml .= '<th class="bloquecero-gantt-weekcol' . $currentclass . '">' . $label . '</th>';
            }
            $gantthtml .= '</tr></thead>';

            // Agrupar actividades por sección.
            $activitiesbysection = [];
            foreach ($ganttactivities as $act) {
                $activitiesbysection[$act['sectionnum']][] = $act;
            }

            // Filas: secciones con sus actividades anidadas debajo.
            $gantthtml .= '<tbody>';
            foreach ($ganttallsections as $sectionnum => $sec) {
                $hasactivities = !empty($activitiesbysection[$sectionnum]);
                $hasdates = ($sec['start'] > 0 && $sec['end'] > 0);
                // Solo mostrar la sección si tiene fechas o tiene actividades con fechas.
                if (!$hasdates && !$hasactivities) {
                    continue;
                }
                // Fila de sección.
                $gantthtml .= '<tr>';
                $gantthtml .= '<td class="bloquecero-gantt-sectionname">' . htmlspecialchars($sec['name']) . '</td>';
                foreach ($ganttweeks as $idx => $wts) {
                    $weekend = $ganttweekends[$idx];
                    $active = ($hasdates && $sec['start'] <= $weekend && $sec['end'] >= $wts);
                    $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
                    $cellclass = 'bloquecero-gantt-cell' . ($active ? ' bloquecero-gantt-active' : '') . $currentclass;
                    $gantthtml .= '<td class="' . $cellclass . '"></td>';
                }
                $gantthtml .= '</tr>';
                // Filas de actividades anidadas bajo esta sección.
                if ($hasactivities) {
                    foreach ($activitiesbysection[$sectionnum] as $act) {
                        $hiddenclass = $act['hidden'] ? ' bloquecero-item-hidden' : '';
                        $gantthtml .= '<tr class="' . trim($hiddenclass) . '">';
                        $gantthtml .= '<td class="bloquecero-gantt-sectionname bloquecero-gantt-activityname">'
                            . $act['icon'] . ' ' . htmlspecialchars($act['name']) . '</td>';
                        foreach ($ganttweeks as $idx => $wts) {
                            $weekend = $ganttweekends[$idx];
                            $active = ($act['start'] <= $weekend && $act['end'] >= $wts);
                            $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
                            $cellclass = 'bloquecero-gantt-cell' . ($active ? ' bloquecero-gantt-activity' : '') . $currentclass;
                            $gantthtml .= '<td class="' . $cellclass . '"></td>';
                        }
                        $gantthtml .= '</tr>';
                    }
                }
            }

            // Fila de sesiones en directo (una sola fila con marcador por semana).
            if (!empty($sesioneszoom)) {
                $gantthtml .= '<tr>';
                $gantthtml .= '<td class="bloquecero-gantt-sectionname bloquecero-gantt-sessionrow">'
                    . get_string('livesessions', 'block_bloquecero') . '</td>';
                foreach ($ganttweeks as $idx => $wts) {
                    $weekend = $ganttweekends[$idx] + 1; // Exclusivo: inicio de la semana siguiente.
                    $currentclass = ($idx === $currentweekidx) ? ' bloquecero-gantt-currentweek' : '';
                    $weeksessions = array_filter($sesioneszoom, function ($s) use ($wts, $weekend) {
                        return (int)$s['fecha'] >= $wts && (int)$s['fecha'] < $weekend;
                    });
                    if (!empty($weeksessions)) {
                        $titles = implode('&#10;', array_map(function ($s) {
                            return htmlspecialchars($s['titulo']) . ' (' . userdate((int)$s['fecha'], '%d/%m %H:%M') . ')';
                        }, $weeksessions));
                        $count = count($weeksessions);
                        $marker = $count > 1 ? $count : '&#9679;';
                        $gantthtml .= '<td class="bloquecero-gantt-cell bloquecero-gantt-session' . $currentclass
                            . '" title="' . $titles . '" data-bs-toggle="tooltip" data-bs-placement="top">' . $marker . '</td>';
                    } else {
                        $gantthtml .= '<td class="bloquecero-gantt-cell' . $currentclass . '"></td>';
                    }
                }
                $gantthtml .= '</tr>';
            }

            $gantthtml .= '</tbody></table></div>';
        }

        $this->content->text .= '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var btn = document.getElementById("bloquecero-gantt-btn");
        var modal = document.getElementById("bloquecero-gantt-modal");
        var content = document.getElementById("bloquecero-gantt-content");
        if (btn && modal && content) {
            content.innerHTML = ' . json_encode($gantthtml) . ';
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                bloqueceroModal.open("bloquecero-gantt-modal");
            });
            modal.addEventListener("click", function(e) {
                if (e.target === modal) bloqueceroModal.close("bloquecero-gantt-modal");
            });
        }
    });
    </script>
    ';

        // Gestión accesible de modales: focus trapping, Escape key, restaurar foco
        $this->content->text .= '
    <script>
    window.bloqueceroModal = {
        activeModal: null,
        previousFocus: null,
        _escHandler: null,
        _trapHandler: null,
        open: function(modalId) {
            var modal = document.getElementById(modalId);
            if (!modal) return;
            this.previousFocus = document.activeElement;
            this.activeModal = modal;
            modal.style.display = "flex";
            var focusable = modal.querySelectorAll("button, [href], input, select, textarea");
            if (focusable.length > 0) {
                var firstEl = focusable[0];
                setTimeout(function() { firstEl.focus(); }, 50);
            }
            var self = this;
            this._escHandler = function(e) {
                if (e.key === "Escape") { self.close(modalId); }
            };
            document.addEventListener("keydown", this._escHandler);
            this._trapHandler = function(e) {
                if (e.key !== "Tab") return;
                var els = modal.querySelectorAll("button, [href], input, select, textarea");
                var first = els[0];
                var last = els[els.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                } else {
                    if (document.activeElement === last) { e.preventDefault(); first.focus(); }
                }
            };
            document.addEventListener("keydown", this._trapHandler);
        },
        close: function(modalId) {
            var modal = document.getElementById(modalId);
            if (modal) modal.style.display = "none";
            if (this._escHandler) document.removeEventListener("keydown", this._escHandler);
            if (this._trapHandler) document.removeEventListener("keydown", this._trapHandler);
            this._escHandler = null;
            this._trapHandler = null;
            this.activeModal = null;
            if (this.previousFocus) this.previousFocus.focus();
            this.previousFocus = null;
        }
    };
    </script>
    ';

        // Strings de i18n para el JS de la tabla de actividades
        $blocqueroi18n = [
        'colactivity' => get_string('colactivity', 'block_bloquecero'),
        'coltype'     => get_string('coltype', 'block_bloquecero'),
        'coldue'      => get_string('coldue', 'block_bloquecero'),
        'colstatus'   => get_string('colstatus', 'block_bloquecero'),
        'noactivities' => get_string('noactivities', 'block_bloquecero'),
        'duetoday'    => get_string('duetoday', 'block_bloquecero'),
        'duetomorrow' => get_string('duetomorrow', 'block_bloquecero'),
        'dueindays'   => get_string('dueindays', 'block_bloquecero'),
        'overduedays' => get_string('overduedays', 'block_bloquecero'),
        'submitted'   => get_string('submitted', 'block_bloquecero'),
        'graded'      => get_string('graded', 'block_bloquecero'),
        'pending'     => get_string('pending', 'block_bloquecero'),
        'daynames'    => explode(',', get_string('daynames', 'block_bloquecero')),
        'hiddenfromstudents' => get_string('hiddenfromstudents', 'moodle'),
        ];

        // Añade el script JS para el modal de sesiones fuera de cualquier echo PHP (como HTML, después del modal y antes del cierre del div)
        $this->content->text .= '<script>var bloqueceroI18n = ' . json_encode($blocqueroi18n) . ';</script>';
        $this->content->text .= '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var icon = document.querySelector(".calendario-actividades-calendaricon");
            var filterSelect = document.getElementById("filter-tipo-actividad");
            var activitiesData = window.bloquecero_activitiesData || [];

            // Función para renderizar la tabla
            function renderActivitiesTable(filterType) {
                var filteredData = filterType ? activitiesData.filter(function(a) { return a.modname === filterType; }) : activitiesData;
                var now = Math.floor(Date.now() / 1000);

                var tabla = \'<table style="width:100%;border-collapse:collapse;font-size:0.93em;">\' +
                    \'<thead><tr style="background:#f5f5f5;border-bottom:2px solid #ddd;">\' +
                    \'<th scope="col" style="padding:10px;text-align:left;font-weight:600;color:#333;">\' + bloqueceroI18n.colactivity + \'</th>\' +
                    \'<th scope="col" style="padding:10px;text-align:left;font-weight:600;color:#333;width:140px;">\' + bloqueceroI18n.coltype + \'</th>\' +
                    \'<th scope="col" style="padding:10px;text-align:left;font-weight:600;color:#333;width:120px;">\' + bloqueceroI18n.coldue + \'</th>\' +
                    \'<th scope="col" style="padding:10px;text-align:center;font-weight:600;color:#333;width:80px;">\' + bloqueceroI18n.colstatus + \'</th>\' +
                    \'</tr></thead><tbody>\';

                if (filteredData.length === 0) {
                    tabla += \'<tr><td colspan="4" style="padding:20px;text-align:center;color:#595959;">\' + bloqueceroI18n.noactivities + \'</td></tr>\';
                } else {
                    filteredData.forEach(function(activity) {
                        var daysText = "";
                        var daysColor = "#555";
                        var dueDateStr = "";
                        var estadoHTML = "";

                        if (activity.duedate) {
                            // Calcular días restantes
                            var daysRemaining = Math.ceil((activity.duedate - now) / 86400);

                            if (daysRemaining < 0) {
                                daysText = bloqueceroI18n.overduedays.replace("{n}",Math.abs(daysRemaining));
                                daysColor = "#d9534f"; // Rojo
                            } else if (daysRemaining === 0) {
                                daysText = bloqueceroI18n.duetoday;
                                daysColor = "#f0ad4e"; // Naranja
                            } else if (daysRemaining === 1) {
                                daysText = bloqueceroI18n.duetomorrow;
                                daysColor = "#f0ad4e"; // Naranja
                            } else if (daysRemaining <= 3) {
                                daysText = bloqueceroI18n.dueindays.replace("{n}",daysRemaining);
                                daysColor = "#f0ad4e"; // Naranja
                            } else if (daysRemaining <= 7) {
                                daysText = bloqueceroI18n.dueindays.replace("{n}",daysRemaining);
                                daysColor = "#5bc0de"; // Azul
                            } else {
                                daysText = bloqueceroI18n.dueindays.replace("{n}",daysRemaining);
                                daysColor = "#5cb85c"; // Verde
                            }

                            var dd = new Date(activity.duedate * 1000);
                            var dayNames = bloqueceroI18n.daynames;
                            dueDateStr = \'<br><span style="font-size:0.85em;color:#888;">\' + dd.getDate() + \' \' + dayNames[dd.getMonth()] + \' \' + dd.getFullYear() + \'</span>\';

                            // Estado de entrega (solo para actividades con fecha/seguimiento)
                            estadoHTML = activity.submitted ?
                                \'<span style="color:#5cb85c;font-weight:600;font-size:0.9em;">\' + bloqueceroI18n.submitted + \'</span>\' :
                                \'<span style="color:#f0ad4e;font-weight:600;font-size:0.9em;">\' + bloqueceroI18n.pending + \'</span>\';
                        } else {
                            // Actividad calificable sin fecha
                            daysText = \'—\';
                            daysColor = "#888";
                            estadoHTML = \'—\';
                        }

                        var hiddenBadge = activity.hidden ? \' <span style="font-size:0.75em;font-weight:600;background:#aaa;color:#fff;border-radius:3px;padding:1px 6px;margin-left:4px;">\' + bloqueceroI18n.hiddenfromstudents + \'</span>\' : \'\';
                        tabla += \'<tr style="border-bottom:1px solid #eee;transition:background 0.2s;\' + (activity.hidden ? \'opacity:0.6;\' : \'\') + \'">\' +
                            \'<td style="padding:12px;">\' +
                            \'<div style="display:flex;align-items:center;gap:8px;">\' +
                            activity.icon +
                            \'<a href="\' + activity.url + \'" style="color:#004D35;font-weight:600;text-decoration:none;">\' + activity.name + \'</a>\' +
                            hiddenBadge +
                            \'</div></td>\' +
                            \'<td style="padding:12px;color:#555;font-size:0.9em;">\' + activity.modfullname + \'</td>\' +
                            \'<td style="padding:12px;color:\' + daysColor + \';font-weight:500;font-size:0.88em;">\' + daysText + dueDateStr + \'</td>\' +
                            \'<td style="padding:12px;text-align:center;">\' + estadoHTML + \'</td>\' +
                            \'</tr>\';
                    });
                }
                tabla += \'</tbody></table>\';

                document.getElementById("modal-actividades-list").innerHTML = tabla;
            }

            // Poblar filtro con tipos únicos
            if (activitiesData.length > 0 && filterSelect) {
                var tipos = {};
                activitiesData.forEach(function(a) {
                    if (!tipos[a.modname]) {
                        tipos[a.modname] = a.modfullname;
                    }
                });
                Object.keys(tipos).forEach(function(modname) {
                    var option = document.createElement("option");
                    option.value = modname;
                    option.textContent = tipos[modname];
                    filterSelect.appendChild(option);
                });

                // Evento para filtrar
                filterSelect.addEventListener("change", function() {
                    renderActivitiesTable(this.value);
                });
            }

            // Abrir modal
            if (icon) {
                icon.addEventListener("click", function() {
                    renderActivitiesTable("");
                    bloqueceroModal.open("modal-actividades-todas");
                });
            }

            // Cerrar modal al hacer clic fuera
            var modal = document.getElementById("modal-actividades-todas");
            if (modal) {
                modal.addEventListener("click", function(e) {
                    if(e.target === modal) bloqueceroModal.close("modal-actividades-todas");
                });
            }
        });
        document.addEventListener("DOMContentLoaded", function() {
            var btn = document.getElementById("bloquecero-bibliografia-btn");
            var modal = document.getElementById("bloquecero-bibliografia-modal");
            var content = document.getElementById("bibliografia-content");

            if(btn && modal && content) {
                // Insertar el contenido de bibliografía
                content.innerHTML = ' . json_encode($bibliografiahtml) . ';

                btn.addEventListener("click", function(e){
                    e.preventDefault();
                    bloqueceroModal.open("bloquecero-bibliografia-modal");
                });

                // Cierra el modal si se hace clic fuera del contenido
                modal.addEventListener("click", function(e){
                    if(e.target === modal) bloqueceroModal.close("bloquecero-bibliografia-modal");
                });
            }
        });
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var icon = document.querySelector(".sesiones-directo-calendaricon");
        if (icon) {
            icon.addEventListener("click", function() {
                var sesiones = window.bloquecero_sesionesZoom || [];
                var tabla = \'<table style="width:100%;border-collapse:collapse;font-size:0.95em;">\' +
                    \'<thead><tr style="background:#f5f5f5;border-bottom:2px solid #ddd;">\' +
                    \'<th scope="col" style="padding:10px;text-align:left;font-weight:600;color:#333;">Sesión</th>\' +
                    \'<th scope="col" style="padding:10px;text-align:left;font-weight:600;color:#333;">Fecha y hora</th>\' +
                    \'</tr></thead><tbody>\';

                if (sesiones.length === 0) {
                    tabla += \'<tr><td colspan="2" style="padding:20px;text-align:center;color:#595959;">No hay sesiones programadas</td></tr>\';
                } else {
                    for(var i=0; i<sesiones.length; i++) {
                        var s = sesiones[i];
                        var fecha = new Date(s.fecha*1000);
                        var dateString = fecha.toLocaleDateString("es-ES", {weekday:"long", day: "2-digit", month: "long", year: "numeric"});
                        var timeString = fecha.toLocaleTimeString("es-ES", {hour: "2-digit", minute: "2-digit"});
                        var durStr = \'\';
                        if (s.duracion > 0) {
                            var h = Math.floor(s.duracion / 3600);
                            var m = Math.floor((s.duracion % 3600) / 60);
                            durStr = h > 0 ? h + \'h\' : \'\';
                            durStr += m > 0 ? (durStr ? \' \' : \'\') + m + \'min\' : \'\';
                        }
                        var descHtml = s.descripcion ? \'<div style="margin-top:8px;font-size:0.9em;color:#444;line-height:1.5;">\' + s.descripcion + \'</div>\' : \'\';

                        tabla += \'<tr style="border-bottom:1px solid #eee;transition:background 0.2s;">\' +
                            \'<td style="padding:12px;">\' +
                            \'<div style="display:flex;align-items:flex-start;gap:10px;">\' +
                            \'<svg style="flex-shrink:0;margin-top:2px;" width="18" height="18" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3" fill="#B7C65C"/><rect x="7" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="7" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/></svg>\' +
                            \'<div>\' + (s.calendarurl ? \'<a href="\' + s.calendarurl + \'" style="color:#004D35;font-weight:600;font-size:1em;text-decoration:none;">\' + s.titulo + \'</a>\' : \'<span style="color:#004D35;font-weight:600;font-size:1em;">\' + s.titulo + \'</span>\') + descHtml + \'</div>\' +
                            \'</div></td>\' +
                            \'<td style="padding:12px;color:#555;white-space:nowrap;vertical-align:top;">\' +
                            \'<div style="font-weight:500;color:#333;">\' + dateString + \'</div>\' +
                            \'<div style="font-size:0.9em;color:#666;margin-top:3px;">&#128336; \' + timeString + (durStr ? \' &nbsp;·&nbsp; &#9201; \' + durStr : \'\') + \'</div>\' +
                            \'</td>\' +
                            \'</tr>\';
                    }
                }
                tabla += \'</tbody></table>\';

                document.getElementById("modal-sesiones-list").innerHTML = tabla;
                bloqueceroModal.open("modal-sesiones-todas");
            });
        }
        // Cierra la modal si haces click fuera
        var modal = document.getElementById("modal-sesiones-todas");
        if (modal) {
            modal.addEventListener("click", function(e){
                if(e.target === modal) bloqueceroModal.close("modal-sesiones-todas");
            });
        }
    });
    </script>
    ';

        if (!$isediting) {
            // Inyecta JS para ocultar todo menos este bloque al cargar la página
            $this->page->requires->js_init_code("
                document.addEventListener('DOMContentLoaded', function() {
                    var region = document.getElementById('region-main');
                    if (region) region.style.display = 'none';

                    // Los bloques laterales siempre visibles

                    // Oculta la cabecera general (título del curso, cabecera, etc.)
                    var headerClasses = [
                        '.page-header',
                        '.page-context-header',
                        '.course-header',
                        '.page-header-headings',
                        '.page-title',
                        '.course-title'
                    ];
                    headerClasses.forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){
                            e.style.display = 'none';
                        });
                    });

                    // Oculta las tabs de navegación (Course / Settings / Participants...)
                    var tabClasses = [
                        '.nav-tabs',
                        '.nav-tabs-line',
                        '.secondary-navigation',
                        '.secondary-nav'
                    ];
                    tabClasses.forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){
                            e.style.display = 'none';
                        });
                    });
                });

                window.bloquecero_restore = function() {
                    var region = document.getElementById('region-main');
                    if (region) region.style.display = '';
                    // Los bloques laterales siempre visibles, no necesitan reset

                    // Restaurar cabecera general y título del curso
                    var headerClasses = [
                        '.page-header',
                        '.page-context-header',
                        '.course-header',
                        '.page-header-headings',
                        '.page-title',
                        '.course-title'
                    ];
                    headerClasses.forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){
                            e.style.display = '';
                        });
                    });

                    // Restaurar tabs de navegación
                    var tabClasses = [
                        '.nav-tabs',
                        '.nav-tabs-line',
                        '.secondary-navigation',
                        '.secondary-nav'
                    ];
                    tabClasses.forEach(function(selector){
                        document.querySelectorAll(selector).forEach(function(e){
                            e.style.display = '';
                        });
                    });

                    var btn = document.getElementById('bloquecero-mostrarcurso-btn');
                    if(btn) btn.style.display = 'none';
                };
            ");
                // --- Script para expandir tarjeta de sección ---
                $this->content->text .= '

        <style>
        .bloquecero-section-card { cursor: default; }
        .bloquecero-section-card li a { cursor: pointer; }
        .bloquecero-section-number { cursor: pointer; }
        .bloquecero-vermas { color: #3D7A1C; font-weight: 500; cursor: pointer; }
        .bloquecero-card-title-row { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: nowrap; width: 100%; gap: 8px; margin-bottom: 8px; min-width: 0; }
        .bloquecero-card-title-row h3 { flex: 1; min-width: 0; overflow-wrap: break-word; }
        .bloquecero-card-title-row button { flex-shrink: 0; margin-top: 2px; }
        .bloquecero-card-line { height: 3px; width: 100%; background: #B7C65C; border-radius: 2px; margin-bottom: 10px; }
        .bloquecero-completion-icon { margin-left: 6px; font-size: 0.85em; }
        .bloquecero-completion-done { color: #4CAF50; }
        .bloquecero-completion-fail { color: #E53935; }
        .bloquecero-completion-pending { color: #595959; }
        .bloquecero-completion-conditions { font-size: 0.75em; color: #595959; margin: 2px 0 4px 24px; line-height: 1.3; }
        .bloquecero-progress-wrapper { margin-top: 10px; padding-top: 8px; border-top: 1px solid #e0e0e0; }
        .bloquecero-progress-bar { height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden; }
        .bloquecero-progress-fill { height: 100%; background: #B7C65C; border-radius: 3px; transition: width 0.3s ease; }
        .bloquecero-progress-text { font-size: 0.8em; color: #555; display: block; margin-top: 4px; }
        </style>
        <style>
                    #region-main.bloquecero-fadein {
                        animation: slideDownFade 0.42s cubic-bezier(.42,0,.52,1.24);
                    }

                    @keyframes slideDownFade {
                        0% {
                            opacity: 0;
                            transform: translateY(-50px);
                        }
                        80% {
                            opacity: 1;
                            transform: translateY(8px);
                        }
                        100% {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                        /* Calendario de actividades */
        .calendario-actividades-maincard {
            min-height: 180px;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 28px 22px 22px 22px !important;
            background: #fff;
            border: 1.5px solid #E2EDE4;
            border-radius: 3px;
            box-shadow: 0 2px 14px rgba(89,157,74,0.05);
        }
        .calendario-actividades-header {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-bottom: 10px;
        }
        .calendario-actividades-header h3 {
            margin: 0;
            font-size: 1.19em;
            font-weight: 600;
            color: #0C3B2E;
            letter-spacing: 0.01em;
        }
        .week-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1em;
            font-weight: 500;
        }
        .week-selector button {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: #fff;
            color: #004D35;
            font-size: 0.95em;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: box-shadow 0.2s, transform 0.2s;
            padding: 0;
            flex-shrink: 0;
        }
        .week-selector button:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            transform: scale(1.1);
        }
        .week-selector button:focus-visible {
            outline: 3px solid #004D35;
            outline-offset: 2px;
        }
        #week-label {
            min-width: 90px;
            text-align: center;
            font-weight: 600;
            color: #004D35;
            font-size: 1em;
            letter-spacing: 0.01em;
        }
        .calendario-actividades-container {
            width: 100%;
            padding: 0;
            margin: 0;
        }
                    #activities-week-content ul {
            padding-left: 0;
            margin: 0;
            width: 100%;
            list-style: none;
        }
        #activities-week-content li {
            margin-bottom: 8px;
            font-size: 1em;
            display: block;
            color: #222;
        }
        #activities-week-content li a {
            color: #004D35;
            text-decoration: none;
            transition: color 0.14s;
        }
        #activities-week-content li:hover,
        #activities-week-content li:hover a {
            color: #6B7D2E;
        }


        .bloquecero-section-activities li a {
            color: #004D35;
            text-decoration: none;
            transition: color 0.14s;
        }
        .bloquecero-section-activities li:hover,
        .bloquecero-section-activities li:hover a {
            color: #6B7D2E;
        }
        #activities-week-content li:hover a,
        .bloquecero-section-activities li:hover a {
            text-decoration: none !important;
        }
        #modal-actividades-list table tr:hover,
        #modal-actividades-list table tr:focus-within,
        #modal-sesiones-list table tr:hover,
        #modal-sesiones-list table tr:focus-within {
            background: #f9f9f9;
        }
        @media (max-width: 660px) {
            .bloquecero-modal-inner {
                min-width: auto !important;
                width: calc(100vw - 24px) !important;
                max-width: calc(100vw - 24px) !important;
                max-height: 88vh !important;
                overflow-y: auto !important;
                padding: 16px 12px !important;
                border-radius: 8px !important;
                box-sizing: border-box;
            }
            .bloquecero-modal-inner h2 {
                font-size: 1.05em !important;
                margin-bottom: 12px !important;
                padding-right: 24px;
            }
            #modal-actividades-list,
            #modal-sesiones-list {
                max-height: 65vh !important;
                font-size: 0.82em;
            }
            #modal-actividades-list table,
            #modal-sesiones-list table {
                font-size: 0.85em;
                min-width: 480px;
            }
            #modal-actividades-list table td,
            #modal-sesiones-list table td,
            #modal-actividades-list table th,
            #modal-sesiones-list table th {
                padding: 8px 6px !important;
            }
        }
            #week-label, #sesion-label {
                        white-space: pre-line;
                        text-align: center;
                        line-height: 1.14;
                    }
                        .bloquecero-vermas-btn {
            background: none;
            border: none;
            color: #3D7A1C;
            font-weight: 500;
            cursor: pointer;
            font-size: 1em;
            padding: 0;
        }
        .bloquecero-vermas-btn:hover {
            text-decoration: underline;
            color: #004D35;
        }
        /* Gantt */
        .bloquecero-gantt-table {
            border-collapse: collapse;
            font-size: 0.82em;
            min-width: 400px;
            white-space: nowrap;
        }
        .bloquecero-gantt-table th,
        .bloquecero-gantt-table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .bloquecero-gantt-sectioncol {
            text-align: left !important;
            font-weight: 600;
            background: #f5f5f5;
            min-width: 140px;
            position: sticky;
            left: 0;
            z-index: 1;
        }
        .bloquecero-gantt-sectionname {
            text-align: left !important;
            background: #fff;
            position: sticky;
            left: 0;
            z-index: 1;
            max-width: 180px;
            white-space: normal;
            font-weight: 500;
        }
        .bloquecero-gantt-weekcol {
            min-width: 52px;
            font-weight: 500;
            background: #f5f5f5;
            line-height: 1.2;
        }
        .bloquecero-gantt-cell {
            min-width: 52px;
        }
        .bloquecero-gantt-active {
            background: #6B7D2E;
        }
        .bloquecero-gantt-activity {
            background: #B8860B;
        }
        .bloquecero-gantt-currentweek {
            background: #e8f5e9 !important;
        }
        .bloquecero-gantt-active.bloquecero-gantt-currentweek {
            background: #4a5c1a !important;
        }
        .bloquecero-gantt-activity.bloquecero-gantt-currentweek {
            background: #8B6008 !important;
        }
        .bloquecero-gantt-activityname {
            font-weight: 400;
            font-size: 0.9em;
        }
        .bloquecero-gantt-session {
            background: #1565C0;
            color: #fff;
            font-size: 0.85em;
            cursor: default;
        }
        .bloquecero-gantt-session.bloquecero-gantt-currentweek {
            background: #0D47A1 !important;
        }
        .bloquecero-gantt-sessionrow {
            font-style: italic;
            color: #1565C0;
        }
                    </style>
        ';
        }

        // phpcs:enable moodle.Files.LineLength.MaxExceeded
        return $this->content;
    }

    /**
     * Return the applicable page formats for this block.
     *
     * @return array Page format => allowed.
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'course-view-weeks' => true,
            'course-view-topics' => true,
            'my' => false,
            'site' => false,
            'mod' => false,
            'admin' => false,
            'all' => false,
        ];
    }

    /**
     * This block has a settings page.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Hide the block header.
     *
     * @return bool
     */
    public function hide_header() {
        return true;
    }
}
