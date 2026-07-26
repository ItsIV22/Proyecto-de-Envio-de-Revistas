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
$role = $_SESSION['role'];
$persona_id = $_SESSION['persona_id'];

if ($role === 'admin') {
    // Estadísticas para Admin
    try {
        // Envíos Totales
        $qEnvios = "SELECT COUNT(*) FROM envio";
        $enviosTotal = $db->query($qEnvios)->fetchColumn();

        // Envíos Pendientes
        $qPendientes = "SELECT COUNT(*) FROM envio WHERE estado = 'Pendiente'";
        $enviosPendientes = $db->query($qPendientes)->fetchColumn();

        // Total Clientes (Personas con usuario cliente)
        $qPersonas = "SELECT COUNT(*) FROM persona";
        $personasTotal = $db->query($qPersonas)->fetchColumn();

        // Total Ejemplares
        $qEjemplares = "SELECT COUNT(*) FROM ejemplar";
        $ejemplaresTotal = $db->query($qEjemplares)->fetchColumn();

        echo json_encode([
            "role" => "admin",
            "stats" => [
                "envios_total" => (int)$enviosTotal,
                "envios_pendientes" => (int)$enviosPendientes,
                "personas_total" => (int)$personasTotal,
                "ejemplares_total" => (int)$ejemplaresTotal
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al obtener estadísticas de admin.", "error" => $e->getMessage()]);
    }
} else if ($role === 'cliente') {
    // Estadísticas para el Cliente Logueado
    try {
        // Total solicitudes
        $qTotal = "SELECT COUNT(*) FROM envio WHERE id_persona = :persona_id";
        $stmt = $db->prepare($qTotal);
        $stmt->bindParam(':persona_id', $persona_id);
        $stmt->execute();
        $total = $stmt->fetchColumn();

        // En tránsito
        $qTransito = "SELECT COUNT(*) FROM envio WHERE id_persona = :persona_id AND estado = 'En tránsito'";
        $stmt = $db->prepare($qTransito);
        $stmt->bindParam(':persona_id', $persona_id);
        $stmt->execute();
        $transito = $stmt->fetchColumn();

        // Entregados
        $qEntregados = "SELECT COUNT(*) FROM envio WHERE id_persona = :persona_id AND estado = 'Entregado'";
        $stmt = $db->prepare($qEntregados);
        $stmt->bindParam(':persona_id', $persona_id);
        $stmt->execute();
        $entregados = $stmt->fetchColumn();

        // Pendientes
        $qPendientes = "SELECT COUNT(*) FROM envio WHERE id_persona = :persona_id AND estado = 'Pendiente'";
        $stmt = $db->prepare($qPendientes);
        $stmt->bindParam(':persona_id', $persona_id);
        $stmt->execute();
        $pendientes = $stmt->fetchColumn();

        echo json_encode([
            "role" => "cliente",
            "stats" => [
                "solicitudes_total" => (int)$total,
                "solicitudes_transito" => (int)$transito,
                "solicitudes_entregadas" => (int)$entregados,
                "solicitudes_pendientes" => (int)$pendientes
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al obtener estadísticas del cliente.", "error" => $e->getMessage()]);
    }
} else {
    http_response_code(403);
    echo json_encode(["message" => "Acceso denegado."]);
}
?>
