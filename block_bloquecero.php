<?php
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
                </style>
            </div>

            <script>
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
                // Otras funciones (toggleZoom, toggleActivities, etc.) se mantienen sin cambios.
                function toggleActivities() { /* ... */ }
                function toggleZoom() { /* ... */ }
            </script>
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