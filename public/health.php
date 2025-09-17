<?php

// Simple health check endpoint
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => []
];

// Check database connection
try {
    if (isset($_ENV['DB_HOST'])) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'],
            $_ENV['DB_NAME']
        );

        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $health['checks']['database'] = 'healthy';
    } else {
        $health['checks']['database'] = 'not_configured';
    }
} catch (Exception $e) {
    $health['checks']['database'] = 'unhealthy';
    $health['status'] = 'degraded';
}

// Check Redis connection
try {
    if (isset($_ENV['REDIS_HOST']) && extension_loaded('redis')) {
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT'] ?? 6379, 5);
        $redis->ping();
        $health['checks']['redis'] = 'healthy';
        $redis->close();
    } else {
        $health['checks']['redis'] = 'not_configured';
    }
} catch (Exception $e) {
    $health['checks']['redis'] = 'unhealthy';
    $health['status'] = 'degraded';
}

// Check file permissions
if (is_writable(__DIR__)) {
    $health['checks']['filesystem'] = 'healthy';
} else {
    $health['checks']['filesystem'] = 'readonly';
    $health['status'] = 'degraded';
}

echo json_encode($health, JSON_PRETTY_PRINT);