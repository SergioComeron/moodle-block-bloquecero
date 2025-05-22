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
        $fondo_cabecera_img = new moodle_url('/blocks/bloquecero/pix/header_bg2.png');

        // Generar botones de contacto para cada profesor y sus respectivas secciones ocultas.
        $contactButtonsHtml = '';
        $contactBlocksHtml = '';
        foreach ($teachersP as $teacher) {
            $uniqueId = 'contact-info-' . $teacher->id;
            // Botón ovalado con el nombre del profesor.
            $contactButtonsHtml .= '
                <button style="
                    padding: 10px 20px;
                    background-color: #004D35;
                    color: white;
                    border: none;
                    border-radius: 50px;
                    cursor: pointer;
                    font-size: 1em;
                    transition: background-color 0.3s ease;
                    margin: 5px;
                " 
                onmouseover="this.style.backgroundColor=\'#00593D\';"
                onmouseout="this.style.backgroundColor=\'#004D35\';"
                onclick="toggleContactInfo(\'' . $uniqueId . '\')">
                    ' . format_string($teacher->fullname) . '
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

        // Carrusel de tarjetas de secciones
        $sectionscarousel = '<div class="sections-carousel">';
        $modinfo = get_fast_modinfo($COURSE);
        $sectioncount = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) continue;
            if (!$section->uservisible) continue;

            $course = $modinfo->get_course();
            $sectiontitle = '';

            // Si el formato es "weeks" y la sección NO tiene nombre personalizado
            if ($course->format == 'weeks' && empty($section->name)) {
                $startdate = $course->startdate;
                $weekduration = 7 * 24 * 60 * 60; // 7 días en segundos
                $sectionstart = $startdate + (($section->section - 1) * $weekduration);
                $sectionend = $sectionstart + $weekduration;

                // Si hay fecha de fin de curso y nos pasamos, la ajustamos (opcional)
                if (!empty($course->enddate) && $sectionend > $course->enddate) {
                    $sectionend = $course->enddate;
                }

                $sectiontitle = userdate($sectionstart, get_string('strftimedateshort')) . ' - ' . userdate($sectionend - 1, get_string('strftimedateshort'));
            } else {
                // Si tiene nombre personalizado o NO es formato weeks
                $sectiontitle = format_string($section->name ?: get_string('section', 'moodle') . ' ' . $section->section);
            }

            // Recoge las actividades visibles de la sección
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
                $activities = '<ul style="margin: 12px 0 0 0; padding-left: 18px;">' . $activities . '</ul>';
            } else {
                $activities = '<div style="margin-top:12px; color:#888; font-size:0.95em;">'.get_string('noactivities', 'block_bloquecero').'</div>';
            }

            $sectionid = 'section-activities-' . $sectioncount;
            $isactive = !empty($section->current);
            $activeclass = $isactive ? ' active-section' : '';
            $activesymbol = $isactive ? ' <span title="Sección activa" style="color:#1abc9c;">&#11088;</span>' : '';
            $sectionscarousel .= '<div class="section-card' . $activeclass . '" style="flex-direction: column; padding:0;">
                <button class="section-title-btn section-title-header" type="button" onclick="toggleSectionActivities(\'' . $sectionid . '\', this)">
                    <span class="section-title-text">' . $sectiontitle . $activesymbol . '</span>
                    <span class="section-arrow" style="color: #004D35;">&#9654;</span>
                </button>
                <div id="' . $sectionid . '" class="section-activities collapsed" style="margin:0 16px 8px 16px; display:none;">'
                    . $activities .
                '</div>
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

        // HTML principal del bloque
        $this->content->text = '
            <div style="padding: 0 20px; font-family: Arial, sans-serif;">
               <!-- Cabecera -->
                <div style="position: relative; border-radius: 12px; overflow: hidden; margin-bottom: 20px; width: 100%; aspect-ratio: 3 / 1;">
                    <img src="' . $fondo_cabecera_img . '" alt="Fondo" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 30px; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-start;">
                        <h1 style="margin: 0 0 10px 0; font-size: 2.5em; color: black;">' . format_string($COURSE->fullname) . '</h1>
                        <p style="margin: 0 0 10px 0; font-size: 1.2em; color: black;">Equipo docente</p>
                        <!-- Botones de contacto para cada profesor -->
                        <div style="display: flex; flex-wrap: wrap;">' . $contactButtonsHtml . '</div>
                    </div>
                </div>
                <!-- Bloques de información de contacto de cada profesor -->
                ' . $contactBlocksHtml . '
                

                
                <!-- Sección de foros y demás secciones -->
                <div style="display: flex; flex-direction: row; justify-content: center; gap: 20px; margin: 20px 0;">
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
                            position: relative;
                            padding: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <h3 style="margin: 0; font-size: 1.2em; position: relative; z-index: 1;">Foro de Estudiantes</h3>
                        </div>
                    </a>
                </div>
                
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

/* Elimina subrayado en todos los estados posibles */
.ghost-button:visited,
.ghost-button:focus,
.ghost-button:hover,
.ghost-button:active {
    text-decoration: none !important;
    color: #004D35;
}

/* Opcional: refuerza el pointer en todos los estados */
.ghost-button:focus,
.ghost-button:hover,
.ghost-button:active {
    cursor: pointer;
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
.sections-carousel {
    display: flex;
    flex-direction: row;
    gap: 18px;
    overflow-x: auto;
    padding: 10px 0 20px 0;
    margin-bottom: 10px;
    scrollbar-width: none; /* Oculta scrollbar en Firefox */
    -ms-overflow-style: none;  /* IE 10+ */
    scroll-snap-type: x mandatory; /* <-- Añadido */
}
.sections-carousel::-webkit-scrollbar {
    display: none; /* Oculta scrollbar en Chrome, Safari y Opera */
}
.section-card {
    min-width: 220px;
    max-width: 260px;
    background: #fff;
    border: 1.5px solid #004D35;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    color: #004D35;
    font-weight: 600;
    font-size: 0.9em;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    transition: box-shadow 0.2s;
    flex-shrink: 0;
    cursor: pointer;
    padding: 0;
    overflow: hidden;
    scroll-snap-align: start; /* <-- Añadido */
}
.section-card.active-section {
    border: 2.5px solid #1abc9c;
    box-shadow: 0 4px 16px rgba(26,188,156,0.12);
}
.section-title-header {
    background: #004D35 !important;
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
                
                <!-- Fila de botones adicionales para otras secciones -->
                <div style="display: flex; justify-content: center; gap: 20px; margin: 20px 0;">
                    <a href="' . new moodle_url('/grade/report/user/index.php', array('id' => $COURSE->id)) . '" class="ghost-button">
                        ' . $OUTPUT->pix_icon('t/grades', '', 'moodle', array('class' => 'bigicon')) . '
                        <span>' . get_string('calificador', 'block_bloquecero') . '</span>
                    </a>
                    <a href="' . new moodle_url('/user/index.php', array('id' => $COURSE->id)) . '" class="ghost-button">
                        ' . $OUTPUT->pix_icon('i/users', '', 'moodle', array('class' => 'bigicon')) . '
                        <span>' . get_string('participantes', 'block_bloquecero') . '</span>
                    </a>
                    <a href="' . new moodle_url('/#', array('id' => $COURSE->id)) . '" class="ghost-button">
                        ' . $OUTPUT->pix_icon('book', '', 'moodle', array('class' => 'bigicon')) . '
                        <span>' . get_string('bibliografiarecomendada', 'block_bloquecero') . '</span>
                    </a>
                </div>

                                <!-- Carrusel de tarjetas de secciones -->
                ' . $carouselContainer . '

                <script>
                    function scrollCarousel(direction) {
                        var carousel = document.querySelector(".sections-carousel");
                        var card = carousel.querySelector(".section-card");
                        var scrollAmount = card ? card.offsetWidth + 18 : 240; // 18 es el gap
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
                        // Cierra todos los demás
                        document.querySelectorAll(\'.section-activities\').forEach(function(div) {
                            div.style.display = "none";
                            div.classList.add(\'collapsed\');
                        });
                        document.querySelectorAll(\'.section-title-btn\').forEach(function(b) {
                            b.classList.remove(\'open\');
                        });
                        // Abre el seleccionado si estaba cerrado
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
                        // Si no hay scroll a la izquierda, oculta la flecha izquierda
                        if (carousel.scrollLeft <= 0) {
                            leftArrow.style.display = "none";
                        } else {
                            leftArrow.style.display = "block";
                        }
                        // Si no hay contenido a la derecha, oculta la flecha derecha
                        if (carousel.scrollWidth <= carousel.clientWidth + carousel.scrollLeft) {
                            rightArrow.style.display = "none";
                        } else {
                            rightArrow.style.display = "block";
                        }
                    }
                    // Actualiza las flechas al cargar la página y al redimensionar la ventana
                    window.addEventListener(\'load\', updateCarouselArrows);
                    window.addEventListener(\'resize\', updateCarouselArrows);
                    // Actualiza las flechas al hacer scroll en el carrusel
                    document.querySelector(".sections-carousel").addEventListener(\'scroll\', updateCarouselArrows);

                    window.addEventListener(\'load\', function() {
                        var carousel = document.querySelector(\'.sections-carousel\');
                        var active = carousel ? carousel.querySelector(\'.section-card.active-section\') : null;
                        if (carousel && active) {
                            // Calcula el scroll para centrar la tarjeta activa
                            var carouselRect = carousel.getBoundingClientRect();
                            var activeRect = active.getBoundingClientRect();
                            var scrollLeft = active.offsetLeft - (carousel.clientWidth / 2) + (active.clientWidth / 2);

                            // Si es la primera, que quede a la izquierda
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