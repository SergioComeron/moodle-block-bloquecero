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
 * TODO describe file block_bloquecero
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/course/format/weeks/lib.php');

// Función para obtener la fecha de inicio de un cm según su tipo
function get_cm_start_date($cm) {
    global $DB;
    $time = 0;
    switch ($cm->modname) {
        case 'assign':
            // En asignaciones, se usa allowsubmissionsfromdate
            $assignment = $DB->get_record('assign', array('id' => $cm->instance), 'allowsubmissionsfromdate', MUST_EXIST);
            $time = $assignment->allowsubmissionsfromdate;
            break;
        case 'quiz':
            // En cuestionarios, se usa timeopen
            $quiz = $DB->get_record('quiz', array('id' => $cm->instance), 'timeopen', MUST_EXIST);
            $time = $quiz->timeopen;
            break;
        // Agregar otros casos según el tipo de actividad
        default:
            // Si no se define fecha de inicio para ese tipo, se deja en 0 o se puede devolver NULL
            $time = 0;
            break;
    }
    return $time;
}

class block_bloquecero extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_bloquecero');
    }

    public function get_content() {
        global $COURSE, $DB, $USER, $CFG, $OUTPUT, $PAGE;

        // Oculta el bloque si no estamos en la página principal del curso (ni en weeks ni en topics)
        $pagetype = $PAGE->pagetype;
        if ($pagetype !== 'course-view-weeks' && $pagetype !== 'course-view-topics' && $pagetype !== 'course-view') {
            return null;
        }
        
        $is_editing = $PAGE->user_is_editing();

        if ($this->content !== null) {
            return $this->content;
        }
    
        $this->content = new stdClass;

        // Ejemplo: array de profesores. En la práctica, recupera los profesores según el curso.
        $teachers = array(
            (object)[
                'id'       => 1,
                'fullname' => 'Prof. Sinesio Delgado',
                'email'    => 'sinesio.delgado@udima.es',
                'phone'    => '+34 911896994 - Extensión 3563',
                'schedule' => '<ul>
                                    <li>Lunes y Martes de 17:00 a 19:00 h.</li>
                                    <li>Miércoles y Jueves de 12:00 a 14:00 h.</li>
                               </ul>'
            ],
            (object)[
                'id'       => 2,
                'fullname' => 'Prof. María López',
                'email'    => 'maria.lopez@udima.es',
                'phone'    => '+34 911000111 - Extensión 1234',
                'schedule' => '<ul>
                                    <li>Martes y Miércoles de 10:00 a 12:00 h.</li>
                                    <li>Viernes de 15:00 a 17:00 h.</li>
                               </ul>'
            ]
        );

        $coursecontext = context_course::instance($COURSE->id);
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
        $teachersP = array();
        foreach ($teachersraw as $teacher) {
            // Recupera el teléfono y horario personalizados si existen en la configuración del bloque
            $phonekey = 'userphone_' . $teacher->id;
            $schedulekey = 'userschedule_' . $teacher->id;
            $phone = isset($this->config->$phonekey) ? $this->config->$phonekey : (isset($teacher->phone1) ? $teacher->phone1 : '');
            $schedulekey = 'userschedule_' . $teacher->id;
            $schedule = '';
            if (isset($this->config->$schedulekey)) {
                if (is_array($this->config->$schedulekey) && isset($this->config->{$schedulekey}['text'])) {
                    $schedule = $this->config->{$schedulekey}['text'];
                } else {
                    $schedule = $this->config->$schedulekey;
                }
            } else {
                $schedule = '<ul><li>Horarios no disponibles</li></ul>';
            }
            $teachersP[] = (object)[
                'id'          => $teacher->id,
                'fullname'    => fullname($teacher),
                'email'       => $teacher->email,
                'phone'       => $phone,
                'picturehtml' => $OUTPUT->user_picture($teacher, array('size' => 100)),
                'schedule'    => $schedule
            ];
        }

        // Obtener el número máximo de profesores a mostrar desde la configuración del bloque
        $maxteachers = 3;
        if (!empty($this->config->maxteachers) && is_numeric($this->config->maxteachers)) {
            $maxteachers = (int)$this->config->maxteachers;
        }

        // Limitar el array de profesores a mostrar según config_maxteachers
        $teachersP = array_slice($teachersP, 0, $maxteachers);

        // URLs de los foros y demás secciones
        $forum_anuncios_url = '#';
        if (!empty($this->config->forumid)) {
            $forum_anuncios_url = new moodle_url('/mod/forum/view.php', array('id' => $this->config->forumid));
        }
        $forum_tutorias_url = '#';
        if (!empty($this->config->forumtutoriasid)) {
            $forum_tutorias_url = new moodle_url('/mod/forum/view.php', array('id' => $this->config->forumtutoriasid));
        }
        $forum_estudiantes_url = new moodle_url('/mod/forum/view.php', array('id' => $this->config->forumestudiantesid));
        $guide_url = !empty($this->config->guide_url) ? $this->config->guide_url : '#';
        // $bibliography_url = !empty($this->config->bibliography_url) ? $this->config->bibliography_url : '#';

        $zoom_url = new moodle_url('/path/to/zoom');
        $tasks_url = new moodle_url('/path/to/tasks');

        $togglebuttonhtml = '';
        if (!$is_editing) {
            $togglebuttonhtml = '
                <div class="moodle-toggle-centering">
                    <button id="bloquecero-mostrarcurso-btn"
                        type="button"
                        onclick="event.preventDefault(); window.bloquecero_toggle()"
                        class="moodle-toggle-btn"
                        title="Mostrar u ocultar curso">
                        <span class="moodle-toggle-circle">
                            <svg id="bloquecero-mostrarcurso-icon" class="moodle-toggle-chevron" width="24" height="24" viewBox="0 0 24 24">
                                <polyline points="9 6 15 12 9 18" fill="none" stroke="#1655A0" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span id="bloquecero-mostrarcurso-text" class="moodle-toggle-label">mostrar curso</span>
                    </button>
                    </div>';
        }
        
        if ($is_editing) {
            $PAGE->requires->js_init_code("
                document.addEventListener('DOMContentLoaded', function() {
                    var region = document.getElementById('region-main');
                    if (region) region.style.display = '';
                    document.querySelectorAll('.block').forEach(function(b){
                        b.style.display = '';
                    });
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



        /// URLs de las imágenes
        $context = context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_bloquecero', 'header_bg', 0, 'itemid, filepath, filename', false);

        // Si hay archivos, usa el primero como imagen de fondo.
        if (!empty($files)) {
            $file = reset($files);
            $fondo_cabecera_img = moodle_url::make_pluginfile_url(
                $context->id, 'block_bloquecero', 'header_bg', 0, $file->get_filepath(), $file->get_filename()
            );
        } else {
            $fondo_cabecera_img = new moodle_url('/path/to/default.jpg');
        }
        // Generar botones de contacto para cada profesor (lista separada por comas).
        $teachersList = array();
        $contactBlocksHtml = '';
        foreach ($teachersP as $teacher) {
            $uniqueId = 'contact-info-' . $teacher->id;
            $teachersList[] = '
                <button class="bloquecero-teacher-btn" type="button" onclick="toggleContactInfo(\'' . $uniqueId . '\')">
                    <span>' . format_string($teacher->fullname) . '</span>
                </button>';

            // Bloque de información de contacto para este profesor (oculto por defecto).
            $contactBlocksHtml .= '
                <div id="' . $uniqueId . '" style="
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
                    <p><strong>Teléfono:</strong> ' . $teacher->phone . '</p>
                    <p><strong>Horario:</strong></p>' . $teacher->schedule . '
                </div>';
        }

        // Unir los botones con comas
        $contactButtonsHtml = implode(', ', $teachersList);

        // Inicializar array para almacenar las actividades de cada sección (clave: id de la sección)
        $sectionsActivitiesData = array();

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
            if ($todaysection < 1) $todaysection = 1;
        }

        $section_cards = [];
        $sectioncount = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) continue;
            if (!$section->uservisible) continue;
            $sectionurl = new moodle_url('/course/view.php', ['id' => $COURSE->id, 'section' => $section->section]);
            $course = $modinfo->get_course();
            if ($course->format == 'weeks' && empty($section->name)) {
                $startdate = $course->startdate;
                $weekduration = 7 * 24 * 60 * 60;
                $sectionstart = $startdate + (($section->section - 1) * $weekduration);
                $sectionend = $sectionstart + $weekduration;
                if (!empty($course->enddate) && $sectionend > $course->enddate) {
                    $sectionend = $course->enddate;
                }
                $sectiontitle = userdate($sectionstart, get_string('strftimedateshort')) . ' - ' . userdate($sectionend - 1, get_string('strftimedateshort'));
            } else {
                $sectiontitle = format_string($section->name ?: get_string('section', 'moodle') . ' ' . $section->section);
            }

            // --- NUEVO BLOQUE: preview/expand actividades sección ---
            $maxactivities = !empty($this->config->maxactivitiespersection) && is_numeric($this->config->maxactivitiespersection)
                ? (int)$this->config->maxactivitiespersection
                : 3; // Valor por defecto si no está configurado
            $visibleactivities = 0;
            $totalactivities = 0;
            $all_activities_array = [];

            if (!empty($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) continue;
                    $icon = $OUTPUT->pix_icon('icon', $cm->modfullname, $cm->modname, ['class' => 'activityicon']);
                    $all_activities_array[] = '<li>' . $icon . ' <a href="' . $cm->url . '">' . format_string($cm->name) . '</a></li>';
                    $visibleactivities++;
                    $totalactivities++;
                }
            }
            // Generar previews y full lists
            $activities_preview = array_slice($all_activities_array, 0, $maxactivities);
            $remaining = count($all_activities_array) - $maxactivities;
            if ($remaining > 0) {
                // $activities_preview[] = '<li class="bloquecero-vermas">+' . $remaining . ' más</li>';
                    $activities_preview[] = '<li class="bloquecero-vermas"><button type="button" class="bloquecero-vermas-btn" onclick="toggleSectionCard(this)">+' . $remaining . ' más</button></li>';
                $all_activities_array[] = '<li class="bloquecero-vermas"><button type="button" class="bloquecero-vermas-btn" onclick="toggleSectionCard(this)">mostrar menos</button></li>';

            }
            $activitieslist = '<ul class="bloquecero-section-activities" data-preview="1" style="margin: 12px 0 0 0; padding-left: 0; list-style: none;">' . implode('', $activities_preview) . '</ul>';
            $activitieslist_full = '<ul class="bloquecero-section-activities" data-full="1" style="margin: 12px 0 0 0; padding-left: 0; list-style: none; display:none;">' . implode('', $all_activities_array) . '</ul>';
            if (!$all_activities_array) {
                $activitieslist = '<div style="margin-top:12px; color:#888; font-size:0.95em;" class="bloquecero-section-activities" data-preview="1">' . get_string('noactivities', 'block_bloquecero') . '</div>';
                $activitieslist_full = '<div style="margin-top:12px; color:#888; font-size:0.95em; display:none;" class="bloquecero-section-activities" data-full="1">' . get_string('noactivities', 'block_bloquecero') . '</div>';
            }

            // Guardar el contenido HTML de las actividades para esta sección con un id único
            $sectionid = 'section-activities-' . $sectioncount;
            $sectionsActivitiesData[$sectionid] = $activitieslist_full;

            // Colores alternos por índice de tarjeta
            $tarjeta_colores = [
                ['bg' => '#F2F5F3', 'linea' => '#B7C65C'], // gris verdoso claro
                ['bg' => '#F8FBED', 'linea' => '#B7C65C'], // verde clarito
                ['bg' => '#FAFAFA', 'linea' => '#B7C65C'], // blanco-gris
            ];
            $tarjeta_idx = $sectioncount % count($tarjeta_colores);
            $bg_color = $tarjeta_colores[$tarjeta_idx]['bg'];
            $line_color = $tarjeta_colores[$tarjeta_idx]['linea'];

            // Badge destacado si corresponde
            $badge = null;
            if ($format === 'weeks' && $section->section == $todaysection) {
                $badge = 'Actual';
            } else if ($format === 'topics' && $section->section == $highlightedsection) {
                $badge = 'Destacada';
            }

            // Construir la tarjeta como string (con badge si corresponde)
            $card_html = '<div class="bloquecero-section-card" style="background: '.$bg_color.'">';
            $card_html .= '
                <div class="bloquecero-section-header-flex" style="display:flex;align-items:center;justify-content:space-between;width:100%;gap:12px;margin-bottom:8px;">
                    <div class="bloquecero-section-number" style="flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'. $sectiontitle .'</div>'
                    . ($badge ? '<span class="bloquecero-section-badge">' . $badge . '</span>' : '') . '
                </div>
                <div class="bloquecero-section-line" style="background: '.$line_color.'; margin-bottom:12px;"></div>
                <div class="bloquecero-section-content">
                    '.$activitieslist.'
                    '.$activitieslist_full.'
                </div>
            </div>';
            
            // Añadir todas las tarjetas al mismo array, sin separar.
            $section_cards[] = $card_html;
            $sectioncount++;
        }
        $sectionscarousel .= implode('', $section_cards);
        $sectionscarousel .= '</div>';

        // Envolver el carrusel en un contenedor con botones laterales
        $carouselContainer = '
            <div class="carousel-container" style="position: relative; display: flex; align-items: center; margin-bottom: 20px; padding: 0 40px;">
                 <button class="carousel-btn carousel-btn-left" onclick="scrollCarousel(-1)" style="background: transparent; border: none; color: #004D35; font-size: 1.5em; padding: 0; cursor: pointer; position: absolute; left: 0; z-index: 2; height: 100%;">&lt;</button>
                 ' . $sectionscarousel . '
                 <button class="carousel-btn carousel-btn-right" onclick="scrollCarousel(1)" style="background: transparent; border: none; color: #004D35; font-size: 1.5em; padding: 0; cursor: pointer; position: absolute; right: 0; z-index: 2; height: 100%;">&gt;</button>
            </div>
        ';

        // Bloque vacío para mostrar las actividades de la sección seleccionada (oculto inicialmente)
        $activitiesBlockHtml = '<div id="section-activities-container" style="margin: 20px 40px; text-align: left; font-size: 0.9em; color: #333; border: 1px solid #ddd; border-radius: 3px; padding: 15px; background-color: #f9f9f9; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); display: none;"></div>';

        // Inyectar la definición del array de actividades en JavaScript
        $sectionsActivitiesJson = json_encode($sectionsActivitiesData);

        $courseDates = '';
        if (!empty($COURSE->startdate)) {
            $courseDates = userdate($COURSE->startdate, get_string('strftimedateshort'));
            if (!empty($COURSE->enddate)) {
                $courseDates .= ' - ' . userdate($COURSE->enddate, get_string('strftimedateshort'));
            }
        }

        // Bloque para mostrar el Calendario de actividades (mismo ancho que el carrusel)
        $calendarActivities = '';
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $startdate = get_cm_start_date($cm);
            if ($startdate) {
                $activitytime = userdate($startdate, get_string('strftimedateshort'));
                $icon = $OUTPUT->pix_icon('icon', $cm->modfullname, $cm->modname, ['class' => 'activityicon']);
                $calendarActivities .= '<li data-timestamp="' . $startdate . '" style="margin-bottom: 6px;">' . $icon .
                    ' <a href="' . $cm->url . '">' .
                    format_string($cm->name) . '</a> <span style="font-size:0.9em; color:#666;">(Inicio: ' . $activitytime . ')</span></li>';
            }
        }
        if ($calendarActivities) {
            $calendarActivities = '<ul id="activities-list" style="margin: 12px 0 0 0; padding-left: 18px; list-style: none;">' 
                . $calendarActivities . '</ul>';
        } else {
            $calendarActivities = '<div style="margin-top:12px; color:#888; font-size:0.95em;">' . 
                get_string('noactivities', 'block_bloquecero') . '</div>';
        }

        // --- Calcular el selector de semanas usando las fechas del curso ---
        $courseStart = $COURSE->startdate;
        $courseEnd = (!empty($COURSE->enddate)) ? $COURSE->enddate : time();
        $weeks = ceil(($courseEnd - $courseStart) / (7 * 24 * 60 * 60));
        $options = '';
        for ($i = 1; $i <= $weeks; $i++) {
            $options .= '<option value="' . $i . '">Semana ' . $i . '</option>';
        }

        // --- SESIONES EN DIRECTO ---
        $sesionesZoom = [
            ['titulo' => 'Clase inaugural', 'fecha' => strtotime('+2 days 18:00'), 'url' => 'https://zoom.us/j/123456789'],
            ['titulo' => 'Repaso Tema 1', 'fecha' => strtotime('+5 days 17:00'), 'url' => 'https://zoom.us/j/987654321'],
            ['titulo' => 'Consultas generales', 'fecha' => strtotime('+12 days 19:00'), 'url' => 'https://zoom.us/j/112233445'],
            ['titulo' => 'Resolución ejercicios', 'fecha' => strtotime('+20 days 16:30'), 'url' => 'https://zoom.us/j/556677889'],
        ];
        $sesionesZoomList = '';
        foreach ($sesionesZoom as $sesion) {
            $fecha = userdate($sesion['fecha'], get_string('strftimedaydatetime', 'langconfig'));
            $sesionesZoomList .= '<li data-timestamp="' . $sesion['fecha'] . '" style="margin-bottom: 6px;">' .
                $OUTPUT->pix_icon('i/calendar', '', '', ['class' => 'activityicon']) .
                ' <a href="' . $sesion['url'] . '" target="_blank">' . format_string($sesion['titulo']) . '</a> <span style="font-size:0.93em; color:#666;">(' . $fecha . ')</span></li>';
        }
        $sesionesZoomList = '<ul id="sesiones-list" style="margin: 12px 0 0 0; padding-left: 18px; list-style: none;">' . $sesionesZoomList . '</ul>';

        // --- NUEVA estructura de la tarjeta de calendario de actividades ---
        $calendarioActividades = '
<div class="udima-maincard calendario-actividades-maincard">
    <div class="calendario-actividades-header">
        <h3>Calendario de actividades</h3>
        <div class="week-selector">
            <button id="prev-week">&lt;</button>
            <span id="week-label"></span>
            <button id="next-week">&gt;</button>
        </div>
    </div>
    <div class="calendario-actividades-container">
        <div id="activities-week-content"></div>
    </div>
</div>
<script>
(function(){
    const courseStart = ' . $courseStart . ';
    const totalWeeks = ' . $weeks . ';
    const now = Math.floor(Date.now() / 1000);
    let currentWeek = Math.floor((now - courseStart) / (7 * 24 * 60 * 60)) + 1;
    if (currentWeek < 1) {
        currentWeek = 1;
    } else if (currentWeek > totalWeeks) {
        currentWeek = totalWeeks;
    }
    // El listado original de actividades
    const originalListHTML = ' . json_encode($calendarActivities) . ';
    const contentContainer = document.getElementById("activities-week-content");
    const weekLabel = document.getElementById("week-label");
    const prevBtn = document.getElementById("prev-week");
    const nextBtn = document.getElementById("next-week");
    function formatDate(ts) {
        const d = new Date(ts * 1000);
        const day = d.getDate();
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        return day + " " + monthNames[d.getMonth()];
    }
    function filterActivities(week) {
        const weekStart = courseStart + (week - 1) * 7 * 24 * 60 * 60;
        const weekEnd = weekStart + 7 * 24 * 60 * 60;
        // Parseamos el HTML original
        const parser = new DOMParser();
        const doc = parser.parseFromString(originalListHTML, "text/html");
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
                contentContainer.innerHTML = "<ul>" + ul.innerHTML + "</ul>";
            } else {
                contentContainer.innerHTML = \'<div style="margin-top:12px; color:#888; font-size:0.95em; text-align:center;">No hay actividades para esta semana.</div>\';
            }
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
})();
</script>
';

        // --- SESIONES EN DIRECTO: genera el bloque con selector de semana ---
        // Calcular semanas para las sesiones (según fechas simuladas)
        $sesionesStart = $sesionesZoom[0]['fecha'];
        $sesionesEnd = $sesionesZoom[count($sesionesZoom)-1]['fecha'];
        $sesionesWeeks = ceil(($sesionesEnd - $courseStart) / (7 * 24 * 60 * 60));
        if ($sesionesWeeks < 1) $sesionesWeeks = 1;
        // Usar el mismo rango de semanas que el curso para coherencia
        $sesionesDirecto = '
<div class="udima-maincard sesiones-directo-maincard">
    <div class="sesiones-directo-header">
        <h3>Sesiones en directo</h3>
        <div class="sesiones-directo-selector">
            <button id="prev-sesion">&lt;</button>
            <span id="sesion-label"></span>
            <button id="next-sesion">&gt;</button>
        </div>
        <span class="sesiones-directo-calendaricon" title="Ver todas las sesiones">
            <!-- Icono SVG calendario -->
            <svg width="22" height="22" viewBox="0 0 24 24" style="vertical-align:middle;cursor:pointer;"><rect x="3" y="5" width="18" height="16" rx="3" fill="#B7C65C" /><rect x="7" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="7" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/></svg>
        </span>
    </div>
    <div class="sesiones-directo-container">
        <div id="sesiones-list-content"></div>
    </div>
</div>
<script>
(function(){
    const courseStart = ' . $courseStart . ';
    const totalWeeks = ' . $weeks . ';
    const now = Math.floor(Date.now() / 1000);
    let currentWeek = Math.floor((now - courseStart) / (7 * 24 * 60 * 60)) + 1;
    if (currentWeek < 1) {
        currentWeek = 1;
    } else if (currentWeek > totalWeeks) {
        currentWeek = totalWeeks;
    }
    // El listado original de sesiones
    const originalSesionesHTML = ' . json_encode($sesionesZoomList) . ';
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
                sesionesContainer.innerHTML = \'<div style="margin-top:12px; color:#888; font-size:0.95em; text-align:center;">No hay sesiones para esta semana.</div>\';
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
})();
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
            <div class="udima-menu-bar">
    <a href="' . new moodle_url('/grade/report/grader/index.php', array('id' => $COURSE->id)) . '" class="udima-menu-link">
        ' . $OUTPUT->pix_icon('t/grades', '', 'moodle', ['class' => 'menu-icon']) . '
        <span>Calificaciones</span>
    </a>
    <a href="' . new moodle_url('/user/index.php', array('id' => $COURSE->id)) . '" class="udima-menu-link">
        ' . $OUTPUT->pix_icon('i/users', '', 'moodle', ['class' => 'menu-icon']) . '
        <span>Participantes</span>
    </a>
    <a href="#" id="bloquecero-bibliografia-btn" class="udima-menu-link">
        ' . $OUTPUT->pix_icon('book', '', 'moodle', ['class' => 'menu-icon']) . '
        <span>Bibliografía</span>
    </a>
    <a href="' . $guide_url . '" class="udima-menu-link" target="_blank">
        ' . $OUTPUT->pix_icon('i/info', '', 'moodle', ['class' => 'menu-icon']) . '
        <span>Guía docente</span>
    </a>' . ((has_capability('moodle/course:update', $coursecontext)) ? '
    <a href="' . (new moodle_url('/course/edit.php', array('id' => $COURSE->id))) . '" class="udima-menu-link">
        ' . $OUTPUT->pix_icon('i/settings', '', 'moodle', ['class' => 'menu-icon']) . '
        <span>Configuración</span>
    </a>' : '') .
'</div>
            <div style="padding: 0 20px; font-family: Arial, sans-serif;">
    <!-- Resto del contenido del bloque -->
    <div class="bloquecero-header-responsive">
        <img src="' . $fondo_cabecera_img . '" alt="Fondo" class="bloquecero-header-bg-img">
        <div class="bloquecero-header-content">
            <h1 class="bloquecero-header-title">' . format_string($COURSE->fullname) . '</h1>
            ' . ($courseDates ? '<p class="bloquecero-header-dates">' . $courseDates . '</p>' : '') . '
            <p class="bloquecero-header-teachers">Equipo docente: ' . $contactButtonsHtml . '</p>
        </div>
    </div>
    <!-- Bloques de información de contacto de cada profesor -->
    ' . $contactBlocksHtml . '
    

    <!-- Sección de foros y demás secciones -->
    <div style="padding: 0 40px;">
        <div class="bloquecero-tabs">
            <a href="' . $forum_anuncios_url . '" class="bloquecero-tab">Tablón de anuncios</a>
            <a href="' . $forum_tutorias_url . '" class="bloquecero-tab">Foro de Tutorías</a>
            <a href="' . $forum_estudiantes_url . '" class="bloquecero-tab">Foro de Estudiantes</a>
        </div>
    </div>
                <!-- Bloques divididos en dos columnas -->
<div class="bloquecero-maincards-row">
    <div style="width: 50%; box-sizing: border-box;">
        ' . $sesionesDirecto . '
    </div>
    <div style="width: 50%; box-sizing: border-box;">
        ' . $calendarioActividades . '
    </div>
</div>
        <!-- Carrusel de tarjetas de secciones -->
        <div style="text-align: left; padding: 0 40px; margin-bottom: 10px;">
<h3 style="color: #004D35; margin-top: 0;">Secciones del curso</h3>
</div>' .
$carouselContainer . '
        <!-- Bloque para mostrar las actividades de la sección seleccionada -->
        ' . $activitiesBlockHtml . '

            <style>
@media (max-width: 900px) {
  .udima-maincard, .sesiones-directo-maincard {
    padding: 18px 10px !important;
    font-size: 0.97em;
    min-width: 0;
  }
  .calendario-actividades-header,
  .sesiones-directo-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }
  .calendario-actividades-header h3,
  .sesiones-directo-header h3 {
    font-size: 1.07em;
    margin-bottom: 2px;
  }
  .week-selector,
  .sesiones-directo-selector {
    margin-left: 0 !important;
    font-size: 0.95em;
    max-width: 100%;
    white-space: nowrap;
  }
}
@media (max-width: 660px) {
  .bloquecero-maincards-row {
    flex-direction: column !important;
    gap: 10px !important;
  }
  .bloquecero-maincards-row > div {
    width: 100% !important;
    margin-bottom: 14px;
  }
  .udima-maincard,
  .sesiones-directo-maincard {
    font-size: 0.96em;
    padding: 12px 4px !important;
    min-width: 0;
  }
  .calendario-actividades-header,
  .sesiones-directo-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    width: 100%;
  }
  .calendario-actividades-header h3,
  .sesiones-directo-header h3 {
    font-size: 0.99em;
    margin-bottom: 2px;
  }
  .week-selector,
  .sesiones-directo-selector {
    font-size: 0.94em;
    margin-left: 0 !important;
    max-width: 100%;
    white-space: nowrap;
  }
}
@media (max-width: 500px) {
  .calendario-actividades-header h3,
  .sesiones-directo-header h3 {
    font-size: 0.92em;
    margin-bottom: 0;
    white-space: normal;
  }
  .week-selector,
  .sesiones-directo-selector {
    font-size: 0.92em;
    margin-left: 0 !important;
    max-width: 100%;
    white-space: normal;
  }
}
.bloquecero-maincards-row {
  display: flex;
  gap: 20px;
  margin: 20px 40px;
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
        if(btn) btn.classList.add(\'open\');
        if(btntext) btntext.innerHTML = \'ocultar curso\';
        if (region) {
            region.style.display = \'\';
            region.classList.remove(\'bloquecero-fadein\');
            setTimeout(function() {
                region.classList.add(\'bloquecero-fadein\');
            }, 10);
        }
        document.querySelectorAll(\'.block\').forEach(function(b){
            b.style.display = \'\';
        });
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
        if(btntext) btntext.innerHTML = \'ocultar curso\';
    } else {
        if(btn) btn.classList.remove(\'open\');
        if(btntext) btntext.innerHTML = \'mostrar curso\';
        if (region) {
            region.style.display = \'none\';
            region.classList.remove(\'bloquecero-fadein\');
        }
        document.querySelectorAll(\'.block\').forEach(function(b){
            if (!b.classList.contains(\'block_bloquecero\')) b.style.display = \'none\';
        });
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
        if(btntext) btntext.innerHTML = \'mostrar curso\';
    }
};
// Al cargar, oculta el curso y ajusta el botón
document.addEventListener(\'DOMContentLoaded\', function() {
    var region = document.getElementById(\'region-main\');
    var btnicon = document.getElementById(\'bloquecero-mostrarcurso-icon\');
    var btntext = document.getElementById(\'bloquecero-mostrarcurso-text\');
    if (region) region.style.display = \'none\';
    document.querySelectorAll(\'.block\').forEach(function(b){
        if (!b.classList.contains(\'block_bloquecero\')) b.style.display = \'none\';
    });
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
    if(btntext) btntext.innerHTML = \'Mostrar curso\';
});
</script>
            <style>
            .bloquecero-section-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 12px;
            margin-bottom: 8px;
        }
        .bloquecero-section-number {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
            .bloquecero-section-badge {
                position: static !important;
                margin-left: 10px;
                flex-shrink: 0;
                top: auto;
                right: auto;
                top: 10px;
                background: #B7C65C;
                color: #fff;
                font-weight: 600;
                font-size: 1em;
                border-radius: 16px;
                padding: 2px 16px;
                z-index: 4;
                box-shadow: 0 2px 8px rgba(183,198,92,0.14);
                letter-spacing: 0.01em;
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
                    color: #B7C65C !important;
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
                    color: #B7C65C !important;
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
                }
                .udima-maincard:hover {
                    border-color: #B7C65C !important;
                    box-shadow: 0 8px 32px rgba(183,198,92,0.11);
                    background: #F7FAF6 !important;
                }
                .udima-menu-bar {
                    display: flex;
                    gap: 28px;
                    justify-content: flex-end;
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
                    color: #6FA24A;
                }
                .udima-menu-link:hover,
                .udima-menu-link:focus {
                    color: #B7C65C;
                    border-bottom: 2.5px solid #B7C65C;
                    outline: none;
                    background: none;
                }
                .udima-menu-link:active {
                    color: #004D35;
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
    font-size: 0.9em;
    font-weight: 400;
    color: #B7C65C;
    flex: none;
    letter-spacing: 0.05em;
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
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                min-width: 0;
                width: 100%;
                margin-bottom: 10px;
                flex-wrap: nowrap;
            }
            .calendario-actividades-header h3 {
                margin: 0;
                font-size: 1.19em;
                font-weight: 600;
                color: #0C3B2E;
                letter-spacing: 0.01em;
                white-space: nowrap;
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
                align-items: center;
                justify-content: space-between;
                flex-wrap: nowrap;
                gap: 8px;
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
                white-space: nowrap;
                min-width: 0;
            }
            /* .week-selector y .sesiones-directo-selector: regla conjunta arriba */
            .sesiones-directo-selector button,
            .week-selector button {
                background: none;
                border: none;
                font-size: 1.2em;
                cursor: pointer;
                color: #004D35;
                padding: 2px 10px;
                border-radius: 5px;
                transition: background 0.18s, color 0.13s;
            }
            .sesiones-directo-selector button:hover,
            .week-selector button:hover {
                background: #B7C65C;
                color: #fff;
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
                color: #B7C65C;
            }
            #sesiones-list-content li:hover a {
                text-decoration: none !important;
            }
            /* Forzar tamaño de fuente en spans y botones de week-selector y sesiones-directo-selector */
            .week-selector span,
            .sesiones-directo-selector span {
                font-size: 0.93em !important;
            }
            .week-selector button,
            .sesiones-directo-selector button {
                font-size: 0.93em !important;
            }
            </style>
            <style>
            .bloquecero-header-responsive {
            .calendario-actividades-header .week-selector,
            .sesiones-directo-header .sesiones-directo-selector {
                font-size: 0.93em !important;
            }
            .calendario-actividades-maincard,
            .sesiones-directo-maincard {
                font-size: 1em !important;
                background: rgba(255, 0, 0, 0.84);  // solo para depuración visual, puedes quitarlo después */
            }
            .calendario-actividades-header .week-selector,
            .sesiones-directo-header .sesiones-directo-selector {
                font-size: 1em !important;
            }
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
                font-size: 2.5em !important;
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
            .bloquecero-header-teachers {
                margin: 0 0 10px 0;
                font-size: 1.2em;
                color: black;
                font-weight: 500;
            }

            @media (max-width: 800px) {
                .bloquecero-header-content {
                    align-items: flex-start;
                    padding: 12px 10px 10px 10px;
                }
                .bloquecero-header-title {
                    font-size: 1.35em !important;
                    line-height: 1.18;
                    margin-bottom: 5px;
                }
                .bloquecero-header-dates {
                    font-size: 0.92em;
                    margin-bottom: 5px;
                }
                .bloquecero-header-teachers {
                    font-size: 1em;
                    margin-bottom: 5px;
                }
                .bloquecero-header-responsive {
                    aspect-ratio: 2.2 / 1;
                    min-height: 84px;
                }
            }

            @media (max-width: 540px) {
                .bloquecero-header-title {
                    font-size: 1em !important;
                }
                .bloquecero-header-content {
                    padding: 6px 4px;
                }
                .bloquecero-header-responsive {
                    min-height: 48px;
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
            }
            .bloquecero-teacher-btn:hover,
            .bloquecero-teacher-btn:focus {
                color: #B7C65C;
                text-decoration: underline;
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
    color: #B7C65C;
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
    cursor: pointer;
    transition: filter 0.17s;
}
.sesiones-directo-calendaricon:hover {
    filter: brightness(1.13) drop-shadow(0 1px 5px #B7C65C33);
}
            </style>
            
            <script>
                // Datos con las actividades de cada sección (clave: id de la sección)
                const sectionsActivitiesData = ' . $sectionsActivitiesJson . ';

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
    // Oculta todas las fichas de profesor
    document.querySelectorAll(\'div[id^="contact-info-"]\').forEach(function(block) {
        if (block.id !== id) {
            block.style.opacity = "0";
            block.style.transform = "scaleY(0)";
            setTimeout(function() {
                block.style.display = "none";
            }, 300);
        }
    });
    // Activa/desactiva solo la ficha pulsada
    const contactInfo = document.getElementById(id);
    const isHidden = contactInfo.style.display === "none" || contactInfo.style.opacity === "0";
    if (isHidden) {
        contactInfo.style.display = "block";
        setTimeout(() => {
            contactInfo.style.opacity = "1";
            contactInfo.style.transform = "scaleY(1)";
        }, 10);
    } else {
        contactInfo.style.opacity = "0";
        contactInfo.style.transform = "scaleY(0)";
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
    } else {
        // Si está colapsada, expande
        if (preview && full) {
            preview.style.display = "none";
            full.style.display = "block";
        }
        card.classList.add("expanded");
    }
}
            </script>
        </div>
    ';

    // Justo antes de cerrar el div principal del bloque, añade el HTML del modal:
    $this->content->text .= '
    <!-- Modal de Bibliografía -->
    <div id="bloquecero-bibliografia-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.35); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:260px; max-width:90vw; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative; text-align:center;">
            <button onclick="document.getElementById(\'bloquecero-bibliografia-modal\').style.display=\'none\'" style="position:absolute; top:10px; right:14px; background:none; border:none; font-size:1.5em; color:#888; cursor:pointer;">&times;</button>
            <h2 style="margin-top:0;">Bibliography</h2>
        </div>
    </div>
    <div id="modal-sesiones-todas" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.32); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; padding:28px 24px; min-width:300px; max-width:92vw; box-shadow:0 8px 32px rgba(0,0,0,0.16); position:relative; text-align:left;">
            <button onclick="document.getElementById(\'modal-sesiones-todas\').style.display=\'none\'" style="position:absolute; top:10px; right:16px; background:none; border:none; font-size:1.3em; color:#999; cursor:pointer;">&times;</button>
            <h2 style="margin-top:0; font-size:1.15em;">Todas las sesiones en directo</h2>
            <div id="modal-sesiones-list" style="margin-top:20px; max-height:50vh; overflow-y:auto;"></div>
        </div>
    </div>
    ';

    // PASAR PHP ARRAY DE SESIONES A JS GLOBAL (antes del cierre del div principal)
    $this->content->text .= '
    <script>
    window.bloquecero_sesionesZoom = ' . json_encode($sesionesZoom) . ';
    </script>
    ';

    // Añade el script JS para el modal de sesiones fuera de cualquier echo PHP (como HTML, después del modal y antes del cierre del div)
    $this->content->text .= '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var btn = document.getElementById("bloquecero-bibliografia-btn");
        var modal = document.getElementById("bloquecero-bibliografia-modal");
        if(btn && modal) {
            btn.addEventListener("click", function(e){
                e.preventDefault();
                modal.style.display = "flex";
            });
            // Cierra el modal si se hace clic fuera del contenido
            modal.addEventListener("click", function(e){
                if(e.target === modal) modal.style.display = "none";
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
                var todas = "";
                for(var i=0; i<sesiones.length; i++) {
                    var fecha = new Date(sesiones[i].fecha*1000);
                    var dateString = fecha.toLocaleDateString() + " " + fecha.toLocaleTimeString().slice(0,5);
                    todas += \'<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">\' +
                        \'<svg width="18" height="18" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3" fill="#B7C65C"/><rect x="7" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="9" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="7" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="11" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/><rect x="15" y="13" width="2.5" height="2.5" rx="1" fill="#fff"/></svg>\' +
                        \'<a href="\' + sesiones[i].url + \'" target="_blank" style="color:#004D35;font-weight:600;">\' + sesiones[i].titulo + \'</a>\' +
                        \'<span style="color:#666;font-size:0.96em;">(\' + dateString + \')</span></div>\';
                }
                document.getElementById("modal-sesiones-list").innerHTML = todas;
                document.getElementById("modal-sesiones-todas").style.display = "flex";
            });
        }
        // Cierra la modal si haces click fuera
        var modal = document.getElementById("modal-sesiones-todas");
        if (modal) {
            modal.addEventListener("click", function(e){
                if(e.target === modal) modal.style.display = "none";
            });
        }
    });
    </script>
    ';

if (!$is_editing) {
    // Inyecta JS para ocultar todo menos este bloque al cargar la página
    $PAGE->requires->js_init_code("
        document.addEventListener(\'DOMContentLoaded\', function() {
            var region = document.getElementById(\'region-main\');
            if (region) region.style.display = \'none\';

            // Oculta todos los bloques menos el tuyo
            document.querySelectorAll(\'.block\').forEach(function(b){
                if (!b.classList.contains(\'block_bloquecero\')) b.style.display = \'none\';
            });

            // Oculta la cabecera general (título del curso, cabecera, etc.)
            var headerClasses = [
                \'.page-header\',
                \'.page-context-header\',
                \'.course-header\',
                \'.page-header-headings\',
                \'.page-title\',
                \'.course-title\'
            ];
            headerClasses.forEach(function(selector){
                document.querySelectorAll(selector).forEach(function(e){
                    e.style.display = \'none\';
                });
            });

            // Oculta las tabs de navegación (Course / Settings / Participants...)
            var tabClasses = [
                \'.nav-tabs\',
                \'.nav-tabs-line\',
                \'.secondary-navigation\',
                \'.secondary-nav\'
            ];
            tabClasses.forEach(function(selector){
                document.querySelectorAll(selector).forEach(function(e){
                    e.style.display = \'none\';
                });
            });
        });

        window.bloquecero_restore = function() {
            var region = document.getElementById(\'region-main\');
            if (region) region.style.display = \'\';
            document.querySelectorAll(\'.block\').forEach(function(b){
                b.style.display = \'\';
            });

            // Restaurar cabecera general y título del curso
            var headerClasses = [
                \'.page-header\',
                \'.page-context-header\',
                \'.course-header\',
                \'.page-header-headings\',
                \'.page-title\',
                \'.course-title\'
            ];
            headerClasses.forEach(function(selector){
                document.querySelectorAll(selector).forEach(function(e){
                    e.style.display = \'\';
                });
            });

            // Restaurar tabs de navegación
            var tabClasses = [
                \'.nav-tabs\',
                \'.nav-tabs-line\',
                \'.secondary-navigation\',
                \'.secondary-nav\'
            ];
            tabClasses.forEach(function(selector){
                document.querySelectorAll(selector).forEach(function(e){
                    e.style.display = \'\';
                });
            });

            var btn = document.getElementById(\'bloquecero-mostrarcurso-btn\');
            if(btn) btn.style.display = \'none\';
        };
    ");
    // --- Script para expandir tarjeta de sección ---
        $this->content->text .= '
<script>
function expandSectionCard(card) {
    if (card.classList.contains("expanded")) return;
    var full = card.querySelector(\'.bloquecero-section-activities[data-full="1"]\');
    var preview = card.querySelector(\'.bloquecero-section-activities[data-preview="1"]\');
    if (full && preview) {
        preview.style.display = "none";
        full.style.display = "block";
    }
    card.classList.add("expanded");
}
</script>
<style>
.bloquecero-section-card { cursor: pointer; }
.bloquecero-vermas { color: #6FA24A; font-weight: 500; cursor: pointer; }
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
    align-items: center;
    justify-content: space-between;
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
    background: none;
    border: none;
    font-size: 1.2em;
    cursor: pointer;
    color: #004D35;
    padding: 2px 10px;
    border-radius: 5px;
    transition: background 0.18s, color 0.13s;
}
.week-selector button:hover {
    background: #B7C65C;
    color: #fff;
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
    display: flex;
    align-items: center;
    color: #222;
}
#activities-week-content li a {
    color: #004D35;
    text-decoration: none;
    transition: color 0.14s;
}
#activities-week-content li:hover,
#activities-week-content li:hover a {
    color: #B7C65C;
}


.bloquecero-section-activities li a {
    color: #004D35;
    text-decoration: none;
    transition: color 0.14s;
}
.bloquecero-section-activities li:hover,
.bloquecero-section-activities li:hover a {
    color: #B7C65C;
}
#activities-week-content li:hover a,
.bloquecero-section-activities li:hover a {
    text-decoration: none !important;
}
    #week-label, #sesion-label {
                white-space: pre-line;
                text-align: center;
                line-height: 1.14;
            }
                .bloquecero-vermas-btn {
    background: none;
    border: none;
    color: #6FA24A;
    font-weight: 500;
    cursor: pointer;
    font-size: 1em;
    padding: 0;
}
.bloquecero-vermas-btn:hover {
    text-decoration: underline;
    color: #004D35;
}
            </style>
';
}

        return $this->content;
    }

    public function applicable_formats() {
        return array(
            'course-view' => true,
            'course-view-weeks' => true,
            'course-view-topics' => true,
            'my' => false,
            'site' => false,
            'mod' => false,
            'admin' => false,
            'all' => false
        );
    }

    public function has_config() {
        return true;
    }

    public function hide_header() {
        return true;
    }
}

            
            