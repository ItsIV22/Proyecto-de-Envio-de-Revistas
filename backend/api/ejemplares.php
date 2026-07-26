<?php
require_once '../../config/database.php';
require_once 'core.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(["message" => "No autorizado."]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // JOIN para traer el título de la revista
        $query = "
            SELECT e.*, r.titulo AS revista_titulo 
            FROM ejemplar e
            JOIN revista r ON e.id_revista = r.id_revista
            ORDER BY e.id_ejemplar DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;

    case 'POST':
        require_role('admin');
        $data = get_json_input();
        if(!empty($data['id_revista']) && !empty($data['numero_edicion']) && !empty($data['fecha_publicacion'])) {
            $query = "INSERT INTO ejemplar (id_revista, numero_edicion, fecha_publicacion) VALUES (:id_revista, :numero_edicion, :fecha_publicacion)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_revista', $data['id_revista']);
            $stmt->bindParam(':numero_edicion', $data['numero_edicion']);
            $stmt->bindParam(':fecha_publicacion', $data['fecha_publicacion']);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Ejemplar creado."]);
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
        require_role('admin');
        $data = get_json_input();
        if(!empty($data['id_ejemplar'])) {
            $query = "UPDATE ejemplar SET id_revista = :id_revista, numero_edicion = :numero_edicion, fecha_publicacion = :fecha_publicacion WHERE id_ejemplar = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data['id_ejemplar']);
            $stmt->bindParam(':id_revista', $data['id_revista']);
            $stmt->bindParam(':numero_edicion', $data['numero_edicion']);
            $stmt->bindParam(':fecha_publicacion', $data['fecha_publicacion']);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Ejemplar actualizado."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
        }
        break;

    case 'DELETE':
        require_role('admin');
        $id = isset($_GET['id']) ? $_GET['id'] : die();
        try {
            $query = "DELETE FROM ejemplar WHERE id_ejemplar = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            if($stmt->execute()){
                echo json_encode(["message" => "Ejemplar eliminado."]);
            }
        } catch (PDOException $e) {
            http_response_code(409);
            echo json_encode(["message" => "No se puede eliminar el ejemplar porque tiene envíos asociados."]);
        }
        break;
}
?>
