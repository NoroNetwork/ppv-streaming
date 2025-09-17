<?php $this->layout('admin-layout', ['title' => 'Admin Dashboard']) ?>

<div class="space-y-6">
    <!-- Dashboard Header -->
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600 mt-1">Overview of your streaming platform</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg card-shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <span class="text-white text-sm">📺</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Streams</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_streams'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg card-shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <span class="text-white text-sm">🔴</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Active Streams</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['active_streams'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg card-shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <span class="text-white text-sm">💰</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                    <p class="text-2xl font-semibold text-gray-900">$<?= number_format($stats['total_revenue'], 2) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg card-shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <span class="text-white text-sm">👥</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_users'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- MediaMTX Status -->
    <div class="bg-white rounded-lg card-shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">MediaMTX Server Status</h2>
            <button id="refresh-status" class="btn-secondary text-sm">↻ Refresh</button>
        </div>

        <div id="mediamtx-status" class="space-y-4">
            <div class="flex items-center">
                <div class="w-3 h-3 bg-gray-400 rounded-full mr-3"></div>
                <span class="text-gray-600">Checking server status...</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg card-shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="/admin/streams" class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition duration-200">
                <div class="text-2xl mb-2">📺</div>
                <h3 class="font-medium text-gray-900">Manage Streams</h3>
                <p class="text-sm text-gray-600">Create, edit, and monitor your streams</p>
            </a>

            <button onclick="showCreateStreamModal()" class="block w-full p-4 border border-gray-200 rounded-lg hover:border-green-300 hover:bg-green-50 transition duration-200 text-left">
                <div class="text-2xl mb-2">➕</div>
                <h3 class="font-medium text-gray-900">New Stream</h3>
                <p class="text-sm text-gray-600">Create a new streaming event</p>
            </button>

            <button onclick="showSystemHealth()" class="block w-full p-4 border border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition duration-200 text-left">
                <div class="text-2xl mb-2">📊</div>
                <h3 class="font-medium text-gray-900">System Health</h3>
                <p class="text-sm text-gray-600">View detailed system metrics</p>
            </button>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg card-shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h2>
        <div id="recent-activity" class="space-y-3">
            <div class="text-gray-500 text-center py-4">Loading recent activity...</div>
        </div>
    </div>
</div>

<!-- Create Stream Modal -->
<div id="create-stream-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Create New Stream</h3>
        <form id="create-stream-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Price ($)</label>
                <input type="number" name="price" min="0" step="0.01" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Start (Optional)</label>
                <input type="datetime-local" name="scheduled_start" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="hideCreateStreamModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Create Stream</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Load MediaMTX status on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadMediaMTXStatus();
        loadRecentActivity();

        // Refresh status every 30 seconds
        setInterval(loadMediaMTXStatus, 30000);
    });

    async function loadMediaMTXStatus() {
        try {
            const response = await fetch('/admin/api/mediamtx/status', {
                headers: auth.getAuthHeaders()
            });

            const data = await response.json();
            const statusDiv = document.getElementById('mediamtx-status');

            if (data.status.status === 'online') {
                statusDiv.innerHTML = `
                    <div class="flex items-center mb-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <span class="text-green-700 font-medium">Server Online</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Version:</span>
                            <span class="text-gray-900">${data.status.version}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Connections:</span>
                            <span class="text-gray-900">${data.status.connections_count}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Paths:</span>
                            <span class="text-gray-900">${data.status.paths_count}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Uptime:</span>
                            <span class="text-gray-900">${formatUptime(data.status.uptime)}</span>
                        </div>
                    </div>
                `;
            } else {
                statusDiv.innerHTML = `
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                        <span class="text-red-700 font-medium">Server Offline</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">${data.status.error || 'Unable to connect to MediaMTX server'}</p>
                `;
            }
        } catch (error) {
            console.error('Failed to load MediaMTX status:', error);
            document.getElementById('mediamtx-status').innerHTML = `
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                    <span class="text-red-700 font-medium">Connection Error</span>
                </div>
            `;
        }
    }

    async function loadRecentActivity() {
        // Simulated recent activity - replace with actual API call
        const activities = [
            { type: 'stream_created', message: 'New stream "Weekly Webinar" created', time: '2 minutes ago' },
            { type: 'payment', message: 'Payment received for "Live Concert"', time: '5 minutes ago' },
            { type: 'stream_ended', message: 'Stream "Daily Update" ended', time: '1 hour ago' }
        ];

        const activityDiv = document.getElementById('recent-activity');
        activityDiv.innerHTML = activities.map(activity => `
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                <span class="text-gray-700">${activity.message}</span>
                <span class="text-sm text-gray-500">${activity.time}</span>
            </div>
        `).join('');
    }

    // Refresh status button
    document.getElementById('refresh-status').addEventListener('click', loadMediaMTXStatus);

    // Create stream modal functions
    function showCreateStreamModal() {
        document.getElementById('create-stream-modal').classList.remove('hidden');
    }

    function hideCreateStreamModal() {
        document.getElementById('create-stream-modal').classList.add('hidden');
        document.getElementById('create-stream-form').reset();
    }

    // Create stream form submission
    document.getElementById('create-stream-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        const streamData = {
            title: formData.get('title'),
            description: formData.get('description'),
            price: parseFloat(formData.get('price')),
            scheduled_start: formData.get('scheduled_start') || null
        };

        try {
            const response = await fetch('/admin/api/streams', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...auth.getAuthHeaders()
                },
                body: JSON.stringify(streamData)
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error);
            }

            hideCreateStreamModal();
            alert('Stream created successfully!');
            location.reload();
        } catch (error) {
            alert('Failed to create stream: ' + error.message);
        }
    });

    function showSystemHealth() {
        alert('System Health - Feature coming soon!');
    }

    function formatUptime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
</script>