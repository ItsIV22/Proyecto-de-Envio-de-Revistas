<?php
/**
 * Archivo de configuración de la Base de Datos.
 * Gestiona la conexión PDO a PostgreSQL.
 */

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __unique_construct() {
        // Variables de entorno para despliegue (ej. Render) o fallbacks para entorno local (Laragon)
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = getenv('DB_PORT') ?: '5432';
        $this->db_name = getenv('DB_NAME') ?: 'revistas_db';
        $this->username = getenv('DB_USER') ?: 'postgres';
        $this->password = getenv('DB_PASSWORD') ?: 'admin';
    }

    public function __construct() {
        $this->__unique_construct();
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Configurar PDO para que lance excepciones en caso de error
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Para asegurar que los strings se manejen en formato esperado (aunque PostgreSQL lo hace bien por defecto)
            // $this->conn->exec("SET NAMES 'utf8'"); // En PostgreSQL no siempre es necesario con PDO si el encoding es utf8, pero es buena práctica en MySQL.
            
        } catch(PDOException $exception) {
            // En producción, no mostrar el error detallado, aquí se retorna un JSON de error si falla la conexión
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(["message" => "Error de conexión a la base de datos", "error" => $exception->getMessage()]);
            exit;
        }

        return $this->conn;
    }
}
?>
