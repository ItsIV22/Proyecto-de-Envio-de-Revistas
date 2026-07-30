<?php
require_once '../../config/database.php';
require_once 'core.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(["message" => "No autorizado."]);
    exit();
}

require_role('admin');

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query = "SELECT * FROM agencia_transporte ORDER BY id_agencia DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;

    case 'POST':
        $data = get_json_input();
        
        $nombre = isset($data['nombre_agencia']) ? trim(strip_tags($data['nombre_agencia'])) : '';
        $contacto = isset($data['contacto']) ? trim(strip_tags($data['contacto'])) : '';

        if(!empty($nombre) && !empty($contacto)) {
            // 1. Validar longitud del nombre
            if (strlen($nombre) < 3 || strlen($nombre) > 150) {
                http_response_code(400);
                echo json_encode(["message" => "El nombre de la agencia debe tener entre 3 y 150 caracteres."]);
                exit();
            }

            // 2. Validar que el contacto sea un email válido
            if (!filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(["message" => "El contacto debe ser un correo electrónico válido (ej. contacto@empresa.com)."]);
                exit();
            }

            // 3. Validar unicidad del nombre de la agencia
            $checkName = "SELECT COUNT(*) FROM agencia_transporte WHERE LOWER(nombre_agencia) = LOWER(:nombre)";
            $stmtCheck = $db->prepare($checkName);
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "Ya existe una agencia con el nombre '$nombre'."]);
                exit();
            }

            $query = "INSERT INTO agencia_transporte (nombre_agencia, contacto) VALUES (:nombre, :contacto)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':contacto', $contacto);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Agencia creada."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos o inválidos."]);
        }
        break;

    case 'PUT':
        $data = get_json_input();
        
        $id_agencia = isset($data['id_agencia']) ? intval($data['id_agencia']) : 0;
        $nombre = isset($data['nombre_agencia']) ? trim(strip_tags($data['nombre_agencia'])) : '';
        $contacto = isset($data['contacto']) ? trim(strip_tags($data['contacto'])) : '';

        if($id_agencia > 0 && !empty($nombre) && !empty($contacto)) {
            // 1. Validar longitud del nombre
            if (strlen($nombre) < 3 || strlen($nombre) > 150) {
                http_response_code(400);
                echo json_encode(["message" => "El nombre de la agencia debe tener entre 3 y 150 caracteres."]);
                exit();
            }

            // 2. Validar que el contacto sea un email válido
            if (!filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(["message" => "El contacto debe ser un correo electrónico válido."]);
                exit();
            }

            // 3. Validar unicidad del nombre (excluyendo la agencia actual)
            $checkName = "SELECT COUNT(*) FROM agencia_transporte WHERE LOWER(nombre_agencia) = LOWER(:nombre) AND id_agencia != :id";
            $stmtCheck = $db->prepare($checkName);
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->bindParam(':id', $id_agencia);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "Ya existe otra agencia con el nombre '$nombre'."]);
                exit();
            }

            $query = "UPDATE agencia_transporte SET nombre_agencia = :nombre, contacto = :contacto WHERE id_agencia = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_agencia);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':contacto', $contacto);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Agencia actualizada."]);
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
        try {
            $query = "DELETE FROM agencia_transporte WHERE id_agencia = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            if($stmt->execute()){
                echo json_encode(["message" => "Agencia eliminada."]);
            }
        } catch (PDOException $e) {
            http_response_code(409);
            echo json_encode(["message" => "No se puede eliminar la agencia porque tiene envíos asignados."]);
        }
        break;
}
?>
