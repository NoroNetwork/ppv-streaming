<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load testing environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env.testing');
$dotenv->load();

// Ensure we're in testing mode
if ($_ENV['APP_ENV'] !== 'testing') {
    throw new RuntimeException('Tests must be run with APP_ENV=testing');
}

// Database setup for testing
class TestDatabase
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'] ?? 3306,
                $_ENV['DB_NAME']
            );

            self::$connection = new PDO(
                $dsn,
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$connection;
    }

    public static function setupTestDatabase(): void
    {
        $pdo = self::getConnection();

        // Read and execute schema files
        $schemaFiles = [
            __DIR__ . '/../database/schema.sql',
            __DIR__ . '/../database/security-tables.sql'
        ];

        foreach ($schemaFiles as $file) {
            if (file_exists($file)) {
                $sql = file_get_contents($file);

                // Split by semicolon and execute each statement
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function($stmt) {
                        return !empty($stmt) && !str_starts_with($stmt, '--');
                    }
                );

                foreach ($statements as $statement) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore table exists errors in testing
                        if (!str_contains($e->getMessage(), 'already exists')) {
                            throw $e;
                        }
                    }
                }
            }
        }
    }

    public static function cleanDatabase(): void
    {
        $pdo = self::getConnection();

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Disable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Truncate all tables
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }

        // Re-enable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Insert default admin user for testing
        $pdo->exec("
            INSERT INTO users (email, password, role, created_at) VALUES
            ('admin@test.com', '$2y$04$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()),
            ('user@test.com', '$2y$04$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NOW())
        ");
    }
}

// Setup test database on bootstrap
TestDatabase::setupTestDatabase();