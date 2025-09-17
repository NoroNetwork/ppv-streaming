<?php

namespace App\Core;

class Application
{
    public function __construct()
    {
        $this->setupErrorHandling();
        $this->setupCors();
    }

    private function setupErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $_ENV['DEBUG'] ?? '0');

        set_exception_handler([$this, 'handleError']);
        set_error_handler([$this, 'handleError']);
    }

    private function setupCors(): void
    {
        $allowedOrigins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
    }

    public function handleCors(): void
    {
        http_response_code(200);
    }

    public function handleError($error): void
    {
        $isDebug = $_ENV['DEBUG'] ?? false;

        if ($error instanceof \Exception) {
            $message = $error->getMessage();
            $code = $error->getCode() ?: 500;
        } else {
            $message = 'Internal Server Error';
            $code = 500;
        }

        http_response_code($code);
        header('Content-Type: application/json');

        $response = ['error' => $message];

        if ($isDebug && $error instanceof \Exception) {
            $response['trace'] = $error->getTraceAsString();
            $response['file'] = $error->getFile();
            $response['line'] = $error->getLine();
        }

        echo json_encode($response);
        exit;
    }
}