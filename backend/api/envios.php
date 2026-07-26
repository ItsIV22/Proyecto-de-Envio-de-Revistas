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
$role = $_SESSION['role'];
$persona_id = $_SESSION['persona_id'];

switch ($method) {
    case 'GET':
        // JOIN para obtener datos legibles
        $query = "
            SELECT 
                en.id_envio, en.fecha_despacho, en.estado, en.numero_guia,
                ej.numero_edicion, r.titulo as revista_titulo,
                p.nombre_completo, p.direccion_envio, p.ciudad, p.id_persona,
                a.nombre_agencia, a.id_agencia
            FROM envio en
            JOIN ejemplar ej ON en.id_ejemplar = ej.id_ejemplar
            JOIN revista r ON ej.id_revista = r.id_revista
            JOIN persona p ON en.id_persona = p.id_persona
            LEFT JOIN agencia_transporte a ON en.id_agencia = a.id_agencia
        ";

        if ($role === 'cliente') {
            $query .= " WHERE en.id_persona = :persona_id";
        }
        
        $query .= " ORDER BY en.id_envio DESC";

        $stmt = $db->prepare($query);
        if ($role === 'cliente') {
            $stmt->bindParam(':persona_id', $persona_id);
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        break;

    case 'POST':
        $data = get_json_input();
        
        if ($role === 'admin') {
            // Admin puede crear envíos para cualquier persona y asignar agencia inmediatamente
            if(!empty($data['id_ejemplar']) && !empty($data['id_persona'])) {
                $query = "INSERT INTO envio (id_ejemplar, id_persona, id_agencia, fecha_despacho, estado, numero_guia) 
                          VALUES (:ejemplar, :persona, :agencia, :fecha, :estado, :guia)";
                $stmt = $db->prepare($query);
                
                // Si estado no se envía, por defecto es Pendiente
                $estado = !empty($data['estado']) ? $data['estado'] : 'Pendiente';
                $agencia = !empty($data['id_agencia']) ? $data['id_agencia'] : null;
                $fecha = !empty($data['fecha_despacho']) ? $data['fecha_despacho'] : null;
                $guia = !empty($data['numero_guia']) ? $data['numero_guia'] : null;

                $stmt->bindParam(':ejemplar', $data['id_ejemplar']);
                $stmt->bindParam(':persona', $data['id_persona']);
                $stmt->bindParam(':agencia', $agencia);
                $stmt->bindParam(':fecha', $fecha);
                $stmt->bindParam(':estado', $estado);
                $stmt->bindParam(':guia', $guia);
                
                if($stmt->execute()){
                    http_response_code(201);
                    echo json_encode(["message" => "Envío creado por admin."]);
                } else {
                    http_response_code(503);
                    echo json_encode(["message" => "Error al crear envío."]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Faltan datos requeridos (ejemplar y persona)."]);
            }
        } else if ($role === 'cliente') {
            // Cliente solo puede solicitar para sí mismo, estado inicial 'Pendiente'
            if(!empty($data['id_ejemplar'])) {
                $query = "INSERT INTO envio (id_ejemplar, id_persona, estado) 
                          VALUES (:ejemplar, :persona, 'Pendiente')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':ejemplar', $data['id_ejemplar']);
                $stmt->bindParam(':persona', $persona_id);
                
                if($stmt->execute()){
                    http_response_code(201);
                    echo json_encode(["message" => "Solicitud de envío registrada exitosamente."]);
                } else {
                    http_response_code(503);
                    echo json_encode(["message" => "Error al procesar solicitud."]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Debe seleccionar un ejemplar."]);
            }
        }
        break;

    case 'PUT':
    case 'PATCH':
        require_role('admin'); // Solo admin actualiza estados y agencias
        $data = get_json_input();
        
        if(!empty($data['id_envio']) && !empty($data['estado'])) {
            // Verificar el estado actual del envío en la BD
            $checkQuery = "SELECT estado FROM envio WHERE id_envio = :id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $data['id_envio']);
            $checkStmt->execute();
            $estadoActual = $checkStmt->fetchColumn();
            
            if ($estadoActual === 'Entregado') {
                http_response_code(400);
                echo json_encode(["message" => "Un envío con estado 'Entregado' no puede ser modificado."]);
                exit();
            }

            // Actualización parcial (estado, agencia, etc)
            $query = "UPDATE envio SET estado = :estado, id_agencia = :agencia, fecha_despacho = :fecha, numero_guia = :guia WHERE id_envio = :id";
            $stmt = $db->prepare($query);
            
            $agencia = !empty($data['id_agencia']) ? $data['id_agencia'] : null;
            $fecha = !empty($data['fecha_despacho']) ? $data['fecha_despacho'] : null;
            $guia = !empty($data['numero_guia']) ? $data['numero_guia'] : null;

            $stmt->bindParam(':id', $data['id_envio']);
            $stmt->bindParam(':estado', $data['estado']);
            $stmt->bindParam(':agencia', $agencia);
            $stmt->bindParam(':fecha', $fecha);
            $stmt->bindParam(':guia', $guia);
            
            if($stmt->execute()){
                echo json_encode(["message" => "Estado de envío actualizado."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Se requiere id_envio y estado."]);
        }
        break;

    case 'DELETE':
        require_role('admin');
        $id = isset($_GET['id']) ? $_GET['id'] : die();
        $query = "DELETE FROM envio WHERE id_envio = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if($stmt->execute()){
            echo json_encode(["message" => "Envío eliminado."]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "Error al eliminar."]);
        }
        break;
}
?>
