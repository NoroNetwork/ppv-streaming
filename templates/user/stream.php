<?php $this->layout('layout', ['title' => $stream['title']]) ?>

<div class="px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <!-- Stream Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= $this->e($stream['title']) ?></h1>

            <div class="flex items-center space-x-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    <?= $stream['status'] === 'active' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' ?>">
                    <?= $stream['status'] === 'active' ? '🔴 LIVE' : '⏱️ ' . ucfirst($stream['status']) ?>
                </span>

                <?php if ($stream['price'] > 0): ?>
                    <span class="text-2xl font-bold text-blue-600">
                        <?= formatCurrency($stream['price'], $stream['currency']) ?>
                    </span>
                <?php else: ?>
                    <span class="text-2xl font-bold text-green-600">FREE</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Video Player Section -->
        <div class="bg-white rounded-lg card-shadow overflow-hidden mb-6">
            <?php if ($hasAccess && $stream['status'] === 'active' && $stream['hls_url']): ?>
                <!-- Video Player -->
                <div class="aspect-video bg-black">
                    <video-js
                        id="stream-player"
                        class="vjs-default-skin"
                        controls
                        preload="auto"
                        data-setup="{}">
                        <source src="<?= $this->e($stream['hls_url']) ?>" type="application/x-mpegURL">
                        <p class="vjs-no-js">
                            To view this video please enable JavaScript, and consider upgrading to a web browser that
                            <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                        </p>
                    </video-js>
                </div>

                <!-- Stream Controls -->
                <div class="p-4 bg-gray-50 border-t">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <span id="viewer-count" class="text-sm text-gray-600">👥 Loading viewers...</span>
                            <span class="text-sm text-gray-600">🎯 HD Quality</span>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button id="fullscreen-btn" class="btn-secondary text-sm">⛶ Fullscreen</button>
                            <button id="refresh-btn" class="btn-secondary text-sm">↻ Refresh</button>
                        </div>
                    </div>
                </div>

            <?php elseif ($hasAccess && $stream['status'] !== 'active'): ?>
                <!-- Stream Not Live -->
                <div class="aspect-video bg-gray-900 flex items-center justify-center">
                    <div class="text-center text-white">
                        <div class="text-6xl mb-4">⏰</div>
                        <h3 class="text-xl font-semibold mb-2">Stream Not Live</h3>
                        <p class="text-gray-300">This stream is not currently broadcasting.</p>
                        <?php if ($stream['scheduled_start']): ?>
                            <p class="text-gray-300 mt-2">Scheduled for: <?= formatDate($stream['scheduled_start']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Payment Required -->
                <div class="aspect-video bg-gradient-to-br from-blue-600 to-purple-700 flex items-center justify-center">
                    <div class="text-center text-white p-8">
                        <div class="text-6xl mb-4">🔒</div>
                        <h3 class="text-2xl font-semibold mb-4">Premium Content</h3>

                        <?php if ($stream['price'] > 0): ?>
                            <p class="text-xl mb-6">Get instant access for <?= formatCurrency($stream['price'], $stream['currency']) ?></p>

                            <?php if ($user): ?>
                                <button id="purchase-btn" class="bg-white text-blue-600 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-200">
                                    Purchase Access
                                </button>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <p class="text-lg">Sign in to purchase access</p>
                                    <button onclick="showLoginModal()" class="bg-white text-blue-600 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-200">
                                        Sign In
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-xl mb-6">Sign in to watch this free stream</p>
                            <button onclick="showLoginModal()" class="bg-white text-blue-600 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-200">
                                Sign In to Watch
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stream Description -->
        <?php if ($stream['description']): ?>
            <div class="bg-white rounded-lg card-shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">About This Stream</h2>
                <p class="text-gray-700 leading-relaxed"><?= nl2br($this->e($stream['description'])) ?></p>
            </div>
        <?php endif; ?>

        <!-- Stream Details -->
        <div class="bg-white rounded-lg card-shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Stream Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if ($stream['scheduled_start']): ?>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Scheduled Start</span>
                        <p class="text-gray-900"><?= formatDate($stream['scheduled_start']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($stream['actual_start']): ?>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Started At</span>
                        <p class="text-gray-900"><?= formatDate($stream['actual_start']) ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <span class="text-sm font-medium text-gray-500">Price</span>
                    <p class="text-gray-900">
                        <?= $stream['price'] > 0 ? formatCurrency($stream['price'], $stream['currency']) : 'Free' ?>
                    </p>
                </div>

                <div>
                    <span class="text-sm font-medium text-gray-500">Status</span>
                    <p class="text-gray-900"><?= ucfirst($stream['status']) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Complete Purchase</h3>

        <div class="mb-4">
            <p class="text-gray-600">Stream: <strong><?= $this->e($stream['title']) ?></strong></p>
            <p class="text-gray-600">Price: <strong><?= formatCurrency($stream['price'], $stream['currency']) ?></strong></p>
        </div>

        <div id="payment-element" class="mb-4">
            <!-- Stripe Elements will create form elements here -->
        </div>

        <div class="flex justify-end space-x-2">
            <button id="cancel-payment" class="btn-secondary">Cancel</button>
            <button id="confirm-payment" class="btn-primary">Pay Now</button>
        </div>

        <div id="payment-messages" class="mt-4 text-sm text-red-600"></div>
    </div>
</div>

<script>
    const streamId = <?= json_encode($stream['id']) ?>;
    const hasAccess = <?= json_encode($hasAccess) ?>;
    let player = null;

    // Initialize video player if user has access
    <?php if ($hasAccess && $stream['status'] === 'active' && $stream['hls_url']): ?>
    document.addEventListener('DOMContentLoaded', function() {
        player = videojs('stream-player', {
            fluid: true,
            responsive: true,
            playbackRates: [0.5, 1, 1.25, 1.5, 2],
            controls: true,
            preload: 'auto'
        });

        // Fullscreen button
        document.getElementById('fullscreen-btn').addEventListener('click', () => {
            if (player) {
                player.requestFullscreen();
            }
        });

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            if (player) {
                player.load();
            }
        });

        // Update viewer count
        updateViewerCount();
        setInterval(updateViewerCount, 30000);
    });

    async function updateViewerCount() {
        try {
            const response = await fetch(`/admin/api/streams/${streamId}/stats`, {
                headers: auth.getAuthHeaders()
            });

            if (response.ok) {
                const data = await response.json();
                document.getElementById('viewer-count').textContent = `👥 ${data.stats.viewers || 0} viewers`;
            }
        } catch (error) {
            console.error('Failed to update viewer count:', error);
        }
    }
    <?php endif; ?>

    // Payment handling
    <?php if ($user && $stream['price'] > 0 && !$hasAccess): ?>
    let stripe = null;
    let elements = null;
    let paymentElement = null;

    document.getElementById('purchase-btn').addEventListener('click', initializePayment);

    async function initializePayment() {
        try {
            // Initialize Stripe
            stripe = Stripe('<?= $_ENV['STRIPE_PUBLIC_KEY'] ?>');

            // Create payment intent
            const response = await fetch('/api/payment/create-intent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...auth.getAuthHeaders()
                },
                body: JSON.stringify({ stream_id: streamId })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error);
            }

            // Create elements
            elements = stripe.elements({
                clientSecret: data.client_secret
            });

            paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');

            // Show modal
            document.getElementById('payment-modal').classList.remove('hidden');

        } catch (error) {
            alert('Payment initialization failed: ' + error.message);
        }
    }

    // Cancel payment
    document.getElementById('cancel-payment').addEventListener('click', () => {
        document.getElementById('payment-modal').classList.add('hidden');
        if (paymentElement) {
            paymentElement.unmount();
        }
    });

    // Confirm payment
    document.getElementById('confirm-payment').addEventListener('click', async () => {
        if (!stripe || !elements) return;

        const {error} = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: window.location.href,
            },
        });

        if (error) {
            document.getElementById('payment-messages').textContent = error.message;
        } else {
            // Payment successful, reload page
            location.reload();
        }
    });
    <?php endif; ?>

    // Helper functions
    function formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    function formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
</script>