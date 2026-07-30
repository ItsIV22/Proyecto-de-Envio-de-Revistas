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
        
        $nombre = isset($data['nombre_completo']) ? trim(strip_tags($data['nombre_completo'])) : '';
        $direccion = isset($data['direccion_envio']) ? trim(strip_tags($data['direccion_envio'])) : '';
        $ciudad = isset($data['ciudad']) ? trim(strip_tags($data['ciudad'])) : '';
        $telefono = isset($data['telefono']) ? trim(strip_tags($data['telefono'])) : '';

        if(!empty($nombre) && !empty($direccion) && !empty($ciudad) && !empty($telefono)) {
            // 1. Validar nombre completo
            if (strlen($nombre) < 3 || strlen($nombre) > 150) {
                http_response_code(400);
                echo json_encode(["message" => "El nombre completo debe tener entre 3 y 150 caracteres."]);
                exit();
            }
            if (preg_match('/\d/', $nombre)) {
                http_response_code(400);
                echo json_encode(["message" => "El nombre completo no debe contener números."]);
                exit();
            }

            // 2. Validar dirección de envío
            if (strlen($direccion) < 5) {
                http_response_code(400);
                echo json_encode(["message" => "La dirección de envío debe tener al menos 5 caracteres."]);
                exit();
            }

            // 3. Validar ciudad
            if (strlen($ciudad) < 2 || strlen($ciudad) > 100) {
                http_response_code(400);
                echo json_encode(["message" => "La ciudad debe tener entre 2 y 100 caracteres."]);
                exit();
            }
            if (preg_match('/\d/', $ciudad)) {
                http_response_code(400);
                echo json_encode(["message" => "La ciudad no debe contener números."]);
                exit();
            }

            // 4. Validar teléfono
            if (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $telefono) || strlen($telefono) < 7 || strlen($telefono) > 20) {
                http_response_code(400);
                echo json_encode(["message" => "El teléfono no es válido. Debe tener entre 7 y 20 caracteres y contener solo números, espacios, guiones o paréntesis."]);
                exit();
            }

            // Iniciar transacción para asegurar consistencia
            $db->beginTransaction();
            
            try {
                // 1. Insertar la persona
                $query = "INSERT INTO persona (nombre_completo, direccion_envio, ciudad, telefono) VALUES (:nombre, :direccion, :ciudad, :telefono)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':direccion', $direccion);
                $stmt->bindParam(':ciudad', $ciudad);
                $stmt->bindParam(':telefono', $telefono);
                $stmt->execute();
                
                $id_persona = $db->lastInsertId();
                
                // 2. Generar nombre de usuario único
                $parts = explode(' ', trim($nombre));
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
        
        $id_persona = isset($data['id_persona']) ? intval($data['id_persona']) : 0;
        $nombre = isset($data['nombre_completo']) ? trim(strip_tags($data['nombre_completo'])) : '';
        $direccion = isset($data['direccion_envio']) ? trim(strip_tags($data['direccion_envio'])) : '';
        $ciudad = isset($data['ciudad']) ? trim(strip_tags($data['ciudad'])) : '';
        $telefono = isset($data['telefono']) ? trim(strip_tags($data['telefono'])) : '';

        if($id_persona > 0 && !empty($nombre) && !empty($direccion) && !empty($ciudad) && !empty($telefono)) {
            // 1. Validar nombre completo
            if (strlen($nombre) < 3 || strlen($nombre) > 150) {
                http_response_code(400);
                echo json_encode(["message" => "El nombre completo debe tener entre 3 y 150 caracteres."]);
                exit();
            }
            if (preg_match('/\d/', $nombre)) {
                http_response_code(400);
                echo json_encode(["message" => "El nombre completo no debe contener números."]);
                exit();
            }

            // 2. Validar dirección de envío
            if (strlen($direccion) < 5) {
                http_response_code(400);
                echo json_encode(["message" => "La dirección de envío debe tener al menos 5 caracteres."]);
                exit();
            }

            // 3. Validar ciudad
            if (strlen($ciudad) < 2 || strlen($ciudad) > 100) {
                http_response_code(400);
                echo json_encode(["message" => "La ciudad debe tener entre 2 y 100 caracteres."]);
                exit();
            }
            if (preg_match('/\d/', $ciudad)) {
                http_response_code(400);
                echo json_encode(["message" => "La ciudad no debe contener números."]);
                exit();
            }

            // 4. Validar teléfono
            if (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $telefono) || strlen($telefono) < 7 || strlen($telefono) > 20) {
                http_response_code(400);
                echo json_encode(["message" => "El teléfono no es válido. Debe tener entre 7 y 20 caracteres y contener solo números, espacios, guiones o paréntesis."]);
                exit();
            }

            $query = "UPDATE persona SET nombre_completo = :nombre, direccion_envio = :direccion, ciudad = :ciudad, telefono = :telefono WHERE id_persona = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_persona);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':ciudad', $ciudad);
            $stmt->bindParam(':telefono', $telefono);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Persona actualizada."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos o inválidos."]);
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
