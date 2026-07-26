<?php
/**
 * core.php
 * Archivo central para inicializar sesiones, configurar CORS y headers JSON.
 * También provee funciones de utilidad para verificar roles.
 */

// Iniciar la sesión si no está iniciada (usaremos cookies de sesión estándar de PHP)
// Para que fetch() en JS envíe las cookies, debemos permitir credentials y un origen específico.
if (session_status() === PHP_SESSION_NONE) {
    // Configuraciones de seguridad para la sesión
    ini_set('session.cookie_httponly', 1);
    // ini_set('session.cookie_secure', 1); // Descomentar en producción con HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Configurar CORS
// Si el frontend está en el mismo dominio/puerto (ej. Laragon), no hay problema de CORS.
// Si se usa un dev server (ej. VS Code Live Server puerto 5500), debemos permitir el origen explícitamente.
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Manejo de la petición pre-flight de CORS (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Verifica si el usuario actual tiene sesión iniciada
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Verifica si el rol del usuario coincide con el rol requerido
 */
function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Protege el endpoint y detiene la ejecución si el usuario no tiene el rol necesario
 */
function require_role($role) {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(["message" => "No autorizado. Inicie sesión."]);
        exit();
    }
    if (!has_role($role)) {
        http_response_code(403);
        echo json_encode(["message" => "Acceso denegado. Permisos insuficientes."]);
        exit();
    }
}

/**
 * Función para obtener los datos enviados vía JSON en el cuerpo de la petición (POST/PUT)
 */
function get_json_input() {
    $json = file_get_contents('php://input');
    return json_decode($json, true);
}
?>
