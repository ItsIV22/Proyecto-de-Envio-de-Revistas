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
        if(!empty($data['nombre_completo']) && !empty($data['direccion_envio']) && !empty($data['ciudad'])) {
            $query = "INSERT INTO persona (nombre_completo, direccion_envio, ciudad, telefono) VALUES (:nombre, :direccion, :ciudad, :telefono)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre_completo']);
            $stmt->bindParam(':direccion', $data['direccion_envio']);
            $stmt->bindParam(':ciudad', $data['ciudad']);
            $stmt->bindParam(':telefono', $data['telefono']);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Persona creada."]);
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
        try {
            $query = "DELETE FROM persona WHERE id_persona = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            if($stmt->execute()){
                echo json_encode(["message" => "Persona eliminada."]);
            }
        } catch (PDOException $e) {
            http_response_code(409);
            echo json_encode(["message" => "No se puede eliminar la persona porque tiene envíos asociados."]);
        }
        break;
}
?>
