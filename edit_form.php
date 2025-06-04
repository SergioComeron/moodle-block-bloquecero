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

            $mform->addElement('textarea', 'config_userschedule_' . $USER->id, get_string('userschedule', 'block_bloquecero'), 'wrap="virtual" rows="3" cols="40"');
            $mform->setType('config_userschedule_' . $USER->id, PARAM_TEXT);
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
    }
}
