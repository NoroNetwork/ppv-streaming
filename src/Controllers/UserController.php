<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\StreamService;
use League\Plates\Engine;

class UserController
{
    private AuthService $authService;
    private StreamService $streamService;
    private Engine $templates;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->streamService = new StreamService();
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function index(): void
    {
        try {
            $streams = $this->streamService->getPublicStreams();
            echo $this->templates->render('user/index', ['streams' => $streams]);
        } catch (\Exception $e) {
            echo $this->templates->render('error', ['message' => $e->getMessage()]);
        }
    }

    public function stream(string $id): void
    {
        try {
            $stream = $this->streamService->getStream($id);

            if (!$stream) {
                http_response_code(404);
                echo $this->templates->render('error', ['message' => 'Stream not found']);
                return;
            }

            // Check if user has access
            $hasAccess = false;
            $user = null;

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if ($authHeader) {
                try {
                    $tokenData = $this->authService->verifyToken(
                        str_replace('Bearer ', '', $authHeader)
                    );
                    $user = $this->authService->getCurrentUser($authHeader);
                    $hasAccess = $this->streamService->hasAccess($user['id'], $id);
                } catch (\Exception $e) {
                    // User not authenticated, continue without access
                }
            }

            echo $this->templates->render('user/stream', [
                'stream' => $stream,
                'hasAccess' => $hasAccess,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            echo $this->templates->render('error', ['message' => $e->getMessage()]);
        }
    }
}