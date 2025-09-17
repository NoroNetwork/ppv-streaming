<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\StreamService;

class StreamController
{
    private AuthService $authService;
    private StreamService $streamService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->streamService = new StreamService();
    }

    public function list(): void
    {
        try {
            $streams = $this->streamService->getPublicStreams();
            echo json_encode(['streams' => $streams]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function get(string $id): void
    {
        try {
            $stream = $this->streamService->getStream($id);

            if (!$stream) {
                http_response_code(404);
                echo json_encode(['error' => 'Stream not found']);
                return;
            }

            echo json_encode(['stream' => $stream]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function checkAccess(string $id): void
    {
        try {
            $user = $this->authService->requireAuth();
            $hasAccess = $this->streamService->hasAccess($user['user_id'], $id);

            $response = ['has_access' => $hasAccess];

            if ($hasAccess) {
                $streamUrl = $this->streamService->getStreamUrl($id);
                if ($streamUrl) {
                    $response['stream_url'] = $streamUrl;
                }
            }

            echo json_encode($response);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}