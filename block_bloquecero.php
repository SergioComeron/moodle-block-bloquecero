<?php
require_once($CFG->dirroot.'/course/format/weeks/lib.php');

class block_bloquecero extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_bloquecero');
    }

    public function get_content() {
        global $COURSE, $DB, $USER, $CFG, $OUTPUT;

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
            $teachersP[] = (object)[
                'id'       => $teacher->id,
                'fullname' => fullname($teacher),
                'email'    => $teacher->email,
                'phone'    => isset($teacher->phone1) ? $teacher->phone1 : '',
                'picturehtml' => $OUTPUT->user_picture($teacher, array('size' => 100)),
                'schedule' => '<ul><li>Horarios no disponibles</li></ul>'
            ];
        }

        // URLs de los foros y demás secciones
        $forum_anuncios_url = new moodle_url('/mod/forum/view.php', array('id' => 64));
        $forum_tutorias_url = new moodle_url('/mod/forum/view.php', array('id' => 64));
        $forum_estudiantes_url = new moodle_url('/mod/forum/view.php', array('id' => 64));
        $guide_url = new moodle_url('/path/to/guide');
        $bibliography_url = new moodle_url('/path/to/bibliography');
        $zoom_url = new moodle_url('/path/to/zoom');
        $tasks_url = new moodle_url('/path/to/tasks');

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
                <button style="
                    background: none;
                    border: none;
                    color: #004D35;
                    font-size: 1em;
                    cursor: pointer;
                    padding: 0;
                    margin: 0;
                    display: inline-flex;
                    align-items: center;
                    transition: color 0.3s ease;
                " onmouseover="this.style.color=\'#1abc9c\'" onmouseout="this.style.color=\'#004D35\'" onclick="toggleContactInfo(\'' . $uniqueId . '\')">
                    <span>' . format_string($teacher->fullname) . '</span>
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 22px;
                        height: 22px;
                        margin-left: 6px;
                        background: #1abc9c;
                        color: #fff;
                        border-radius: 50%;
                        font-size: 0.85em;
                    ">i</span>
                </button>';

            // Bloque de información de contacto para este profesor (oculto por defecto).
            $contactBlocksHtml .= '
                <div id="' . $uniqueId . '" style="
                    display: none;
                    margin-top: 10px;
                    text-align: left;
                    font-size: 0.9em;
                    color: #333;
                    border: 1px solid #ddd;
                    border-radius: 8px;
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
        $sectioncount = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) continue;
            if (!$section->uservisible) continue;

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

            // Generar las actividades de la sección (guardarlas para mostrarlas en el bloque aparte)
            $activities = '';
            if (!empty($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) continue;
                    $icon = $OUTPUT->pix_icon('icon', $cm->modfullname, $cm->modname, ['class' => 'activityicon']);
                    $activities .= '<li style="margin-bottom: 6px;">' . $icon . ' <a href="' . $cm->url . '" style="color:#004D35;text-decoration:none;">' . format_string($cm->name) . '</a></li>';
                }
            }
            if ($activities) {
                $activities = '<ul style="margin: 12px 0 0 0; padding-left: 18px; list-style: none;">' . $activities . '</ul>';
            } else {
                $activities = '<div style="margin-top:12px; color:#888; font-size:0.95em;">' . get_string('noactivities', 'block_bloquecero') . '</div>';
            }
            
            // Generar el enlace "Abrir sección" (sin apariencia de botón)
            $sectionurl = new moodle_url('/course/view.php', array('id' => $COURSE->id, 'section' => $section->section));
            // Crear un encabezado que muestre el nombre (o fechas) de la sección a la izquierda y el enlace a la derecha
            $header = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">'
                        . '<span style="font-weight: bold; color: #004D35;">' . $sectiontitle . '</span>'
                        . '<a href="' . $sectionurl . '" style="color: #004D35; text-decoration: none; font-weight: bold; font-size: 0.9em;">'
                            . get_string('abrirseccion', 'block_bloquecero')
                        . '</a>'
                    . '</div>';
            
            // Colocar el encabezado (con el título y el enlace) encima del listado de actividades
            $activities = $header . $activities;
            
            // Guardar el contenido HTML de las actividades para esta sección con un id único
            $sectionid = 'section-activities-' . $sectioncount;
            $sectionsActivitiesData[$sectionid] = $activities;

            // Se muestra en el carrusel sólo el botón con el título de la sección
            $isactive = !empty($section->current);
            $activesymbol = $isactive ? ' <span title="Sección activa" style="color:#1abc9c;">&#11088;</span>' : '';
            $sectionscarousel .= '<div class="section-card' . ($isactive ? ' active-section' : '') . '" style="flex-direction: column; padding:0;">
                <button class="section-title-btn section-title-header" type="button" onclick="showSectionActivities(\'' . $sectionid . '\', this)">
                    <span class="section-title-text">' . $sectiontitle . $activesymbol . '</span>
                    <span class="section-arrow" style="color: #004D35;">&#9654;</span>
                </button>
            </div>';
            $sectioncount++;
        }
        $sectionscarousel .= '</div>';

        // Envolver el carrusel en un contenedor con botones laterales
        $carouselContainer = '
            <div class="carousel-container" style="position: relative; display: flex; align-items: center; margin-bottom: 20px; padding: 0 40px;">
                 <button class="carousel-btn carousel-btn-left" onclick="scrollCarousel(-1)" style="background: transparent; border: none; color: #004D35; font-size: 1.5em; padding: 0; cursor: pointer; position: absolute; left: 0; z-index: 2; height: 100%;">&#9664;</button>
                 ' . $sectionscarousel . '
                 <button class="carousel-btn carousel-btn-right" onclick="scrollCarousel(1)" style="background: transparent; border: none; color: #004D35; font-size: 1.5em; padding: 0; cursor: pointer; position: absolute; right: 0; z-index: 2; height: 100%;">&#9654;</button>
            </div>';

        // Bloque vacío para mostrar las actividades de la sección seleccionada (oculto inicialmente)
        $activitiesBlockHtml = '<div id="section-activities-container" style="margin-top: 20px; text-align: left; font-size: 0.9em; color: #333; border: 1px solid #ddd; border-radius: 8px; padding: 15px; background-color: #f9f9f9; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); display: none;"></div>';

        // Inyectar la definición del array de actividades en JavaScript
        $sectionsActivitiesJson = json_encode($sectionsActivitiesData);

        $courseDates = '';
        if (!empty($COURSE->startdate)) {
            $courseDates = userdate($COURSE->startdate, get_string('strftimedateshort'));
            if (!empty($COURSE->enddate)) {
                $courseDates .= ' - ' . userdate($COURSE->enddate, get_string('strftimedateshort'));
            }
        }
        // HTML principal del bloque (se añade debajo del carrusel el bloque para las actividades)
        $this->content->text = '
            <div style="padding: 0 20px; font-family: Arial, sans-serif;">
               <!-- Cabecera -->
                <div style="position: relative; border-radius: 12px; overflow: hidden; margin-bottom: 20px; width: 100%; aspect-ratio: 3 / 1;">
                    <img src="' . $fondo_cabecera_img . '" alt="Fondo" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 30px; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-start;">
                        <h1 style="margin: 0 0 10px 0; font-size: 2.5em; color: black;">' . format_string($COURSE->fullname) . '</h1>
                        ' . ($courseDates ? '<p style="margin: 0 0 10px 0; font-size: 1em; color: black;">' . $courseDates . '</p>' : '') . '
                        <p style="margin: 0 0 10px 0; font-size: 1.2em; color: black;">
        Equipo docente: ' . $contactButtonsHtml . '
    </p>
                    </div>
                </div>
                <!-- Bloques de información de contacto de cada profesor -->
                ' . $contactBlocksHtml . '
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin: 20px 0;">
                    <a href="' . new moodle_url('/grade/report/user/index.php', array('id' => $COURSE->id)) . '" class="round-button" title="' . get_string('calificador', 'block_bloquecero') . '">
                       ' . $OUTPUT->pix_icon('t/grades', '', 'moodle', ['class' => 'bigicon']) . '
                       <span class="button-text">' . get_string('calificador', 'block_bloquecero') . '</span>
                    </a>
                    <a href="' . new moodle_url('/user/index.php', array('id' => $COURSE->id)) . '" class="round-button" title="' . get_string('participantes', 'block_bloquecero') . '">
                       ' . $OUTPUT->pix_icon('i/users', '', 'moodle', ['class' => 'bigicon']) . '
                       <span class="button-text">' . get_string('participantes', 'block_bloquecero') . '</span>
                    </a>
                    <a href="' . new moodle_url('/#', array('id' => $COURSE->id)) . '" class="round-button" title="' . get_string('bibliografiarecomendada', 'block_bloquecero') . '">
                       ' . $OUTPUT->pix_icon('book', '', 'moodle', ['class' => 'bigicon']) . '
                       <span class="button-text">' . get_string('bibliografiarecomendada', 'block_bloquecero') . '</span>
                    </a>
                </div>
                <style>
                    .round-button {
                        position: relative;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 40px; /* en lugar de width fija */
                        height: 40px;
                        padding: 0 5px; /* padding base */
                        background: #fff;
                        color: #004D35;
                        border: 2px solid #004D35;
                        border-radius: 50%;
                        text-decoration: none;
                        overflow: hidden;
                        transition: all 0.3s ease;
                        white-space: nowrap;
                    }
                    .round-button .bigicon {
                        transition: transform 0.3s ease;
                    }
                    .round-button:hover .bigicon {
                        transform: scale(1.1);
                    }
                    .round-button .button-text {
                        position: absolute;
                        right: -100%;
                        opacity: 0;
                        padding: 0 10px;
                        font-size: 0.9em;
                        transition: right 0.3s ease, opacity 0.3s ease;
                        color: #004D35;
                        background: #fff;
                        height: 100%;
                        display: flex;
                        align-items: center;
                    }
                    .round-button:hover {
                        width: auto;  /* Permite que el botón se expanda */
                        padding-right: 10px;
                    }
                    .round-button:hover .button-text {
                        right: 0;
                        opacity: 1;
                    }
                </style>
                <!-- Sección de foros y demás secciones -->
                <div style="padding: 0 40px;">
                    <div style="display: flex; justify-content: center; gap: 20px; margin: 20px 0;">
                        <!-- Tablón de anuncios -->
                        <a href="' . $forum_anuncios_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                            <div class="forum-card" style="
                                border: 1px solid #ddd;
                                border-radius: 8px;
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                                text-align: center;
                                background-color: #004D35;
                                color: white;
                                transition: transform 0.3s ease, box-shadow 0.3s ease;
                                position: relative;
                                padding: 10px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <h3 style="margin: 0; font-size: 1.2em; position: relative; z-index: 1;">Tablón de anuncios</h3>
                            </div>
                        </a>
                        <!-- Foro de Tutorías -->
                        <a href="' . $forum_tutorias_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                            <div class="forum-card" style="
                                border: 1px solid #ddd;
                                border-radius: 8px;
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                                text-align: center;
                                background-color: #004D35;
                                color: white;
                                transition: transform 0.3s ease, box-shadow 0.3s ease;
                                position: relative;
                                padding: 10px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <h3 style="margin: 0; font-size: 1.2em; position: relative; z-index: 1;">Foro de Tutorías</h3>
                            </div>
                        </a>
                        <!-- Foro de Estudiantes -->
                        <a href="' . $forum_estudiantes_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                            <div class="forum-card" style="
                                border: 1px solid #ddd;
                                border-radius: 8px;
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                                text-align: center;
                                background-color: #004D35;
                                color: white;
                                transition: transform 0.3s ease, box-shadow 0.3s ease;
                                padding: 10px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <h3 style="margin: 0; font-size: 1.2em; position: relative; z-index: 1;">Foro de Estudiantes</h3>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Carrusel de tarjetas de secciones -->
                ' . $carouselContainer . '
                <!-- Bloque para mostrar las actividades de la sección seleccionada -->
                ' . $activitiesBlockHtml . '
                <style>
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
                        border-radius: 8px;
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
                    }
                    /* Ocultar scrollbar en Chrome, Safari y Opera */
                    .sections-carousel::-webkit-scrollbar {
                        display: none;
                    }
                    /* Calculamos el ancho para que siempre quepan 4 tarjetas dejando 3 gaps de 18px (54px total) */
                    .section-card {
                        flex: 0 0 calc((100% - 54px) / 4);
                        max-width: calc((100% - 54px) / 4);
                        min-width: calc((100% - 54px) / 4);
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
                        box-shadow: 0 4px 16px rgba(26,188,156,0.12);
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
                    .section-title-header .section-title-text {
                        color: #fff !important;
                        font-weight: 600;
                        font-size: 1em;
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
                        // Si se pulsa la misma sección que ya está activa y el bloque es visible, se oculta
                        if (lastSectionShown === sectionId && container.style.display === "block") {
                            container.innerHTML = "";
                            container.style.display = "none";
                            lastSectionShown = null;
                        } else {
                            if (sectionsActivitiesData[sectionId]) {
                                container.innerHTML = sectionsActivitiesData[sectionId];
                                container.style.display = "block";
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
                        var carousel = document.querySelector(".sections-carousel");
                        var active = carousel ? carousel.querySelector(".section-card.active-section") : null;
                        if (carousel && active) {
                            var scrollLeft = active.offsetLeft - (carousel.clientWidth / 2) + (active.clientWidth / 2);
                            if (active === carousel.firstElementChild) {
                                scrollLeft = 0;
                            }
                            carousel.scrollLeft = scrollLeft;
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
                        const contactInfo = document.getElementById(id);
                        if (contactInfo.style.display === "none" || contactInfo.style.opacity === "0") {
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
                    window.addEventListener(\'load\', function() {
                        var carousel = document.querySelector(\'.sections-carousel\');
                        var active = carousel ? carousel.querySelector(\'.section-card.active-section\') : null;
                        if (carousel && active) {
                            var scrollLeft = active.offsetLeft - (carousel.clientWidth / 2) + (active.clientWidth / 2);
                            if (active === carousel.firstElementChild) {
                                scrollLeft = 0;
                            }
                            carousel.scrollLeft = scrollLeft;
                        }
                        updateCarouselArrows();
                    });
                </script>
            </div>
        ';
    
        return $this->content;
    }

    public function applicable_formats() {
        return array("site" => true, "course" => true, "my" => true);
    }    

    public function has_config() {
        return true;
    }

    public function hide_header() {
        return true;
    }
}