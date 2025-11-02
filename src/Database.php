<?php
namespace MSE;

use PDO;
use PDOException;

class Database {
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $databaseUrl = getenv('DATABASE_URL');

        // --- Connexion via DATABASE_URL (ex: Heroku, Railway)
        if ($databaseUrl) {
            $db = parse_url($databaseUrl);
            $host = $db['host'];
            $port = $db['port'] ?? 5432;
            $dbname = ltrim($db['path'], '/');
            $user = $db['user'];
            $pass = $db['pass'];
            $sslmode = 'require'; // souvent nécessaire sur les plateformes cloud
        } 
        // --- Sinon, fallback vers les variables locales classiques
        else {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: 5432;
            $dbname = getenv('DB_NAME') ?: 'test';
            $user = getenv('DB_USER') ?: 'postgres';
            $pass = getenv('DB_PASS') ?: '';
            $sslmode = getenv('DB_SSLMODE') ?: 'prefer';
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new \Exception('❌ Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Retourne l’instance unique (Singleton)
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne la connexion PDO active
     */
    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * Teste la connexion à la base
     */
    public function testConnection(): bool {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // --- Empêche le clonage et la désérialisation du Singleton
    private function __clone() {}
    public function __wakeup() {}
}
