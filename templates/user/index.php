<?php $this->layout('layout', ['title' => 'Live Streams']) ?>

<div class="px-4 py-6">
    <!-- Hero Section -->
    <div class="gradient-bg rounded-xl p-8 mb-8 text-white">
        <div class="max-w-3xl">
            <h1 class="text-4xl font-bold mb-4">Premium Live Streaming</h1>
            <p class="text-xl opacity-90">Experience high-quality live events with instant access and secure payments.</p>
        </div>
    </div>

    <!-- Streams Grid -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Available Streams</h2>

        <?php if (empty($streams)): ?>
            <div class="text-center py-12">
                <div class="text-gray-400 text-6xl mb-4">📺</div>
                <h3 class="text-xl font-medium text-gray-500 mb-2">No streams available</h3>
                <p class="text-gray-400">Check back later for live events.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($streams as $stream): ?>
                    <div class="bg-white rounded-lg card-shadow overflow-hidden">
                        <!-- Stream Thumbnail/Preview -->
                        <div class="aspect-video bg-gray-900 relative">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-white text-center">
                                    <div class="text-4xl mb-2">🎬</div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?= $stream['status'] === 'active' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $stream['status'] === 'active' ? '🔴 LIVE' : '⏱️ ' . ucfirst($stream['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Stream Info -->
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                <?= $this->e($stream['title']) ?>
                            </h3>

                            <?php if ($stream['description']): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                    <?= $this->e($stream['description']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="flex items-center justify-between">
                                <div class="text-2xl font-bold text-blue-600">
                                    <?= $stream['price'] > 0 ? formatCurrency($stream['price'], $stream['currency']) : 'FREE' ?>
                                </div>

                                <a href="/stream/<?= $stream['id'] ?>" class="btn-primary">
                                    <?= $stream['status'] === 'active' ? 'Watch Now' : 'View Details' ?>
                                </a>
                            </div>

                            <?php if ($stream['scheduled_start']): ?>
                                <div class="mt-3 text-sm text-gray-500">
                                    📅 <?= formatDate($stream['scheduled_start']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Features Section -->
    <div class="bg-white rounded-lg card-shadow p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Why Choose Our Platform?</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="text-4xl mb-4">🔒</div>
                <h3 class="text-lg font-semibold mb-2">Secure Payments</h3>
                <p class="text-gray-600">Safe and encrypted payment processing with industry-standard security.</p>
            </div>

            <div class="text-center">
                <div class="text-4xl mb-4">📱</div>
                <h3 class="text-lg font-semibold mb-2">Multi-Device</h3>
                <p class="text-gray-600">Watch on any device - desktop, tablet, or mobile with responsive streaming.</p>
            </div>

            <div class="text-center">
                <div class="text-4xl mb-4">⚡</div>
                <h3 class="text-lg font-semibold mb-2">HD Quality</h3>
                <p class="text-gray-600">Crystal clear HD streaming with adaptive bitrate for smooth playback.</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Helper function for currency formatting
    function formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    // Helper function for date formatting
    function formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Auto-refresh stream status every 30 seconds
    setInterval(async () => {
        try {
            const response = await fetch('/api/streams');
            const data = await response.json();

            // Update stream status indicators
            data.streams.forEach(stream => {
                const statusElements = document.querySelectorAll(`[data-stream-id="${stream.id}"] .status`);
                statusElements.forEach(el => {
                    el.className = stream.status === 'active' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800';
                    el.textContent = stream.status === 'active' ? '🔴 LIVE' : '⏱️ ' + stream.status.charAt(0).toUpperCase() + stream.status.slice(1);
                });
            });
        } catch (error) {
            console.error('Failed to refresh stream status:', error);
        }
    }, 30000);
</script>