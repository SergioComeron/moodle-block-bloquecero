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
 * Callback implementations for block_bloquecero.
 *
 * @package    block_bloquecero
 * @copyright  2025 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hides the course index drawer on course pages where the block is installed.
 *
 * @return string HTML to inject.
 */
function block_bloquecero_before_standard_top_of_body_html() {
    global $COURSE, $DB;

    if (empty($COURSE) || $COURSE->id == SITEID) {
        return '';
    }

    static $hasblock = null;
    if ($hasblock === null) {
        $coursecontext = context_course::instance($COURSE->id);
        $hasblock = $DB->record_exists('block_instances', [
            'blockname'       => 'bloquecero',
            'parentcontextid' => $coursecontext->id,
        ]);
    }

    if (!$hasblock) {
        return '';
    }

    return '<style>
        #courseindex-drawer,
        .courseindex,
        .drawer-toggler.drawer-left-toggle { display: none !important; }
        @media (min-width: 768px) {
            .drawer-left {
                display: block !important;
                transform: translateX(0) !important;
                visibility: visible !important;
            }
            .drawers-backdrop { display: none !important; }
        }
    </style>';
}

/**
 * Serves files for the block_bloquecero plugin.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context $context Context object.
 * @param string $filearea File area name.
 * @param array $args Extra arguments.
 * @param bool $forcedownload Force download flag.
 * @param array $options Additional options.
 */
function block_bloquecero_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Asegúrate de que estamos en el contexto del sistema (donde se guarda la config del plugin).
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        send_file_not_found();
    }

    // Solo permitimos acceso a 'header_bg'.
    if ($filearea !== 'header_bg') {
        send_file_not_found();
    }

    require_login();

    $fs = get_file_storage();

    // Extrae el nombre del archivo.
    $filename = array_pop($args);
    // El filepath en Moodle siempre empieza y termina con "/"
    $filepath = implode('/', $args);
    if ($filepath === '' || $filepath === '0') {
        $filepath = '/';
    } else {
        $filepath = '/' . $filepath . '/';
    }
    if ($filepath === '' || $filepath === '//') {
        $filepath = '/';
    }

    // Intenta obtener el archivo.
    $file = $fs->get_file($context->id, 'block_bloquecero', 'header_bg', 0, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        debugging("❌ Archivo no encontrado: contextid={$context->id}, filearea={$filearea}, itemid=0, filepath={$filepath}, filename={$filename}", DEBUG_DEVELOPER);
        send_file_not_found();
    }

    // ✅ Si llega aquí, lo sirve.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
