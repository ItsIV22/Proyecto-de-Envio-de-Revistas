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
        
        $id_revista = isset($data['id_revista']) ? intval($data['id_revista']) : 0;
        $numero_edicion = isset($data['numero_edicion']) ? intval($data['numero_edicion']) : 0;
        $fecha_publicacion = isset($data['fecha_publicacion']) ? trim($data['fecha_publicacion']) : '';

        if($id_revista > 0 && $numero_edicion > 0 && !empty($fecha_publicacion)) {
            // 1. Validar que la revista exista
            $checkRev = "SELECT COUNT(*) FROM revista WHERE id_revista = :id_revista";
            $stmtCheckRev = $db->prepare($checkRev);
            $stmtCheckRev->bindParam(':id_revista', $id_revista);
            $stmtCheckRev->execute();
            if ($stmtCheckRev->fetchColumn() == 0) {
                http_response_code(400);
                echo json_encode(["message" => "La revista seleccionada no existe."]);
                exit();
            }

            // 2. Validar formato de fecha (YYYY-MM-DD) y que no sea futura
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_publicacion)) {
                http_response_code(400);
                echo json_encode(["message" => "Formato de fecha inválido. Utilice AAAA-MM-DD."]);
                exit();
            }
            $dateParts = explode('-', $fecha_publicacion);
            if (!checkdate(intval($dateParts[1]), intval($dateParts[2]), intval($dateParts[0]))) {
                http_response_code(400);
                echo json_encode(["message" => "La fecha de publicación no existe en el calendario."]);
                exit();
            }
            if (strtotime($fecha_publicacion) > time() + 86400) { // Tolerancia de zona horaria (1 día)
                http_response_code(400);
                echo json_encode(["message" => "La fecha de publicación no puede ser del futuro."]);
                exit();
            }

            // 3. Validar unicidad del número de edición para esa revista
            $checkUniq = "SELECT COUNT(*) FROM ejemplar WHERE id_revista = :id_revista AND numero_edicion = :numero_edicion";
            $stmtCheckUniq = $db->prepare($checkUniq);
            $stmtCheckUniq->bindParam(':id_revista', $id_revista);
            $stmtCheckUniq->bindParam(':numero_edicion', $numero_edicion);
            $stmtCheckUniq->execute();
            if ($stmtCheckUniq->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "La edición N° $numero_edicion ya está registrada para esta revista."]);
                exit();
            }

            $query = "INSERT INTO ejemplar (id_revista, numero_edicion, fecha_publicacion) VALUES (:id_revista, :numero_edicion, :fecha_publicacion)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_revista', $id_revista);
            $stmt->bindParam(':numero_edicion', $numero_edicion);
            $stmt->bindParam(':fecha_publicacion', $fecha_publicacion);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Ejemplar creado."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos o número de edición inválido."]);
        }
        break;

    case 'PUT':
        require_role('admin');
        $data = get_json_input();
        
        $id_ejemplar = isset($data['id_ejemplar']) ? intval($data['id_ejemplar']) : 0;
        $id_revista = isset($data['id_revista']) ? intval($data['id_revista']) : 0;
        $numero_edicion = isset($data['numero_edicion']) ? intval($data['numero_edicion']) : 0;
        $fecha_publicacion = isset($data['fecha_publicacion']) ? trim($data['fecha_publicacion']) : '';

        if($id_ejemplar > 0 && $id_revista > 0 && $numero_edicion > 0 && !empty($fecha_publicacion)) {
            // 1. Validar que la revista exista
            $checkRev = "SELECT COUNT(*) FROM revista WHERE id_revista = :id_revista";
            $stmtCheckRev = $db->prepare($checkRev);
            $stmtCheckRev->bindParam(':id_revista', $id_revista);
            $stmtCheckRev->execute();
            if ($stmtCheckRev->fetchColumn() == 0) {
                http_response_code(400);
                echo json_encode(["message" => "La revista seleccionada no existe."]);
                exit();
            }

            // 2. Validar formato de fecha y que no sea futura
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_publicacion)) {
                http_response_code(400);
                echo json_encode(["message" => "Formato de fecha inválido. Utilice AAAA-MM-DD."]);
                exit();
            }
            $dateParts = explode('-', $fecha_publicacion);
            if (!checkdate(intval($dateParts[1]), intval($dateParts[2]), intval($dateParts[0]))) {
                http_response_code(400);
                echo json_encode(["message" => "La fecha de publicación no existe en el calendario."]);
                exit();
            }
            if (strtotime($fecha_publicacion) > time() + 86400) {
                http_response_code(400);
                echo json_encode(["message" => "La fecha de publicación no puede ser del futuro."]);
                exit();
            }

            // 3. Validar unicidad de edición para la revista (excluyendo el ejemplar actual)
            $checkUniq = "SELECT COUNT(*) FROM ejemplar WHERE id_revista = :id_revista AND numero_edicion = :numero_edicion AND id_ejemplar != :id";
            $stmtCheckUniq = $db->prepare($checkUniq);
            $stmtCheckUniq->bindParam(':id_revista', $id_revista);
            $stmtCheckUniq->bindParam(':numero_edicion', $numero_edicion);
            $stmtCheckUniq->bindParam(':id', $id_ejemplar);
            $stmtCheckUniq->execute();
            if ($stmtCheckUniq->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "La edición N° $numero_edicion ya está registrada para esta revista."]);
                exit();
            }

            $query = "UPDATE ejemplar SET id_revista = :id_revista, numero_edicion = :numero_edicion, fecha_publicacion = :fecha_publicacion WHERE id_ejemplar = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_ejemplar);
            $stmt->bindParam(':id_revista', $id_revista);
            $stmt->bindParam(':numero_edicion', $numero_edicion);
            $stmt->bindParam(':fecha_publicacion', $fecha_publicacion);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Ejemplar actualizado."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos o número de edición inválido."]);
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
