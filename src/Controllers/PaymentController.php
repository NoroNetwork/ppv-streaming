<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\PaymentService;
use App\Services\StreamService;
use League\Plates\Engine;

class PaymentController
{
    private AuthService $authService;
    private PaymentService $paymentService;
    private StreamService $streamService;
    private Engine $templates;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->paymentService = new PaymentService();
        $this->streamService = new StreamService();
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function showPayment(string $streamId): void
    {
        try {
            $stream = $this->streamService->getStream($streamId);

            if (!$stream) {
                http_response_code(404);
                echo $this->templates->render('error', ['message' => 'Stream not found']);
                return;
            }

            echo $this->templates->render('user/payment', [
                'stream' => $stream,
                'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY']
            ]);
        } catch (\Exception $e) {
            echo $this->templates->render('error', ['message' => $e->getMessage()]);
        }
    }

    public function createPaymentIntent(): void
    {
        try {
            $user = $this->authService->requireAuth();
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['stream_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Stream ID required']);
                return;
            }

            $stream = $this->streamService->getStream($input['stream_id']);

            if (!$stream) {
                http_response_code(404);
                echo json_encode(['error' => 'Stream not found']);
                return;
            }

            // Check if user already has access
            if ($this->streamService->hasAccess($user['user_id'], $stream['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'You already have access to this stream']);
                return;
            }

            $paymentIntent = $this->paymentService->createPaymentIntent(
                $stream['price'],
                $stream['currency'],
                $user['user_id'],
                $stream['id']
            );

            echo json_encode($paymentIntent);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function handleWebhook(): void
    {
        try {
            $payload = file_get_contents('php://input');
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

            $event = $this->paymentService->verifyWebhook($payload, $sigHeader);

            if ($event['type'] === 'payment_intent.succeeded') {
                $paymentIntent = $event['data']['object'];
                $this->paymentService->handleSuccessfulPayment($paymentIntent);
            }

            echo json_encode(['status' => 'success']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}