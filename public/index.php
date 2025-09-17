<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Core\Router;
use App\Core\Security;
use App\Controllers\UserController;
use App\Controllers\AdminController;
use App\Controllers\StreamController;
use App\Controllers\PaymentController;
use App\Controllers\AuthController;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Apply security headers
Security::secureHeaders();

// Initialize application
$app = new Application();

// Initialize router
$router = new Router();

// Authentication routes
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me']);

// User portal routes
$router->get('/', [UserController::class, 'index']);
$router->get('/stream/{id}', [UserController::class, 'stream']);
$router->get('/payment/{streamId}', [PaymentController::class, 'showPayment']);
$router->post('/api/payment/create-intent', [PaymentController::class, 'createPaymentIntent']);
$router->post('/api/payment/webhook', [PaymentController::class, 'handleWebhook']);

// Stream API routes
$router->get('/api/streams', [StreamController::class, 'list']);
$router->get('/api/streams/{id}', [StreamController::class, 'get']);
$router->get('/api/streams/{id}/access', [StreamController::class, 'checkAccess']);

// Admin panel routes
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/streams', [AdminController::class, 'streams']);
$router->post('/admin/api/streams', [AdminController::class, 'createStream']);
$router->put('/admin/api/streams/{id}', [AdminController::class, 'updateStream']);
$router->delete('/admin/api/streams/{id}', [AdminController::class, 'deleteStream']);
$router->get('/admin/api/streams/{id}/stats', [AdminController::class, 'getStreamStats']);
$router->get('/admin/api/mediamtx/status', [AdminController::class, 'getMediaMTXStatus']);

// Handle CORS for API requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $app->handleCors();
    exit(0);
}

// Route the request
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Exception $e) {
    $app->handleError($e);
}