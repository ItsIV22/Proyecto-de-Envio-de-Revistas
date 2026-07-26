<?php
require_once '../../config/database.php';
require_once 'core.php';

// Solo usuarios logueados pueden interactuar con este endpoint (admin para CRUD, cliente tal vez para leer)
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
        $query = "SELECT * FROM revista ORDER BY id_revista DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;

    case 'POST':
        require_role('admin'); // Solo admin puede crear
        $data = get_json_input();
        if(!empty($data['titulo']) && !empty($data['categoria']) && !empty($data['periodicidad'])) {
            $query = "INSERT INTO revista (titulo, categoria, periodicidad) VALUES (:titulo, :categoria, :periodicidad)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':titulo', $data['titulo']);
            $stmt->bindParam(':categoria', $data['categoria']);
            $stmt->bindParam(':periodicidad', $data['periodicidad']);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Revista creada.", "id" => $db->lastInsertId()]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear la revista."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos."]);
        }
        break;

    case 'PUT':
        require_role('admin');
        $data = get_json_input();
        if(!empty($data['id_revista'])) {
            $query = "UPDATE revista SET titulo = :titulo, categoria = :categoria, periodicidad = :periodicidad WHERE id_revista = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data['id_revista']);
            $stmt->bindParam(':titulo', $data['titulo']);
            $stmt->bindParam(':categoria', $data['categoria']);
            $stmt->bindParam(':periodicidad', $data['periodicidad']);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Revista actualizada."]);
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
            $query = "DELETE FROM revista WHERE id_revista = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            if($stmt->execute()){
                echo json_encode(["message" => "Revista eliminada."]);
            }
        } catch (PDOException $e) {
            http_response_code(409); // Conflict, due to foreign key restriction
            echo json_encode(["message" => "No se puede eliminar la revista porque tiene ejemplares asociados."]);
        }
        break;
}
?>
