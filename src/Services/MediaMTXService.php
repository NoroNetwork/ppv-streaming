<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MediaMTXService
{
    private Client $client;
    private string $apiUrl;
    private string $apiToken;

    public function __construct()
    {
        $this->apiUrl = $_ENV['MEDIAMTX_API_URL'];
        $this->apiToken = $_ENV['MEDIAMTX_API_TOKEN'] ?? '';

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiToken
            ]
        ]);
    }

    public function createStream(string $streamKey): array
    {
        try {
            $config = [
                'name' => $streamKey,
                'source' => 'publisher',
                'sourceProtocol' => 'rtmp',
                'disablePublisherOverride' => false,
                'fallback' => '',
                'srtReadPassphrase' => '',
                'srtPublishPassphrase' => '',
                'recordPath' => '',
                'recordFormat' => 'fmp4',
                'recordPartDuration' => '1s',
                'recordSegmentDuration' => '1h',
                'recordDeleteAfter' => '24h',
                'overridePublisher' => false,
                'srtReadPassphrase' => '',
                'srtPublishPassphrase' => ''
            ];

            $response = $this->client->post("/config/paths/$streamKey", [
                'json' => $config
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'rtmp_url' => $this->buildRTMPUrl($streamKey),
                'hls_url' => $this->buildHLSUrl($streamKey),
                'stream_key' => $streamKey,
                'config' => $data
            ];
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 409) {
                // Stream already exists, return URLs
                return [
                    'rtmp_url' => $this->buildRTMPUrl($streamKey),
                    'hls_url' => $this->buildHLSUrl($streamKey),
                    'stream_key' => $streamKey
                ];
            }

            throw new \Exception('Failed to create stream in MediaMTX: ' . $e->getMessage());
        }
    }

    public function deleteStream(string $streamKey): bool
    {
        try {
            $this->client->delete("/config/paths/$streamKey");
            return true;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                return true; // Already deleted
            }
            throw new \Exception('Failed to delete stream from MediaMTX: ' . $e->getMessage());
        }
    }

    public function getStreamStats(string $streamKey): array
    {
        try {
            $response = $this->client->get("/paths/$streamKey");
            $data = json_decode($response->getBody(), true);

            return [
                'is_live' => $data['sourceReady'] ?? false,
                'viewers' => count($data['readers'] ?? []),
                'bytes_sent' => $data['bytesSent'] ?? 0,
                'bytes_received' => $data['bytesReceived'] ?? 0,
                'created_at' => $data['created'] ?? null,
                'last_request' => $data['lastRequest'] ?? null
            ];
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                return [
                    'is_live' => false,
                    'viewers' => 0,
                    'bytes_sent' => 0,
                    'bytes_received' => 0
                ];
            }

            throw new \Exception('Failed to get stream stats from MediaMTX: ' . $e->getMessage());
        }
    }

    public function getServerStatus(): array
    {
        try {
            $response = $this->client->get('/');
            $data = json_decode($response->getBody(), true);

            return [
                'version' => $data['version'] ?? 'unknown',
                'uptime' => $data['uptime'] ?? 0,
                'paths_count' => count($data['paths'] ?? []),
                'connections_count' => $data['connectionsCount'] ?? 0,
                'status' => 'online'
            ];
        } catch (RequestException $e) {
            return [
                'status' => 'offline',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAllPaths(): array
    {
        try {
            $response = $this->client->get('/paths');
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get paths from MediaMTX: ' . $e->getMessage());
        }
    }

    public function kickReader(string $streamKey, string $readerId): bool
    {
        try {
            $this->client->delete("/paths/$streamKey/readers/$readerId");
            return true;
        } catch (RequestException $e) {
            throw new \Exception('Failed to kick reader: ' . $e->getMessage());
        }
    }

    private function buildRTMPUrl(string $streamKey): string
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);
        $port = 1935; // Default RTMP port
        return "rtmp://$host:$port/$streamKey";
    }

    private function buildHLSUrl(string $streamKey): string
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);
        $port = 8888; // Default HLS port
        return "http://$host:$port/$streamKey/index.m3u8";
    }

    public function updateStreamStatus(string $streamKey, bool $isLive): void
    {
        // Update stream status in database based on MediaMTX state
        $streamService = new StreamService();

        $stmt = \App\Core\Database::prepare('SELECT id FROM streams WHERE stream_key = ?');
        $stmt->execute([$streamKey]);
        $stream = $stmt->fetch();

        if ($stream) {
            $newStatus = $isLive ? 'active' : 'inactive';
            $updateData = ['status' => $newStatus];

            if ($isLive && !$stream['actual_start']) {
                $updateData['actual_start'] = date('Y-m-d H:i:s');
            } elseif (!$isLive && $stream['status'] === 'active') {
                $updateData['actual_end'] = date('Y-m-d H:i:s');
                $updateData['status'] = 'ended';
            }

            $streamService->updateStream($stream['id'], $updateData);
        }
    }
}