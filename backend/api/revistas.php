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
        
        $titulo = isset($data['titulo']) ? trim(strip_tags($data['titulo'])) : '';
        $categoria = isset($data['categoria']) ? trim(strip_tags($data['categoria'])) : '';
        $periodicidad = isset($data['periodicidad']) ? trim(strip_tags($data['periodicidad'])) : '';

        $valid_categories = ['Tecnología', 'Gastronomía', 'Turismo', 'Economía', 'Medicina', 'Deportes', 'Cultura', 'Moda', 'Ciencia', 'Automovilismo'];
        $valid_periodicities = ['Semanal', 'Quincenal', 'Mensual', 'Bimestral', 'Trimestral', 'Semestral', 'Anual'];

        if (!empty($titulo) && !empty($categoria) && !empty($periodicidad)) {
            // Validar longitud del título
            if (strlen($titulo) < 3 || strlen($titulo) > 150) {
                http_response_code(400);
                echo json_encode(["message" => "El título debe tener entre 3 y 150 caracteres."]);
                exit();
            }

            // Validar categoría admisible
            if (!in_array($categoria, $valid_categories)) {
                http_response_code(400);
                echo json_encode(["message" => "Categoría no permitida. Seleccione una de las siguientes: " . implode(', ', $valid_categories)]);
                exit();
            }

            // Validar periodicidad admisible
            if (!in_array($periodicidad, $valid_periodicities)) {
                http_response_code(400);
                echo json_encode(["message" => "Periodicidad no permitida. Seleccione una de las siguientes: " . implode(', ', $valid_periodicities)]);
                exit();
            }

            // Validar unicidad del título (case-insensitive)
            $check_query = "SELECT COUNT(*) FROM revista WHERE LOWER(titulo) = LOWER(:titulo)";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':titulo', $titulo);
            $check_stmt->execute();
            if ($check_stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "Ya existe una revista con el título '$titulo'."]);
                exit();
            }

            $query = "INSERT INTO revista (titulo, categoria, periodicidad) VALUES (:titulo, :categoria, :periodicidad)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':categoria', $categoria);
            $stmt->bindParam(':periodicidad', $periodicidad);
            
            if($stmt->execute()){
                http_response_code(201);
                echo json_encode(["message" => "Revista creada.", "id" => $db->lastInsertId()]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear la revista."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos o inválidos."]);
        }
        break;

    case 'PUT':
        require_role('admin');
        $data = get_json_input();
        
        $id_revista = isset($data['id_revista']) ? intval($data['id_revista']) : 0;
        $titulo = isset($data['titulo']) ? trim(strip_tags($data['titulo'])) : '';
        $categoria = isset($data['categoria']) ? trim(strip_tags($data['categoria'])) : '';
        $periodicidad = isset($data['periodicidad']) ? trim(strip_tags($data['periodicidad'])) : '';

        $valid_categories = ['Tecnología', 'Gastronomía', 'Turismo', 'Economía', 'Medicina', 'Deportes', 'Cultura', 'Moda', 'Ciencia', 'Automovilismo'];
        $valid_periodicities = ['Semanal', 'Quincenal', 'Mensual', 'Bimestral', 'Trimestral', 'Semestral', 'Anual'];

        if ($id_revista > 0 && !empty($titulo) && !empty($categoria) && !empty($periodicidad)) {
            // Validar longitud del título
            if (strlen($titulo) < 3 || strlen($titulo) > 150) {
                http_response_code(400);
                echo json_encode(["message" => "El título debe tener entre 3 y 150 caracteres."]);
                exit();
            }

            // Validar categoría admisible
            if (!in_array($categoria, $valid_categories)) {
                http_response_code(400);
                echo json_encode(["message" => "Categoría no permitida."]);
                exit();
            }

            // Validar periodicidad admisible
            if (!in_array($periodicidad, $valid_periodicities)) {
                http_response_code(400);
                echo json_encode(["message" => "Periodicidad no permitida."]);
                exit();
            }

            // Validar unicidad del título (excluyendo la revista actual)
            $check_query = "SELECT COUNT(*) FROM revista WHERE LOWER(titulo) = LOWER(:titulo) AND id_revista != :id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':titulo', $titulo);
            $check_stmt->bindParam(':id', $id_revista);
            $check_stmt->execute();
            if ($check_stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "Ya existe otra revista con el título '$titulo'."]);
                exit();
            }

            $query = "UPDATE revista SET titulo = :titulo, categoria = :categoria, periodicidad = :periodicidad WHERE id_revista = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_revista);
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':categoria', $categoria);
            $stmt->bindParam(':periodicidad', $periodicidad);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Revista actualizada."]);
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
