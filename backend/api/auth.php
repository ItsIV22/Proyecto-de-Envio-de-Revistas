<?php
require_once '../../config/database.php';
require_once 'core.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $data = get_json_input();
    
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["message" => "Faltan credenciales."]);
        exit();
    }

    $username = $data['username'];
    $password = $data['password'];

    $query = "SELECT id_usuario, username, password, rol, id_persona FROM usuario WHERE username = :username LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        // Verificamos password con password_verify y un fallback de texto plano para migración automática
        $is_password_correct = false;
        $needs_rehash = false;

        if (password_verify($password, $user['password'])) {
            $is_password_correct = true;
        } else if ($password === $user['password']) {
            $is_password_correct = true;
            $needs_rehash = true;
        }

        if ($is_password_correct) {
            // Si estaba en texto plano, la actualizamos automáticamente en la BD a Bcrypt
            if ($needs_rehash) {
                try {
                    $new_hash = password_hash($password, PASSWORD_BCRYPT);
                    $updateQuery = "UPDATE usuario SET password = :password WHERE id_usuario = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':password', $new_hash);
                    $updateStmt->bindParam(':id', $user['id_usuario']);
                    $updateStmt->execute();
                } catch (Exception $e) {
                    // Ignorar silenciosamente si no se puede escribir para no bloquear el login
                }
            }

            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['rol'];
            $_SESSION['persona_id'] = $user['id_persona'];

            echo json_encode([
                "message" => "Login exitoso",
                "user" => [
                    "id" => $user['id_usuario'],
                    "username" => $user['username'],
                    "role" => $user['rol'],
                    "persona_id" => $user['id_persona']
                ]
            ]);
            exit();
        }
    }
    
    http_response_code(401);
    echo json_encode(["message" => "Credenciales inválidas."]);
}
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(["message" => "Sesión cerrada correctamente."]);
}
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'session') {
    if (is_logged_in()) {
        echo json_encode([
            "logged_in" => true,
            "user" => [
                "id" => $_SESSION['user_id'],
                "username" => $_SESSION['username'],
                "role" => $_SESSION['role'],
                "persona_id" => $_SESSION['persona_id'] ?? null
            ]
        ]);
    } else {
        echo json_encode(["logged_in" => false]);
    }
}
else {
    http_response_code(404);
    echo json_encode(["message" => "Ruta de autenticación no encontrada."]);
}
?>
