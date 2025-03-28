<?php
class block_bloquecero extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_bloquecero');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
    
        $this->content = new stdClass;

        // URL del foro de anuncios
        // $forum_anuncios_url = new moodle_url('/mod/forum/view.php', ['id' => 1]);
        $forum_anuncios_url = 'http://localhost/stable_404/mod/forum/view.php?id=64';

        // URL del foro de tutorías
        $forum_tutorias_url = 'http://localhost/stable_404/mod/forum/view.php?id=64';

        // URL del foro de estudiantes
        $forum_estudiantes_url = 'http://localhost/stable_404/mod/forum/view.php?id=64';

        // URLs de las secciones
        $guide_url = new moodle_url('/path/to/guide'); // Cambia esta URL
        $bibliography_url = new moodle_url('/path/to/bibliography'); // Cambia esta URL
        $zoom_url = new moodle_url('/path/to/zoom'); // Cambia esta URL
        $tasks_url = new moodle_url('/path/to/tasks'); // Cambia esta URL

        /// URLs de las imagenes
        $foro_estudiantes_img = new moodle_url('/blocks/bloquecero/pix/foro_estudiantes_bg.png');
        $foro_anuncios_img = new moodle_url('/blocks/bloquecero/pix/foro_anuncios_bg.png');
        $foro_tutorias_img = new moodle_url('/blocks/bloquecero/pix/foro_tutorias_bg.png');
        // HTML para el bloque
        $this->content->text = '
            <div style="padding: 0 20px; font-family: Arial, sans-serif;">
                <!-- Cabecera -->
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center; background-color: #f9f9f9;">
                    <h2 style="margin: 0; font-size: 1.5em;">Título de la asignatura con la cabecera de marketing</h2>
                    <p style="margin: 10px 0; font-size: 1em;">Profesor/a: Sinesio Delgado</p>
                    <button style="
                        padding: 10px 15px;
                        background-color: #0073e6;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 1em;
                        transition: background-color 0.3s ease;
                    " onmouseover="this.style.backgroundColor=\'#005bb5\';" onmouseout="this.style.backgroundColor=\'#0073e6\';" onclick="toggleContactInfo()">Datos de contacto</button>
                    <div id="contact-info" style="
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
                        <p><strong>E-mail:</strong> jesusalberto.arenas@udima.es</p>
                        <p><strong>Teléfono:</strong> +34 918561694 / +34 911896994 - Extensión 3563</p>
                        <p><strong>Horario de Tutorías Telefónicas (horario Madrid):</strong></p>
                        <ul>
                            <li>Lunes y Martes de 17:00 a 19:00 h.</li>
                            <li>Miércoles y Jueves de 12:00 a 14:00 h.</li>
                        </ul>
                    </div>
                </div>

                <!-- Foros -->
                <div style="display: flex; flex-direction: row; justify-content: center; gap: 20px; margin: 20px 0;">
                    <a href="' . $forum_anuncios_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                        <div class="forum-card" style="
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                            text-align: center;
                            background-image: url(' . $foro_anuncios_img . ');
                            background-size: cover;
                            background-position: center;
                            color: white;
                            transition: transform 0.3s ease, box-shadow 0.3s ease;
                            position: relative;
                            height: 150px;
                            display: flex;
                            align-items: flex-end;
                            justify-content: center;
                        ">
                            <div style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 100%;
                                height: 50%;
                                background-color: rgba(0,0,0,0.5);
                                border-radius: 0 0 8px 8px;
                            "></div>
                            <h3 style="
                                margin: 10px;
                                font-size: 1.2em;
                                position: relative;
                                z-index: 1;
                            ">Tablón de anuncios</h3>
                        </div>
                    </a>
                    <a href="' . $forum_tutorias_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                        <div class="forum-card" style="
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                            text-align: center;
                            background-image: url(' . $foro_tutorias_img . ');
                            background-size: cover;
                            background-position: center;
                            color: white;
                            transition: transform 0.3s ease, box-shadow 0.3s ease;
                            position: relative;
                            height: 150px;
                            display: flex;
                            align-items: flex-end;
                            justify-content: center;
                        ">
                            <div style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 100%;
                                height: 50%;
                                background-color: rgba(0,0,0,0.5);
                                border-radius: 0 0 8px 8px;
                            "></div>
                            <h3 style="
                                margin: 10px;
                                font-size: 1.2em;
                                position: relative;
                                z-index: 1;
                            ">Foro de Tutorías</h3>
                        </div>
                    </a>
                    <a href="' . $forum_estudiantes_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                        <div class="forum-card" style="
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                            text-align: center;
                            background-image: url(' . $foro_estudiantes_img . ');
                            background-size: cover;
                            background-position: top center;
                            color: white;
                            transition: transform 0.3s ease, box-shadow 0.3s ease;
                            position: relative;
                            height: 150px;
                            display: flex;
                            align-items: flex-end;
                            justify-content: center;
                        ">
                            <div style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 100%;
                                height: 50%;
                                background-color: rgba(0,0,0,0.5);
                                border-radius: 0 0 8px 8px;
                            "></div>
                            <h3 style="
                                margin: 10px;
                                font-size: 1.2em;
                                position: relative;
                                z-index: 1;
                            ">Foro de Estudiantes</h3>
                        </div>
                    </a>
                </div>

                <!-- Enlace a la Guía Docente y Bibliografía -->
                <div style="display: flex; flex-direction: row; justify-content: center; gap: 20px; margin: 20px 0;">
                    <a href="' . $guide_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                        <div style="
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            padding: 20px;
                            text-align: center;
                            background-color: #f9f9f9;
                        ">
                            <h3 style="margin: 0; font-size: 1.2em; color: #333;">Enlace a la Guía Docente</h3>
                        </div>
                    </a>
                    <a href="' . $bibliography_url . '" style="text-decoration: none; color: inherit; flex: 1;">
                        <div style="
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            padding: 20px;
                            text-align: center;
                            background-color: #f9f9f9;
                        ">
                            <h3 style="margin: 0; font-size: 1.2em; color: #333;">Bibliografía referencia</h3>
                        </div>
                    </a>
                </div>

                

                <!-- Sesiones Zoom y Actividades -->
                <div style="display: flex; flex-direction: row; justify-content: center; gap: 20px; margin: 20px 0; align-items: flex-start; width: 100%;">
                    <!-- Sesiones Zoom -->
                    <div style="
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        padding: 20px;
                        text-align: left;
                        background-color: #f9f9f9;
                        flex: 1;
                    ">
                    <h3 style="margin: 0 0 10px 0; font-size: 1.2em; color: #333; cursor: pointer; text-align: center;" onclick="toggleZoom()">Sesiones Zoom</h3>
                        <div id="zoom-container" style="overflow: hidden; max-height: 0; transition: max-height 0.3s ease; margin-top: 10px;">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li><a href="#" style="text-decoration: none; color: #0073e6;">28/03/2025 - Introducción al curso</a></li>
                                <li><a href="#" style="text-decoration: none; color: #0073e6;">30/03/2025 - Sesión práctica 1</a></li>
                                <li><a href="#" style="text-decoration: none; color: #0073e6;">05/04/2025 - Resolución de dudas</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Actividades y Tareas -->
                    <div style="
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        padding: 20px;
                        text-align: left;
                        background-color: #f9f9f9;
                        flex: 1;
                    ">
                        <h3 style="margin: 0 0 10px 0; font-size: 1.2em; color: #333; cursor: pointer; text-align: center;" onclick="toggleActivities()">Actividades y Tareas Evaluación</h3>
                        <div id="activities-container" style="overflow: hidden; max-height: 0; transition: max-height 0.3s ease; margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span onclick="changeWeek(-1)" style="
                                    font-size: 1.5em;
                                    font-weight: bold;
                                    color: #0073e6;
                                    cursor: pointer;
                                ">&lt;</span>
                                <span id="current-week" style="font-size: 1em; font-weight: bold;">Semana 1</span>
                                <span onclick="changeWeek(1)" style="
                                    font-size: 1.5em;
                                    font-weight: bold;
                                    color: #0073e6;
                                    cursor: pointer;
                                ">&gt;</span>
                            </div>
                            <ul id="activities-list" style="list-style: none; padding: 0; margin: 0;">
                                <!-- Actividades de la semana 1 -->
                                <li><a href="#" style="text-decoration: none; color: #0073e6;">01/04/2025 - Tarea 1: Ensayo</a></li>
                                <li><a href="#" style="text-decoration: none; color: #0073e6;">10/04/2025 - Cuestionario 1</a></li>
                                <li><a href="#" style="text-decoration: none; color: #0073e6;">15/04/2025 - Proyecto final</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <style>
                    .forum-card:hover {
                        transform: scale(1.05);
                        box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
                    }
                </style>

            </div>

            <script>
                function toggleContactInfo() {
                    const contactInfo = document.getElementById("contact-info");
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

                function toggleActivities() {
                    const container = document.getElementById("activities-container");
                    if (container.style.maxHeight === "0px" || container.style.maxHeight === "") {
                        container.style.maxHeight = container.scrollHeight + "px";
                    } else {
                        container.style.maxHeight = "0px";
                    }
                }
                
                const activitiesByWeek = {
                    1: [
                        { date: "01/04/2025", name: "Tarea 1: Ensayo", url: "#" },
                        { date: "10/04/2025", name: "Cuestionario 1", url: "#" },
                        { date: "15/04/2025", name: "Proyecto final", url: "#" }
                    ],
                    2: [
                        { date: "22/04/2025", name: "Tarea 2: Investigación", url: "#" },
                        { date: "25/04/2025", name: "Cuestionario 2", url: "#" },
                        { date: "30/04/2025", name: "Presentación grupal", url: "#" }
                    ],
                    3: [
                        { date: "05/05/2025", name: "Tarea 3: Análisis", url: "#" },
                        { date: "10/05/2025", name: "Examen parcial", url: "#" }
                    ]
                };

                let currentWeek = 1;

                function changeWeek(direction) {
                    const weeks = Object.keys(activitiesByWeek).map(Number);
                    const currentIndex = weeks.indexOf(currentWeek);
                    const newIndex = currentIndex + direction;
                    if (newIndex >= 0 && newIndex < weeks.length) {
                        currentWeek = weeks[newIndex];
                        updateActivities();
                    }
                }

                function updateActivities() {
                    const activitiesList = document.getElementById("activities-list");
                    const currentWeekSpan = document.getElementById("current-week");
                    currentWeekSpan.textContent = `Semana ${currentWeek}`;
                    activitiesList.innerHTML = "";
                    activitiesByWeek[currentWeek].forEach(activity => {
                        const listItem = document.createElement("li");
                        const link = document.createElement("a");
                        link.href = activity.url;
                        link.textContent = `${activity.date} - ${activity.name}`;
                        link.style.textDecoration = "none";
                        link.style.color = "#0073e6";
                        listItem.appendChild(link);
                        activitiesList.appendChild(listItem);
                    });
                }

                function toggleZoom() {
                    const container = document.getElementById("zoom-container");
                    const chevron = document.getElementById("zoom-chevron");
                    if (container.style.maxHeight === "0px" || container.style.maxHeight === "") {
                        container.style.maxHeight = container.scrollHeight + "px";
                        chevron.style.transform = "rotate(90deg)";
                    } else {
                        container.style.maxHeight = "0px";
                        chevron.style.transform = "rotate(0deg)";
                    }
                }
            </script>
        ';
    
        return $this->content;
    }

    public function applicable_formats() {
        return ["site" => true, "course" => true, "my" => true];
    }    

    public function has_config() {
        return true;
    }

    public function hide_header() {
        return true;
    }
                

}