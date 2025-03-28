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
    
        // Estilos para el spinner, sesiones y filtros
        $this->content->text = "<style>
            .zoom-spinner {
                width: 30px;
                height: 30px;
                border: 4px solid rgba(0, 0, 0, 0.1);
                border-left-color: #007bff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 10px auto;
            }
    
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
    
            .zoom-sessions {
                font-family: Arial, sans-serif;
            }
    
            .zoom-session {
                background: #f8f9fa;
                padding: 10px;
                margin-bottom: 10px;
                border-radius: 5px;
                position: relative;
                display: block;
            }

            .zoom-title {
                font-size: 16px;
                font-weight: bold;
            }
    
            .zoom-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 8px;
            }
    
            .zoom-time {
                font-size: 12px;
                color: #6c757d;
            }

            .filter-container {
                margin-bottom: 10px;
                display: flex;
                gap: 10px;
            }

            .filter-container select {
                padding: 5px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .filter-container {
    display: flex;
    flex-direction: column; /* Los filtros se apilan verticalmente */
    gap: 8px; /* Espacio entre cada filtro */
    justify-content: center;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 250px; /* Ajuste para que no se salgan del bloque */
    margin-left: auto;
    margin-right: auto;
}

.filter-container select {
    padding: 6px;
    border: 1px solid #007bff;
    border-radius: 5px;
    font-size: 12px;
    background-color: #fff;
    color: #333;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    font-weight: normal;
    width: 100%; /* Ajuste para que el tamaño sea consistente */
    max-width: 230px;
}

/* Icono de desplegable en los select */
.filter-container select {
    background-image: url('data:image/svg+xml;utf8,<svg fill=\"%23007bff\" xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" width=\"16px\" height=\"16px\"><path d=\"M7 10l5 5 5-5z\"/></svg>');
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 14px;
    padding-right: 25px;
}

.filter-container select:hover {
    border-color: #0056b3;
}

.filter-container select:focus {
    outline: none;
    border-color: #0056b3;
    box-shadow: 0 0 5px rgba(0, 91, 187, 0.3);
}
        </style>";
    
        // Contenedor de filtros
        $this->content->text .= '
            <div class="filter-container">
                <select id="filter-date">
                    <option value="">Filtrar por fecha</option>
                </select>
                <select id="filter-subject">
                    <option value="">Filtrar por asignatura</option>
                </select>
                <select id="filter-teacher">
                    <option value="">Filtrar por profesor</option>
                </select>
            </div>
        ';

        // Contenedor inicial con el mensaje de carga y spinner
        $this->content->text .= '<div id="zoom-udima-container">';
        $this->content->text .= '<p>Cargando sesiones de Zoom...</p>';
        $this->content->text .= '<div class="zoom-spinner"></div>';
        $this->content->text .= '</div>';
    
        // Incluir JavaScript para hacer la petición AJAX y filtrar datos
        $this->content->text .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                fetchZoomSessions();
            });

            function fetchZoomSessions() {
                fetch("' . new moodle_url('/blocks/bloquecero/fetch_sessions.php') . '")
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById("zoom-udima-container").innerHTML = data;
                        initializeFilters();
                    })
                    .catch(error => {
                        document.getElementById("zoom-udima-container").innerHTML = "<p>Error al cargar las sesiones.</p>";
                    });
            }

            function initializeFilters() {
                let sessions = document.querySelectorAll(".zoom-session");
                let dateFilter = document.getElementById("filter-date");
                let subjectFilter = document.getElementById("filter-subject");
                let teacherFilter = document.getElementById("filter-teacher");

                let dates = new Set();
                let subjects = new Set();
                let teachers = new Set();

                sessions.forEach(session => {
                    dates.add(session.getAttribute("data-date"));
                    subjects.add(session.getAttribute("data-subject"));
                    teachers.add(session.getAttribute("data-teacher"));
                });

                populateFilter(dateFilter, dates);
                populateFilter(subjectFilter, subjects);
                populateFilter(teacherFilter, teachers);

                dateFilter.addEventListener("change", filterSessions);
                subjectFilter.addEventListener("change", filterSessions);
                teacherFilter.addEventListener("change", filterSessions);
            }

            function populateFilter(filter, values) {
                values.forEach(value => {
                    let option = document.createElement("option");
                    option.value = value;
                    option.textContent = value;
                    filter.appendChild(option);
                });
            }

            function filterSessions() {
                let dateValue = document.getElementById("filter-date").value;
                let subjectValue = document.getElementById("filter-subject").value;
                let teacherValue = document.getElementById("filter-teacher").value;

                document.querySelectorAll(".zoom-session").forEach(session => {
                    let match = true;

                    if (dateValue && session.getAttribute("data-date") !== dateValue) {
                        match = false;
                    }
                    if (subjectValue && session.getAttribute("data-subject") !== subjectValue) {
                        match = false;
                    }
                    if (teacherValue && session.getAttribute("data-teacher") !== teacherValue) {
                        match = false;
                    }

                    session.style.display = match ? "block" : "none";
                });
            }
        </script>';
    
        return $this->content;
    }

    public function applicable_formats() {
        return ["site" => true, "course" => true, "my" => true];
    }    

    public function has_config() {
        return true;
    }
}