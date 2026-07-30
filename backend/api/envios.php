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
                $id_ejemplar = intval($data['id_ejemplar']);
                $id_persona = intval($data['id_persona']);
                $estado = !empty($data['estado']) ? trim($data['estado']) : 'Pendiente';
                
                // Validar estado
                $valid_estados = ['Pendiente', 'En tránsito', 'Entregado'];
                if (!in_array($estado, $valid_estados)) {
                    http_response_code(400);
                    echo json_encode(["message" => "Estado de envío no válido."]);
                    exit();
                }

                // 1. Evitar duplicidad de envíos: mismo ejemplar para la misma persona
                $checkQuery = "SELECT COUNT(*) FROM envio WHERE id_persona = :persona AND id_ejemplar = :ejemplar";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':persona', $id_persona);
                $checkStmt->bindParam(':ejemplar', $id_ejemplar);
                $checkStmt->execute();
                if ($checkStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(["message" => "Esta persona ya tiene un envío registrado para este ejemplar."]);
                    exit();
                }

                // 2. Validar campos condicionales según el estado
                $agencia = !empty($data['id_agencia']) ? intval($data['id_agencia']) : null;
                $fecha = !empty($data['fecha_despacho']) ? trim($data['fecha_despacho']) : null;
                $guia = !empty($data['numero_guia']) ? trim(strip_tags($data['numero_guia'])) : null;

                if ($estado === 'Pendiente') {
                    $agencia = null;
                    $fecha = null;
                    $guia = null;
                } else {
                    if (empty($agencia) || empty($fecha) || empty($guia)) {
                        http_response_code(400);
                        echo json_encode(["message" => "Para envíos en tránsito o entregados, la agencia, fecha de despacho y número de guía son obligatorios."]);
                        exit();
                    }

                    // Validar formato de fecha y que no sea futura
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                        http_response_code(400);
                        echo json_encode(["message" => "Formato de fecha de despacho inválido. Use AAAA-MM-DD."]);
                        exit();
                    }
                    if (strtotime($fecha) > time() + 86400) {
                        http_response_code(400);
                        echo json_encode(["message" => "La fecha de despacho no puede ser en el futuro."]);
                        exit();
                    }

                    // Validar que la fecha de despacho no sea anterior a la publicación del ejemplar
                    $pubQuery = "SELECT fecha_publicacion FROM ejemplar WHERE id_ejemplar = :ejemplar";
                    $pubStmt = $db->prepare($pubQuery);
                    $pubStmt->bindParam(':ejemplar', $id_ejemplar);
                    $pubStmt->execute();
                    $pubDate = $pubStmt->fetchColumn();
                    if ($pubDate && strtotime($fecha) < strtotime($pubDate)) {
                        http_response_code(400);
                        echo json_encode(["message" => "La fecha de despacho no puede ser anterior a la fecha de publicación del ejemplar ($pubDate)."]);
                        exit();
                    }

                    // Validar unicidad del número de guía
                    $guiaQuery = "SELECT COUNT(*) FROM envio WHERE numero_guia = :guia";
                    $guiaStmt = $db->prepare($guiaQuery);
                    $guiaStmt->bindParam(':guia', $guia);
                    $guiaStmt->execute();
                    if ($guiaStmt->fetchColumn() > 0) {
                        http_response_code(400);
                        echo json_encode(["message" => "El número de guía '$guia' ya existe."]);
                        exit();
                    }
                }

                $query = "INSERT INTO envio (id_ejemplar, id_persona, id_agencia, fecha_despacho, estado, numero_guia) 
                          VALUES (:ejemplar, :persona, :agencia, :fecha, :estado, :guia)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':ejemplar', $id_ejemplar);
                $stmt->bindParam(':persona', $id_persona);
                $stmt->bindValue(':agencia', $agencia, $agencia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':fecha', $fecha, $fecha === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindParam(':estado', $estado);
                $stmt->bindValue(':guia', $guia, $guia === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                
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
                $id_ejemplar = intval($data['id_ejemplar']);
                
                // Evitar duplicidad
                $checkQuery = "SELECT COUNT(*) FROM envio WHERE id_persona = :persona AND id_ejemplar = :ejemplar";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':persona', $persona_id);
                $checkStmt->bindParam(':ejemplar', $id_ejemplar);
                $checkStmt->execute();
                if ($checkStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(["message" => "Ya has solicitado o recibido un envío para este ejemplar."]);
                    exit();
                }

                $query = "INSERT INTO envio (id_ejemplar, id_persona, estado) 
                          VALUES (:ejemplar, :persona, 'Pendiente')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':ejemplar', $id_ejemplar);
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
        
        $id_envio = isset($data['id_envio']) ? intval($data['id_envio']) : 0;
        $estado = isset($data['estado']) ? trim($data['estado']) : '';

        if($id_envio > 0 && !empty($estado)) {
            // Verificar el estado actual del envío en la BD
            $checkQuery = "
                SELECT en.estado, en.id_ejemplar, ej.fecha_publicacion 
                FROM envio en 
                JOIN ejemplar ej ON en.id_ejemplar = ej.id_ejemplar 
                WHERE en.id_envio = :id
            ";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id_envio);
            $checkStmt->execute();
            $envioInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$envioInfo) {
                http_response_code(404);
                echo json_encode(["message" => "El envío especificado no existe."]);
                exit();
            }

            $estadoActual = $envioInfo['estado'];
            $id_ejemplar = $envioInfo['id_ejemplar'];
            $fecha_publicacion = $envioInfo['fecha_publicacion'];
            
            if ($estadoActual === 'Entregado') {
                http_response_code(400);
                echo json_encode(["message" => "Un envío con estado 'Entregado' no puede ser modificado."]);
                exit();
            }

            // Validar estado
            $valid_estados = ['Pendiente', 'En tránsito', 'Entregado'];
            if (!in_array($estado, $valid_estados)) {
                http_response_code(400);
                echo json_encode(["message" => "Estado de envío no válido."]);
                exit();
            }

            // Impedir retrocesos de estado (ej. de En tránsito a Pendiente)
            if ($estadoActual === 'En tránsito' && $estado === 'Pendiente') {
                http_response_code(400);
                echo json_encode(["message" => "No se puede cambiar el estado de un envío 'En tránsito' de vuelta a 'Pendiente'."]);
                exit();
            }

            $agencia = !empty($data['id_agencia']) ? intval($data['id_agencia']) : null;
            $fecha = !empty($data['fecha_despacho']) ? trim($data['fecha_despacho']) : null;
            $guia = !empty($data['numero_guia']) ? trim(strip_tags($data['numero_guia'])) : null;

            if ($estado === 'Pendiente') {
                $agencia = null;
                $fecha = null;
                $guia = null;
            } else {
                if (empty($agencia) || empty($fecha) || empty($guia)) {
                    http_response_code(400);
                    echo json_encode(["message" => "Para envíos en tránsito o entregados, la agencia, fecha de despacho y número de guía son obligatorios."]);
                    exit();
                }

                // Validar fecha despacho no futura
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    http_response_code(400);
                    echo json_encode(["message" => "Formato de fecha de despacho inválido. Use AAAA-MM-DD."]);
                    exit();
                }
                if (strtotime($fecha) > time() + 86400) {
                    http_response_code(400);
                    echo json_encode(["message" => "La fecha de despacho no puede ser en el futuro."]);
                    exit();
                }

                // Validar fecha despacho >= publicación ejemplar
                if (strtotime($fecha) < strtotime($fecha_publicacion)) {
                    http_response_code(400);
                    echo json_encode(["message" => "La fecha de despacho no puede ser anterior a la fecha de publicación del ejemplar ($fecha_publicacion)."]);
                    exit();
                }

                // Validar unicidad de guía
                $guiaQuery = "SELECT COUNT(*) FROM envio WHERE numero_guia = :guia AND id_envio != :id";
                $guiaStmt = $db->prepare($guiaQuery);
                $guiaStmt->bindParam(':guia', $guia);
                $guiaStmt->bindParam(':id', $id_envio);
                $guiaStmt->execute();
                if ($guiaStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(["message" => "El número de guía '$guia' ya ha sido utilizado."]);
                    exit();
                }
            }

            // Actualización parcial
            $query = "UPDATE envio SET estado = :estado, id_agencia = :agencia, fecha_despacho = :fecha, numero_guia = :guia WHERE id_envio = :id";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':id', $id_envio);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindValue(':agencia', $agencia, $agencia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':fecha', $fecha, $fecha === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':guia', $guia, $guia === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
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
