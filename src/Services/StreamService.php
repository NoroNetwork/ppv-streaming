<?php

namespace App\Services;

use App\Core\Database;

class StreamService
{
    public function getPublicStreams(): array
    {
        $stmt = Database::prepare(
            'SELECT id, title, description, price, currency, status, scheduled_start
             FROM streams
             WHERE status IN ("active", "inactive")
             ORDER BY scheduled_start DESC, created_at DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getStream(string $id): ?array
    {
        $stmt = Database::prepare(
            'SELECT * FROM streams WHERE id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function hasAccess(string $userId, string $streamId): bool
    {
        $stmt = Database::prepare(
            'SELECT id FROM stream_access
             WHERE user_id = ? AND stream_id = ?
             AND (expires_at IS NULL OR expires_at > NOW())'
        );
        $stmt->execute([$userId, $streamId]);

        return (bool) $stmt->fetch();
    }

    public function getStreamUrl(string $streamId): ?string
    {
        $stream = $this->getStream($streamId);

        if (!$stream || $stream['status'] !== 'active') {
            return null;
        }

        return $stream['hls_url'];
    }

    public function createStream(array $data): string
    {
        $stmt = Database::prepare(
            'INSERT INTO streams (title, description, price, currency, stream_key, scheduled_start)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $streamKey = $this->generateStreamKey();

        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['price'] ?? 0.00,
            $data['currency'] ?? 'USD',
            $streamKey,
            $data['scheduled_start'] ?? null
        ]);

        return Database::lastInsertId();
    }

    public function updateStream(string $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['title', 'description', 'price', 'currency', 'status', 'scheduled_start'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;

        $stmt = Database::prepare(
            'UPDATE streams SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?'
        );

        return $stmt->execute($values);
    }

    public function deleteStream(string $id): bool
    {
        $stmt = Database::prepare('DELETE FROM streams WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getStreamStats(string $id): array
    {
        // Get latest stats
        $stmt = Database::prepare(
            'SELECT * FROM stream_stats
             WHERE stream_id = ?
             ORDER BY recorded_at DESC
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $latestStats = $stmt->fetch() ?: [];

        // Get total access count
        $stmt = Database::prepare(
            'SELECT COUNT(*) as total_purchases, SUM(amount_paid) as total_revenue
             FROM stream_access
             WHERE stream_id = ?'
        );
        $stmt->execute([$id]);
        $totals = $stmt->fetch();

        return array_merge($latestStats, $totals);
    }

    private function generateStreamKey(): string
    {
        return 'stream_' . bin2hex(random_bytes(16));
    }

    public function recordViewerCount(string $streamId, int $viewerCount): void
    {
        // Update or insert today's stats
        $stmt = Database::prepare(
            'INSERT INTO stream_stats (stream_id, viewer_count, peak_viewers, recorded_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
             viewer_count = VALUES(viewer_count),
             peak_viewers = GREATEST(peak_viewers, VALUES(peak_viewers))'
        );
        $stmt->execute([$streamId, $viewerCount, $viewerCount]);
    }
}