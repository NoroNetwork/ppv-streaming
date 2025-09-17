<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($title ?? 'Admin Panel - PPV Streaming') ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom admin styles -->
    <style>
        .sidebar-gradient {
            background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
        }

        .card-shadow {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200;
        }

        .btn-secondary {
            @apply bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200;
        }

        .btn-danger {
            @apply bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200;
        }

        .btn-success {
            @apply bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200;
        }

        .status-badge {
            @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
        }

        .status-active {
            @apply bg-green-100 text-green-800;
        }

        .status-inactive {
            @apply bg-gray-100 text-gray-800;
        }

        .status-ended {
            @apply bg-red-100 text-red-800;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 sidebar-gradient text-white flex-shrink-0">
            <div class="flex items-center justify-center h-16 border-b border-gray-700">
                <h1 class="text-xl font-bold">PPV Admin</h1>
            </div>

            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="/admin" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200 <?= $this->e($_SERVER['REQUEST_URI']) === '/admin' ? 'bg-gray-700' : '' ?>">
                        <span class="mr-3">📊</span>
                        Dashboard
                    </a>

                    <a href="/admin/streams" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200 <?= $this->e($_SERVER['REQUEST_URI']) === '/admin/streams' ? 'bg-gray-700' : '' ?>">
                        <span class="mr-3">📺</span>
                        Streams
                    </a>

                    <a href="/admin/users" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200">
                        <span class="mr-3">👥</span>
                        Users
                    </a>

                    <a href="/admin/payments" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200">
                        <span class="mr-3">💰</span>
                        Payments
                    </a>

                    <a href="/admin/analytics" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200">
                        <span class="mr-3">📈</span>
                        Analytics
                    </a>

                    <a href="/admin/settings" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200">
                        <span class="mr-3">⚙️</span>
                        Settings
                    </a>
                </div>

                <div class="mt-8 pt-8 border-t border-gray-700">
                    <div class="px-4 space-y-2">
                        <a href="/" target="_blank" class="flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200">
                            <span class="mr-3">🌐</span>
                            View Site
                        </a>

                        <button onclick="auth.logout(); window.location.href='/'" class="w-full flex items-center px-4 py-2 text-sm font-medium rounded-lg hover:bg-gray-700 transition duration-200">
                            <span class="mr-3">🚪</span>
                            Logout
                        </button>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b border-gray-200 flex-shrink-0">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center">
                        <h2 class="text-xl font-semibold text-gray-900"><?= $this->e($title ?? 'Admin Panel') ?></h2>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="p-2 text-gray-400 hover:text-gray-600 transition duration-200">
                                <span class="sr-only">Notifications</span>
                                🔔
                            </button>
                            <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-400"></span>
                        </div>

                        <!-- User Profile -->
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-700">👤</span>
                            </div>
                            <span id="admin-user-info" class="text-sm font-medium text-gray-700">Loading...</span>
                        </div>

                        <!-- System Status -->
                        <div id="system-status" class="flex items-center">
                            <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-500">Checking...</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?= $this->section('content') ?>
            </main>
        </div>
    </div>

    <!-- Global JavaScript -->
    <script>
        // Auth state management (same as main site)
        class Auth {
            constructor() {
                this.token = localStorage.getItem('auth_token');
                this.user = null;
                this.init();
            }

            async init() {
                if (this.token) {
                    try {
                        await this.getCurrentUser();
                        if (this.user.role !== 'admin') {
                            this.logout();
                            window.location.href = '/';
                            return;
                        }
                    } catch (error) {
                        this.logout();
                        window.location.href = '/';
                        return;
                    }
                } else {
                    window.location.href = '/';
                    return;
                }
                this.updateUI();
            }

            async getCurrentUser() {
                const response = await fetch('/api/auth/me', {
                    headers: {
                        'Authorization': `Bearer ${this.token}`
                    }
                });

                if (!response.ok) {
                    throw new Error('Invalid token');
                }

                const data = await response.json();
                this.user = data.user;
                return this.user;
            }

            logout() {
                this.token = null;
                this.user = null;
                localStorage.removeItem('auth_token');
            }

            updateUI() {
                if (this.user) {
                    document.getElementById('admin-user-info').textContent = this.user.email;
                    this.checkSystemStatus();
                }
            }

            getAuthHeaders() {
                return this.token ? { 'Authorization': `Bearer ${this.token}` } : {};
            }

            async checkSystemStatus() {
                try {
                    const response = await fetch('/admin/api/mediamtx/status', {
                        headers: this.getAuthHeaders()
                    });

                    const data = await response.json();
                    const statusEl = document.getElementById('system-status');

                    if (data.status.status === 'online') {
                        statusEl.innerHTML = `
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm text-green-600">System Online</span>
                        `;
                    } else {
                        statusEl.innerHTML = `
                            <div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div>
                            <span class="text-sm text-red-600">System Offline</span>
                        `;
                    }
                } catch (error) {
                    const statusEl = document.getElementById('system-status');
                    statusEl.innerHTML = `
                        <div class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></div>
                        <span class="text-sm text-yellow-600">Status Unknown</span>
                    `;
                }
            }
        }

        // Initialize auth
        const auth = new Auth();

        // Utility functions
        function formatCurrency(amount, currency = 'USD') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatDateTime(dateString) {
            return new Date(dateString).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showConfirmDialog(message, onConfirm) {
            if (confirm(message)) {
                onConfirm();
            }
        }

        function showNotification(message, type = 'info') {
            // Simple notification - could be enhanced with a proper notification system
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Auto-refresh system status every 30 seconds
        setInterval(() => {
            if (auth.user) {
                auth.checkSystemStatus();
            }
        }, 30000);
    </script>
</body>
</html>