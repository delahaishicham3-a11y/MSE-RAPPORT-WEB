<?php
namespace MSE;

use PDO;
use PDOException;

class Database {
    private $connection;

    public function __construct() {
        $databaseUrl = getenv('DATABASE_URL');
        if (!$databaseUrl) {
            throw new \Exception('DATABASE_URL not configured in environment');
        }

        $db = parse_url($databaseUrl);
        $host = $db['host'];
        $port = $db['port'] ?? 5432;
        $dbname = ltrim($db['path'], '/');
        $user = $db['user'];
        $pass = $db['pass'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->connection;
    }
}
