<?php

namespace App\Controllers;

use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function register(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['email'], $input['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Email and password required']);
                return;
            }

            $result = $this->authService->register(
                $input['email'],
                $input['password'],
                $input['role'] ?? 'user'
            );

            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function login(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['email'], $input['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Email and password required']);
                return;
            }

            $result = $this->authService->login($input['email'], $input['password']);

            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function logout(): void
    {
        // For JWT tokens, logout is handled client-side by removing the token
        echo json_encode(['message' => 'Logged out successfully']);
    }

    public function me(): void
    {
        try {
            $user = $this->authService->requireAuth();
            $userData = $this->authService->getCurrentUser($_SERVER['HTTP_AUTHORIZATION'] ?? '');

            if (!$userData) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                return;
            }

            echo json_encode(['user' => $userData]);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}