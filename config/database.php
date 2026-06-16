<?php
/**
 * PDO database connection (singleton).
 * Update these credentials for your environment, or set as env vars.
 */

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Credentials resolution order:
            //   1. config/local.php  (gitignored — per-server overrides; survives `git reset --hard`)
            //   2. environment variables
            //   3. local development defaults
            $local = [];
            $localFile = __DIR__ . '/local.php';
            if (is_file($localFile)) {
                $loaded = require $localFile;
                if (is_array($loaded)) {
                    $local = $loaded;
                }
            }

            $host = $local['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
            $name = $local['DB_NAME'] ?? (getenv('DB_NAME') ?: 'giftdekedekho');
            $user = $local['DB_USER'] ?? (getenv('DB_USER') ?: 'root');
            $pass = $local['DB_PASS'] ?? (getenv('DB_PASS') ?: '');
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (ENVIRONMENT === 'development') {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('Database connection failed. Please try again later.');
            }
        }

        return self::$instance;
    }
}
