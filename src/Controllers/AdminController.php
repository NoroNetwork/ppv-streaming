<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\StreamService;
use App\Services\MediaMTXService;
use League\Plates\Engine;

class AdminController
{
    private AuthService $authService;
    private StreamService $streamService;
    private MediaMTXService $mediaMTXService;
    private Engine $templates;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->streamService = new StreamService();
        $this->mediaMTXService = new MediaMTXService();
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function dashboard(): void
    {
        try {
            $this->authService->requireRole('admin');

            $stats = [
                'total_streams' => $this->getTotalStreams(),
                'active_streams' => $this->getActiveStreams(),
                'total_revenue' => $this->getTotalRevenue(),
                'total_users' => $this->getTotalUsers(),
            ];

            echo $this->templates->render('admin/dashboard', ['stats' => $stats]);
        } catch (\Exception $e) {
            echo $this->templates->render('error', ['message' => $e->getMessage()]);
        }
    }

    public function streams(): void
    {
        try {
            $this->authService->requireRole('admin');

            $streams = $this->getAllStreams();
            echo $this->templates->render('admin/streams', ['streams' => $streams]);
        } catch (\Exception $e) {
            echo $this->templates->render('error', ['message' => $e->getMessage()]);
        }
    }

    public function createStream(): void
    {
        try {
            $this->authService->requireRole('admin');

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Title is required']);
                return;
            }

            $streamId = $this->streamService->createStream($input);
            $stream = $this->streamService->getStream($streamId);

            // Create MediaMTX stream configuration
            $mediaMTXConfig = $this->mediaMTXService->createStream($stream['stream_key']);

            // Update stream with MediaMTX URLs
            $this->streamService->updateStream($streamId, [
                'rtmp_url' => $mediaMTXConfig['rtmp_url'],
                'hls_url' => $mediaMTXConfig['hls_url']
            ]);

            $updatedStream = $this->streamService->getStream($streamId);

            echo json_encode(['stream' => $updatedStream]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function updateStream(string $id): void
    {
        try {
            $this->authService->requireRole('admin');

            $input = json_decode(file_get_contents('php://input'), true);
            $success = $this->streamService->updateStream($id, $input);

            if (!$success) {
                http_response_code(400);
                echo json_encode(['error' => 'Update failed']);
                return;
            }

            $stream = $this->streamService->getStream($id);
            echo json_encode(['stream' => $stream]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deleteStream(string $id): void
    {
        try {
            $this->authService->requireRole('admin');

            $stream = $this->streamService->getStream($id);
            if ($stream) {
                $this->mediaMTXService->deleteStream($stream['stream_key']);
            }

            $success = $this->streamService->deleteStream($id);

            if (!$success) {
                http_response_code(400);
                echo json_encode(['error' => 'Delete failed']);
                return;
            }

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getStreamStats(string $id): void
    {
        try {
            $this->authService->requireRole('admin');

            $stats = $this->streamService->getStreamStats($id);
            $stream = $this->streamService->getStream($id);

            if ($stream) {
                // Get live stats from MediaMTX
                $liveStats = $this->mediaMTXService->getStreamStats($stream['stream_key']);
                $stats = array_merge($stats, $liveStats);
            }

            echo json_encode(['stats' => $stats]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getMediaMTXStatus(): void
    {
        try {
            $this->authService->requireRole('admin');

            $status = $this->mediaMTXService->getServerStatus();
            echo json_encode(['status' => $status]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function getTotalStreams(): int
    {
        $stmt = \App\Core\Database::prepare('SELECT COUNT(*) as count FROM streams');
        $stmt->execute();
        return $stmt->fetch()['count'];
    }

    private function getActiveStreams(): int
    {
        $stmt = \App\Core\Database::prepare('SELECT COUNT(*) as count FROM streams WHERE status = "active"');
        $stmt->execute();
        return $stmt->fetch()['count'];
    }

    private function getTotalRevenue(): float
    {
        $stmt = \App\Core\Database::prepare('SELECT SUM(amount_paid) as total FROM stream_access');
        $stmt->execute();
        return $stmt->fetch()['total'] ?? 0.0;
    }

    private function getTotalUsers(): int
    {
        $stmt = \App\Core\Database::prepare('SELECT COUNT(*) as count FROM users');
        $stmt->execute();
        return $stmt->fetch()['count'];
    }

    private function getAllStreams(): array
    {
        $stmt = \App\Core\Database::prepare(
            'SELECT s.*,
             COUNT(sa.id) as total_purchases,
             SUM(sa.amount_paid) as total_revenue
             FROM streams s
             LEFT JOIN stream_access sa ON s.id = sa.stream_id
             GROUP BY s.id
             ORDER BY s.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}