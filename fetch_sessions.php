<?php
$__script_start = microtime(true);
ob_start();
require_once('../../config.php');
$courseid = required_param('courseid', PARAM_INT); // Lo recoges de la URL o POST o lo que uses.
$course = get_course($courseid); // Esto te da el objeto curso correctamente.

error_reporting(E_ALL);
ini_set('display_errors', 1);

global $USER;

// Obtener configuraciones desde Moodle
$client_id = get_config('block_zoom_udima', 'client_id');
$client_secret = get_config('block_zoom_udima', 'client_secret');
$token_url = get_config('block_zoom_udima', 'token_url');
$scope = get_config('block_zoom_udima', 'scope');

// Obtener el token OAuth2
$data = "grant_type=client_credentials"
      . "&client_id=" . urlencode($client_id)
      . "&client_secret=" . urlencode($client_secret)
      . "&scope=" . urlencode($scope);


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$__t_token_start = microtime(true);
$response = curl_exec($ch);
$__t_token_end = microtime(true);
$__token_ms = (int) round(( $__t_token_end - $__t_token_start ) * 1000);
curl_close($ch);

$token_data = json_decode($response, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    echo "<p>Error obteniendo el token.</p>";
    exit;
}

// Hacer la petición a la API de Zoom
// Obtener el rol del usuario en el curso
// Obtener el rol del usuario en el curso y contextos superiores
$userid = $USER->id;


// Determinar el campus actual basado en $CFG->wwwroot
// Determinar el campus actual basado en la configuración del bloque
$campus_config = get_config('block_zoom_udima', 'campus');
if ($campus_config === 'udima') {
    $api_origen = 'udima';
    $api_userid = $USER->username; // ID de usuario en UDIMA
} elseif ($campus_config === 'cef') {
    $api_origen = 'cef';
    $api_userid = $USER->idnumber;
} else {
    // Fallback: autodetectar por dominio si no está configurado
    $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
    if (strpos($host, 'aula.udima.es') !== false) {
        $api_origen = 'udima';
        $api_userid = $USER->username;
    } elseif (strpos($host, 'campus.cef.es') !== false) {
        $api_origen = 'cef';
        $api_userid = $USER->id;
    } else {
        $api_origen = 'udima';
        $api_userid = $USER->username;
    }
}
$systemcontext = context_system::instance();
$coursecontext = context_course::instance($course->id);
$categorycontext = context_coursecat::instance($course->category);

// Recoge roles de todos los contextos relevantes
$roles = array_merge(
    get_user_roles($systemcontext, $userid, false),
    get_user_roles($categorycontext, $userid, false),
    get_user_roles($coursecontext, $userid, false)
);

// Deduplicar roles por shortname
$uniqueRolesByShortname = [];
foreach ($roles as $r) {
    if (!empty($r->shortname)) {
        $uniqueRolesByShortname[$r->shortname] = $r;
    }
}

// Prioridad de roles
$priority = ['manager', 'managercoordinadores', 'jefe-de-estudios', 'profesor-edicion', 'editingteacher', 'teacher', 'student'];
$user_role = null;

// Buscar el rol de mayor prioridad
foreach ($priority as $p_role) {
    if (isset($uniqueRolesByShortname[$p_role])) {
        $user_role = $p_role;
        break;
    }
}

// Fallback: primer rol encontrado
if (!$user_role && !empty($uniqueRolesByShortname)) {
    $first = reset($uniqueRolesByShortname);
    $user_role = $first->shortname ?? null;
}

// Mapear el rol de Moodle al formato esperado por la API
$role_mapping = [
    'student'              => 'estudiante',
    'alumno'               => 'alumno',
    'profesor'             => 'profesor',
    'teacher'              => 'profesor',
    'editingteacher'       => 'profesor',
    'profesor-edicion'     => 'profesor',
    'managercoordinadores' => 'profesor',
    'manager'              => 'manager',
    'jefe-de-estudios'     => 'manager',
];




$api_role = $role_mapping[$user_role] ?? 'estudiante'; // Valor predeterminado si no se encuentra el rol
if (is_siteadmin($USER)) {
    $api_role = 'manager';
}

$user_system_roles2 = get_user_roles($systemcontext, $userid, false);
$user_course_roles2 = get_user_roles($coursecontext, $userid, false);
// Si el usuario es manager en el sistema y tiene rol docente en el curso, forzar rol 'profesor'
$system_roles_shortnames = array_map(function($r) { return $r->shortname ?? ''; }, $user_system_roles2);
$course_roles_shortnames = array_map(function($r) { return $r->shortname ?? ''; }, $user_course_roles2);

$is_manager_system = in_array('manager', $system_roles_shortnames);
$is_teacher_course = array_intersect(
    ['teacher', 'editingteacher', 'profesor-edicion'],
    $course_roles_shortnames
);

if ($is_manager_system && !empty($is_teacher_course)) {
    $api_role = 'profesor';
}

$baseurl = get_config('block_zoom_udima', 'baseurl');

$api_url = "$baseurl/Clases/ObtenerClases?id=$api_userid&rol=$api_role&aulamoodle=$course->idnumber&origen=$api_origen";

$mostrar_api_url = get_config('block_zoom_udima', 'mostrar_api_url');
if ($mostrar_api_url) {
    echo "<p>API URL: $api_url</p>";
}
$headers = [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
];

 $__t_api_start = microtime(true);
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$__t_api_end = microtime(true);
$__api_ms = (int) round(( $__t_api_end - $__t_api_start ) * 1000);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
// Para pruebas de desarrollo, define directamente si usar datos de prueba o la API real.
$useTestData = (bool) get_config('block_zoom_udima', 'use_testdata');

if (($http_code !== 200 || !$response) && !$useTestData) {
    echo $OUTPUT->notification("Error al obtener las sesiones. HTTP $http_code: $response", 'notifyproblem');
    exit;
}

$data = json_decode($response, true);



if ($useTestData) {
    $data = [
        [
            'id'           => 1,
            'curso'        => 'Curso de Prueba 1',
            'asignatura'   => 'Matemáticas',
            'docente'      => 'Profesor Juan',
            'horaInicio'   => time() + 310,     // inicia en 5 minutos
            'horaFin'      => time() + 3600,    // termina en 1 hora
            'ubicacion'    => 'Aula 101',
            'coordinador'  => 'Coordinador 1',
            'enlace'       => 'https://zoom.us/j/123456789',
            'activo'       => 1,
            'aula'         => 'Aula Virtual'
        ],
        [
            'id'           => 2,
            'curso'        => 'Curso de Prueba 2',
            'asignatura'   => 'Historia',
            'docente'      => 'Profesora Ana',
            'horaInicio'   => time() - 300,     // inició hace 5 minutos
            'horaFin'      => time() + 900,     // termina en 15 minutos
            'ubicacion'    => 'Aula 202',
            'coordinador'  => 'Coordinador 2',
            'enlace'       => 'https://zoom.us/j/987654321',
            'activo'       => 0
        ],
        [
            'id'           => 3,
            'curso'        => 'Curso de Prueba 3',
            'asignatura'   => 'Ciencias',
            'docente'      => 'Profesor Carlos',
            'horaInicio'   => time() - 7200,    // inició hace 2 horas
            'horaFin'      => time() - 3600,    // terminó hace 1 hora
            'ubicacion'    => 'Aula 303',
            'coordinador'  => 'Coordinador 3',
            'enlace'       => 'https://zoom.us/j/1122334455',
            'activo'       => 0
        ]
    ];
}
// print_r($data);
// 🔹 Si la API devuelve un solo objeto en lugar de un array, convertirlo en un array
if (!isset($data[0])) {
    $data = [$data];
}

if (!$data || empty($data)) {
    echo "<style>
            .block_zoom_udima-message {
                text-align: center;
                font-size: 16px;
                margin: 20px 0;
                color: #000;
            }
            .block_zoom_udima-message i {
                margin-right: 5px;
            }
          </style>";
    echo "<div class='block_zoom_udima-message'><i class='bi bi-calendar-x'></i>" . get_string('no_sessions_found', 'block_zoom_udima') . "</div>";
    exit;
}

// 🔹 Convertir JSON a HTML
$output = "<style>
    .zoom-session {
        background: #ffffff;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 5px;
        position: relative;
        display: block;
        box-shadow: 0px 4px 6px rgba(0,0,0,0.1);
    }
    /* Estilo para que los iconos tengan el color #004D35 */
    .zoom-session i {
         color: #004D35;
         margin-right: 5px;
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
    /* Asegura que los datos y el botón se apilen en columna y no se superpongan */
    .zoom-access-container {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .zoom-access-container .access-button {
        margin-top: 8px;
        position: static !important;
        transform: none !important;
    }
</style>";

// Contar sesiones válidas antes de generar el HTML
$availableSessions = 0;
foreach ($data as $session) {
    if (
        isset(
            $session['curso'],
            $session['asignatura'],
            $session['docente'],
            $session['horaInicio'],
            $session['horaFin'],
            $session['ubicacion'],
            $session['coordinador'],
            $session['enlace']
        )
    ) {
        $availableSessions++;
    }
}

// Determinar clase de margen según la versión de Moodle.
if (isset($CFG->branch) && $CFG->branch >= 500) {
    $marginClass = "me-2";
} else {
    $marginClass = "mr-2";
}

$output .= "<div class='zoom-sessions'>";
    // Encabezado: botón, título y badge
    $output .= "<div class='zoom-header d-flex align-items-center justify-content-between flex-wrap' style='padding: 0 10px;'>";
        // Contenedor izquierdo: toggle, título y badge
        $output .= "<div class='d-flex align-items-center'>";
            // Botón toggle
            $output .= "<div class='$marginClass'>";
                $output .= "<a role='button' data-bs-toggle='collapse' href='#allSessions' id='toggleSessions' aria-expanded='false' aria-controls='allSessions' class='btn btn-icon icons-collapse-expand justify-content-center collapsed' aria-label='Toggle sesiones'>";
                    $output .= "<span class='expanded-icon icon-no-margin p-2' title='Colapsar'>";
                        $output .= "<i class='fa fa-chevron-down fa-fw' aria-hidden='true'></i>";
                    $output .= "</span>";
                    $output .= "<span class='collapsed-icon icon-no-margin p-2' title='Expandir'>";
                        $output .= "<i class='fa fa-chevron-right fa-fw' aria-hidden='true'></i>";
                    $output .= "</span>";
                $output .= "</a>";
            $output .= "</div>";
            // Título y badge
            $output .= "<h4 style='font-size:18px; margin-bottom:10px; margin-left: 10px;' class='mb-0'>" . get_string('livesessions', 'block_zoom_udima') . "</h4>";
            $output .= "<span class='badge bg-primary rounded-circle' style='width: 1.5rem; height: 1.5rem; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left:10px; color: #fff;'>$availableSessions</span>";
        $output .= "</div>";
        // // Contenedor derecho: enlace de grabaciones
        // $recordsurl = new moodle_url('/blocks/zoom_udima/records.php', [ 'courseid' => $courseid ]);
        // $output .= "<div>";
        //     $output .= "<a href='{$recordsurl}' class='btn btn-link' style='text-decoration: none;'>Ver grabaciones</a>";
        // $output .= "</div>";
    $output .= "</div>";
    
    // Contenedor collapse para todas las sesiones (si no hay sesiones se mostrará el mensaje dentro)
    $output .= "<div class='collapse' id='allSessions'>";
        if ($availableSessions == 0) {
            // Mostrar el mensaje de no hay sesiones dentro del desplegable
            $output .= "<div class='block_zoom_udima-message' style='text-align:center; font-size:16px; margin:20px 0; color:#000;'>";
                $output .= "<i class='bi bi-calendar-x' style='margin-right:5px;'></i>" . get_string('no_sessions_found', 'block_zoom_udima');
                // Solo mostrar el mensaje si el usuario NO es estudiante
                // if ($api_role !== 'estudiante') {
                //     $output .= "<div style='margin-top:6px; color:#888;'>¿No ves tu sesión?</div>";
                // }
            $output .= "</div>";
        } else {
            // Aquí se genera el listado de sesiones
            // Se establece la bandera y se leen los settings:
            $mostrar_curso       = get_config('block_zoom_udima', 'mostrar_curso');
            $mostrar_asignatura  = get_config('block_zoom_udima', 'mostrar_asignatura');
            $mostrar_ubicacion   = get_config('block_zoom_udima', 'mostrar_ubicacion');
    
            foreach ($data as $session) {
                if (
                    !isset(
                        $session['curso'],
                        $session['asignatura'],
                        $session['docente'],
                        $session['horaInicio'],
                        $session['horaFin'],
                        $session['ubicacion'],
                        $session['coordinador'],
                        $session['enlace']
                    )
                ) {
                    continue;
                }
    
                $curso         = htmlspecialchars($session['curso']);
                $asignatura    = htmlspecialchars($session['asignatura']);
                $docente       = htmlspecialchars($session['docente']);
                $fechaInicio   = userdate($session['horaInicio'], '%H:%M');
                $fechaFin      = userdate($session['horaFin'], '%H:%M');
                $ubicacion     = htmlspecialchars($session['ubicacion']);
                $coordinador   = htmlspecialchars($session['coordinador']);
                $enlace        = htmlspecialchars($session['enlace']);
                
                $activo = isset($session['activo']) ? $session['activo'] : 0;
                
                $output .= "<div class='zoom-session' style='margin-bottom: 20px;'>";
                    $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                    <i class='bi bi-person' style='margin-right:5px;'></i>Docente: $docente
                                </div>";
                    $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                    <i class='bi bi-clock' style='margin-right:5px;'></i>De $fechaInicio a $fechaFin
                                </div>";
                    if ($mostrar_asignatura) {
                        $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                        Asignatura: $asignatura
                                    </div>";
                    }
                    if ($mostrar_curso) {
                        $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                        <i class='bi bi-book' style='margin-right:5px;'></i>Curso: $curso
                                    </div>";
                    }
                    if ($mostrar_ubicacion) {
                        $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                        <i class='bi bi-geo-alt' style='margin-right:5px;'></i>Ubicación: $ubicacion
                                    </div>";
                    }
                    if (isset($session['aula'])) {
                        $aula = htmlspecialchars($session['aula']);
                        $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                        <i class='bi bi-geo-alt' style='margin-right:5px;'></i>Aula: $aula
                                    </div>";
                    }
                    $output .= "<div class='zoom-time' style='margin-bottom:2px;'>
                                    <i class='bi bi-person' style='margin-right:5px;'></i>Coordinador: $coordinador
                                </div>";
                    if ($activo == 1) {
                        $output .= "<div class='zoom-access-container' data-starttime='{$session['horaInicio']}' data-link='{$enlace}'>";
                            if (time() >= $session['horaFin']) {
                                $output .= "<div class='zoom-time ended-message' style='margin-top:5px; color: #6c757d; font-size: 14px;'>" . get_string('notavailable', 'block_zoom_udima') . "</div>";
                            } elseif (time() >= ($session['horaInicio'] - 300)) {
                                $output .= "<button type='button' class='btn btn-primary access-button' onclick=\"logAndOpen('$enlace', 'session_started', $courseid);\">" . get_string('access', 'block_zoom_udima') . "</button>";
                            } else {
                                $output .= "<div class='zoom-time wait-message' style='margin-top:5px; color: #6c757d; font-size: 14px;'>
                                                <i class='fa fa-exclamation-triangle' aria-hidden='true' style='margin-right:5px;'></i>" . get_string('access_wait', 'block_zoom_udima') . "
                                            </div>";
                            }
                        $output .= "</div>";
                    }

                $output .= "</div>";
            }
        }





    $output .= "</div>"; // Cierre collapse
$output .= "</div>"; // Cierre contenedor principal de zoom-sessions


// ---- Timing & diagnostics ----
$__total_ms = (int) round(( microtime(true) - $__script_start ) * 1000);
// Send Server-Timing header (visible in DevTools > Network)
@header(
    "Server-Timing: "
    . "token;desc=\"OAuth token\";dur=" . ($__token_ms ?? 0) . ", "
    . "clases;desc=\"Clases API\";dur=" . ($__api_ms ?? 0) . ", "
    . "total;desc=\"PHP total\";dur=" . $__total_ms
);

// Optionally log to the browser console for admins or when API URL is shown
if (!empty($mostrar_api_url) || is_siteadmin($USER)) {
    $output .= "<script>"
             . "console.log('[zoom_udima] OAuth token: " . (($__token_ms ?? 0)) . " ms');"
             . "console.log('[zoom_udima] Clases API: " . (($__api_ms ?? 0)) . " ms');"
             . "console.log('[zoom_udima] PHP total: " . $__total_ms . " ms');"
             . "</script>";
}
echo $output;
// exit;

