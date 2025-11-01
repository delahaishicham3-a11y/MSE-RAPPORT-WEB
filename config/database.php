<?php
namespace MSE;

use PDO;
use PDOException;
use Exception;

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbUrl = getenv('postgresql://mse_reports_okn4_user:P0Gmi9cmWXbeK5vPhsD8VS11BubfIHOd@dpg-d42pve0dl3ps73clm2gg-a/mse_reports_okn4');

        if ($dbUrl) {
            $dbParts = parse_url($dbUrl);
            $host = $dbParts['host'];
            $port = $dbParts['port'] ?? 5432;
            $dbname = ltrim($dbParts['path'], '/');
            $user = $dbParts['user'];
            $password = $dbParts['pass'];
        } else {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: 5432;
            $dbname = getenv('DB_NAME') ?: 'mse_reports';
            $user = getenv('DB_USER') ?: 'postgres';
            $password = getenv('DB_PASSWORD') ?: '';
        }

        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            $this->pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->initTables();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS reports (
            id SERIAL PRIMARY KEY,
            report_num VARCHAR(100),
            report_date DATE,
            address TEXT,
            c1_marque VARCHAR(100),
            c1_modele VARCHAR(100),
            c1_serie VARCHAR(100),
            c2_marque VARCHAR(100),
            c2_modele VARCHAR(100),
            c2_serie VARCHAR(100),
            etat_general TEXT,
            anomalies TEXT,
            travaux_realises TEXT,
            recommandations TEXT,
            urgence VARCHAR(20),
            intervenant VARCHAR(100),
            mesures JSONB,
            controles JSONB,
            releves JSONB,
            email_destinataire VARCHAR(255),
            email_sent BOOLEAN DEFAULT FALSE,
            email_sent_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS report_photos (
            id SERIAL PRIMARY KEY,
            report_id INTEGER REFERENCES reports(id) ON DELETE CASCADE,
            photo_data TEXT NOT NULL,
            photo_name VARCHAR(255),
            photo_type VARCHAR(50),
            photo_size INTEGER,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_report_num ON reports(report_num);
        CREATE INDEX IF NOT EXISTS idx_report_date ON reports(report_date);
        CREATE INDEX IF NOT EXISTS idx_urgence ON reports(urgence);
        CREATE INDEX IF NOT EXISTS idx_created_at ON reports(created_at);
        CREATE INDEX IF NOT EXISTS idx_email_sent ON reports(email_sent);
        CREATE INDEX IF NOT EXISTS idx_report_photos_report_id ON report_photos(report_id);
        ";

        $this->pdo->exec($sql);
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
