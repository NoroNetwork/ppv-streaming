<?php

namespace App\Services;

use App\Core\Database;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;

class PaymentService
{
    public function __construct()
    {
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    public function createPaymentIntent(float $amount, string $currency, string $userId, string $streamId): array
    {
        try {
            $amountInCents = (int)($amount * 100);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                'metadata' => [
                    'user_id' => $userId,
                    'stream_id' => $streamId
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
        } catch (\Exception $e) {
            throw new \Exception('Payment creation failed: ' . $e->getMessage());
        }
    }

    public function verifyWebhook(string $payload, string $sigHeader): array
    {
        try {
            return Webhook::constructEvent(
                $payload,
                $sigHeader,
                $_ENV['STRIPE_WEBHOOK_SECRET']
            );
        } catch (\Exception $e) {
            throw new \Exception('Webhook verification failed: ' . $e->getMessage());
        }
    }

    public function handleSuccessfulPayment(array $paymentIntent): void
    {
        $userId = $paymentIntent['metadata']['user_id'];
        $streamId = $paymentIntent['metadata']['stream_id'];
        $amountPaid = $paymentIntent['amount'] / 100; // Convert from cents
        $currency = strtoupper($paymentIntent['currency']);

        // Check if access already granted
        $stmt = Database::prepare(
            'SELECT id FROM stream_access WHERE user_id = ? AND stream_id = ?'
        );
        $stmt->execute([$userId, $streamId]);

        if ($stmt->fetch()) {
            return; // Access already granted
        }

        // Grant access
        $stmt = Database::prepare(
            'INSERT INTO stream_access (user_id, stream_id, payment_intent_id, amount_paid, currency)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $streamId,
            $paymentIntent['id'],
            $amountPaid,
            $currency
        ]);

        // Update stream statistics
        $this->updateStreamRevenue($streamId, $amountPaid);
    }

    private function updateStreamRevenue(string $streamId, float $amount): void
    {
        // Get or create stream stats for today
        $stmt = Database::prepare(
            'SELECT id, total_revenue, total_purchases FROM stream_stats
             WHERE stream_id = ? AND DATE(recorded_at) = CURDATE()
             ORDER BY recorded_at DESC LIMIT 1'
        );
        $stmt->execute([$streamId]);
        $stats = $stmt->fetch();

        if ($stats) {
            // Update existing stats
            $stmt = Database::prepare(
                'UPDATE stream_stats
                 SET total_revenue = total_revenue + ?, total_purchases = total_purchases + 1
                 WHERE id = ?'
            );
            $stmt->execute([$amount, $stats['id']]);
        } else {
            // Create new stats entry
            $stmt = Database::prepare(
                'INSERT INTO stream_stats (stream_id, total_revenue, total_purchases)
                 VALUES (?, ?, 1)'
            );
            $stmt->execute([$streamId, $amount]);
        }
    }
}