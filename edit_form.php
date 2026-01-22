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
 * @copyright  2025 YOUR NAME <your@email.com>
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

        // Si el usuario actual es profesor, permitirle introducir su horario y teléfono
        if (array_key_exists($USER->id, $teachers)) {
            $mform->addElement('header', 'teachercustom', get_string('teachercustom', 'block_bloquecero'));
            $mform->addElement('text', 'config_userphone_' . $USER->id, get_string('userphone', 'block_bloquecero'));
            $mform->setType('config_userphone_' . $USER->id, PARAM_TEXT);
            $mform->setDefault('config_userphone_' . $USER->id, '');
            $mform->addHelpButton('config_userphone_' . $USER->id, 'userphone', 'block_bloquecero');

            $mform->addElement('editor', 'config_userschedule_' . $USER->id, get_string('userschedule', 'block_bloquecero'));
            $mform->setType('config_userschedule_' . $USER->id, PARAM_RAW);
            $mform->setDefault('config_userschedule_' . $USER->id, '');
            $mform->addHelpButton('config_userschedule_' . $USER->id, 'userschedule', 'block_bloquecero');
        }

        // Selector para el número máximo de profesores a mostrar en el bloque.
        $maxteachersoptions = [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5'
        ];
        $mform->addElement('select', 'config_maxteachers', get_string('maxteachers', 'block_bloquecero'), $maxteachersoptions);
        $mform->setDefault('config_maxteachers', 3);
        $mform->addHelpButton('config_maxteachers', 'maxteachers', 'block_bloquecero');

        // --- Configuración de bibliografía (lista de libros) ---
        $mform->addElement('header', 'bibliographyheader', get_string('bibliography', 'block_bloquecero'));

        // Número de libros a mostrar por defecto (puedes cambiarlo)
        $numbooks = 3;
        if (!empty($this->block->config) && !empty($this->block->config->bibliography_name)) {
            $numbooks = max(3, count($this->block->config->bibliography_name));
        }

        $repeatarray = [];
        $repeatarray[] = $mform->createElement('text', 'bibliography_name', get_string('bookname', 'block_bloquecero'));
        $mform->setType('bibliography_name', PARAM_TEXT);
        $repeatarray[] = $mform->createElement('text', 'bibliography_url', get_string('bookurl', 'block_bloquecero'));
        $mform->setType('bibliography_url', PARAM_URL);

        $repeats = $numbooks;
        $this->repeat_elements(
            $repeatarray,
            $repeats,
            [],
            'bibliography_repeats',
            'bibliography_add_fields',
            1,
            get_string('addbook', 'block_bloquecero'),
            true
        );

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
                'blockid' => $blockinstanceid
            ]);
            $managelink = html_writer::link($manageurl, get_string('managesessions', 'block_bloquecero'),
                ['target' => '_blank', 'class' => 'btn btn-secondary']);
            $mform->addElement('static', 'managesessionslink', '', $managelink);
        } else {
            $mform->addElement('static', 'managesessionsinfo', '',
                get_string('saveblockfirst', 'block_bloquecero'));
        }

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
        global $USER;

        error_log('=== SET_DATA EN EDIT_FORM ===');

        if (!empty($this->block->config)) {
            error_log('Config existe: ' . print_r($this->block->config, true));

            // Cargar datos del profesor actual
            $scheduleKey = 'userschedule_' . $USER->id;
            if (!empty($this->block->config->$scheduleKey)) {
                // Si es un array (datos del editor), asignarlo directamente
                if (is_array($this->block->config->$scheduleKey)) {
                    $defaults->{"config_userschedule_" . $USER->id} = $this->block->config->$scheduleKey;
                } else {
                    // Si es texto plano, convertirlo a formato editor
                    $defaults->{"config_userschedule_" . $USER->id} = [
                        'text' => $this->block->config->$scheduleKey,
                        'format' => FORMAT_HTML
                    ];
                }
            }

            // Cargar bibliografía existente
            if (!empty($this->block->config->bibliography_name) && is_array($this->block->config->bibliography_name)) {
                error_log('Cargando bibliography_name: ' . print_r($this->block->config->bibliography_name, true));

                foreach ($this->block->config->bibliography_name as $index => $name) {
                    $defaults->{"bibliography_name[$index]"} = $name;
                }
            }

            if (!empty($this->block->config->bibliography_url) && is_array($this->block->config->bibliography_url)) {
                error_log('Cargando bibliography_url: ' . print_r($this->block->config->bibliography_url, true));

                foreach ($this->block->config->bibliography_url as $index => $url) {
                    $defaults->{"bibliography_url[$index]"} = $url;
                }
            }

        } else {
            error_log('Config NO existe o está vacío');
        }

        parent::set_data($defaults);
    }

    /**
     * Perform some moodle validation.
     * Procesa los datos antes de guardarlos.
     */
    public function get_data() {
        $data = parent::get_data();

        if ($data) {
            // Los campos repetidos vienen SIN el prefijo config_, pero necesitamos añadírselo
            // para que se guarden correctamente en la configuración del bloque
            if (isset($data->bibliography_name) && is_array($data->bibliography_name)) {
                // Filtrar valores vacíos
                $filteredNames = [];
                $filteredUrls = [];

                foreach ($data->bibliography_name as $index => $name) {
                    $name = trim($name);
                    if ($name !== '') {
                        $filteredNames[] = $name;

                        $url = isset($data->bibliography_url[$index]) ? trim($data->bibliography_url[$index]) : '';
                        // Si la URL no está vacía y no empieza por http:// o https://, le añadimos https://
                        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                            $url = 'https://' . $url;
                        }
                        $filteredUrls[] = $url;
                    }
                }

                // Añadir el prefijo config_ para que se guarde correctamente
                $data->config_bibliography_name = $filteredNames;
                $data->config_bibliography_url = $filteredUrls;

                // Eliminar los campos sin prefijo para evitar confusión
                unset($data->bibliography_name);
                unset($data->bibliography_url);

                error_log('=== BIBLIOGRAFÍA PROCESADA ===');
                error_log('config_bibliography_name: ' . print_r($data->config_bibliography_name, true));
                error_log('config_bibliography_url: ' . print_r($data->config_bibliography_url, true));
            }

        }

        return $data;
    }
}
