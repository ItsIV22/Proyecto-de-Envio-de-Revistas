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
        if(!empty($data['nombre_agencia']) && !empty($data['contacto'])) {
            $query = "INSERT INTO agencia_transporte (nombre_agencia, contacto) VALUES (:nombre, :contacto)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre_agencia']);
            $stmt->bindParam(':contacto', $data['contacto']);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Agencia creada."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos."]);
        }
        break;

    case 'PUT':
        $data = get_json_input();
        if(!empty($data['id_agencia'])) {
            $query = "UPDATE agencia_transporte SET nombre_agencia = :nombre, contacto = :contacto WHERE id_agencia = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data['id_agencia']);
            $stmt->bindParam(':nombre', $data['nombre_agencia']);
            $stmt->bindParam(':contacto', $data['contacto']);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Agencia actualizada."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
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
