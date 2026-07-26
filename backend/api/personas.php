<?php
require_once '../../config/database.php';
require_once 'core.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(["message" => "No autorizado."]);
    exit();
}

// Solo el admin debería poder ver o modificar todas las personas.
require_role('admin');

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query = "SELECT * FROM persona ORDER BY id_persona DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;

    case 'POST':
        $data = get_json_input();
        if(!empty($data['nombre_completo']) && !empty($data['direccion_envio']) && !empty($data['ciudad']) && !empty($data['telefono'])) {
            
            // Iniciar transacción para asegurar consistencia
            $db->beginTransaction();
            
            try {
                // 1. Insertar la persona
                $query = "INSERT INTO persona (nombre_completo, direccion_envio, ciudad, telefono) VALUES (:nombre, :direccion, :ciudad, :telefono)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nombre', $data['nombre_completo']);
                $stmt->bindParam(':direccion', $data['direccion_envio']);
                $stmt->bindParam(':ciudad', $data['ciudad']);
                $stmt->bindParam(':telefono', $data['telefono']);
                $stmt->execute();
                
                $id_persona = $db->lastInsertId();
                
                // 2. Generar nombre de usuario único
                $parts = explode(' ', trim($data['nombre_completo']));
                $first_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $parts[0]));
                $username = $first_name . rand(100, 999);
                
                // Verificar que no exista el username
                $checkQuery = "SELECT COUNT(*) FROM usuario WHERE username = :username";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':username', $username);
                $checkStmt->execute();
                if ($checkStmt->fetchColumn() > 0) {
                    $username = $first_name . rand(1000, 9999); // Re-generar si existe
                }
                
                // 3. Generar contraseña temporal
                $temp_password = "usr" . rand(1000, 9999);
                $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);
                
                // 4. Crear el usuario del cliente
                $userQuery = "INSERT INTO usuario (username, password, rol, id_persona) VALUES (:username, :password, 'cliente', :id_persona)";
                $userStmt = $db->prepare($userQuery);
                $userStmt->bindParam(':username', $username);
                $userStmt->bindParam(':password', $hashed_password);
                $userStmt->bindParam(':id_persona', $id_persona);
                $userStmt->execute();
                
                $db->commit();
                
                http_response_code(201);
                echo json_encode([
                    "message" => "Persona y usuario creados exitosamente.",
                    "credentials" => [
                        "username" => $username,
                        "password" => $temp_password
                    ]
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(["message" => "Error al registrar la persona y el usuario.", "error" => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos. Se requieren todos los campos."]);
        }
        break;

    case 'PUT':
        $data = get_json_input();
        if(!empty($data['id_persona'])) {
            $query = "UPDATE persona SET nombre_completo = :nombre, direccion_envio = :direccion, ciudad = :ciudad, telefono = :telefono WHERE id_persona = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data['id_persona']);
            $stmt->bindParam(':nombre', $data['nombre_completo']);
            $stmt->bindParam(':direccion', $data['direccion_envio']);
            $stmt->bindParam(':ciudad', $data['ciudad']);
            $stmt->bindParam(':telefono', $data['telefono']);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Persona actualizada."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? $_GET['id'] : die();
        
        $db->beginTransaction();
        try {
            // 1. Eliminar el usuario asociado primero (para evitar la restricción FK)
            $delUserQuery = "DELETE FROM usuario WHERE id_persona = :id";
            $delUserStmt = $db->prepare($delUserQuery);
            $delUserStmt->bindParam(':id', $id);
            $delUserStmt->execute();
            
            // 2. Eliminar la persona
            $query = "DELETE FROM persona WHERE id_persona = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $db->commit();
            echo json_encode(["message" => "Persona y usuario eliminados."]);
        } catch (PDOException $e) {
            $db->rollBack();
            http_response_code(409);
            echo json_encode(["message" => "No se puede eliminar la persona porque tiene envíos asociados."]);
        }
        break;
}
?>
