<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($title ?? 'PPV Streaming Platform') ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Video.js for HLS streaming -->
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">
    <script src="https://vjs.zencdn.net/8.6.1/video.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@videojs/http-streaming@3.0.2/dist/videojs-http-streaming.min.js"></script>

    <!-- Stripe for payments -->
    <script src="https://js.stripe.com/v3/"></script>

    <!-- Custom styles -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .video-js {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
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
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-gray-900">PPV Stream</h1>
                    </a>
                </div>

                <div class="flex items-center space-x-4">
                    <div id="auth-section">
                        <!-- Auth buttons will be populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?= $this->section('content') ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; 2024 PPV Streaming Platform. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Global JavaScript -->
    <script>
        // Auth state management
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
                    } catch (error) {
                        this.logout();
                    }
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

            async login(email, password) {
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Login failed');
                }

                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('auth_token', this.token);
                this.updateUI();

                return data;
            }

            logout() {
                this.token = null;
                this.user = null;
                localStorage.removeItem('auth_token');
                this.updateUI();
            }

            updateUI() {
                const authSection = document.getElementById('auth-section');

                if (this.user) {
                    authSection.innerHTML = `
                        <span class="text-gray-700">Welcome, ${this.user.email}</span>
                        ${this.user.role === 'admin' ? '<a href="/admin" class="btn-secondary ml-2">Admin</a>' : ''}
                        <button onclick="auth.logout()" class="btn-secondary ml-2">Logout</button>
                    `;
                } else {
                    authSection.innerHTML = `
                        <button onclick="showLoginModal()" class="btn-primary">Login</button>
                        <button onclick="showRegisterModal()" class="btn-secondary ml-2">Register</button>
                    `;
                }
            }

            getAuthHeaders() {
                return this.token ? { 'Authorization': `Bearer ${this.token}` } : {};
            }
        }

        // Initialize auth
        const auth = new Auth();

        // Modal functions
        function showLoginModal() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-semibold mb-4">Login</h3>
                    <form id="login-form">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="this.closest('.fixed').remove()" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Login</button>
                        </div>
                    </form>
                </div>
            `;

            document.body.appendChild(modal);

            modal.querySelector('#login-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);

                try {
                    await auth.login(formData.get('email'), formData.get('password'));
                    modal.remove();
                    location.reload();
                } catch (error) {
                    alert('Login failed: ' + error.message);
                }
            });
        }

        function showRegisterModal() {
            // Similar implementation to login modal
            alert('Register functionality - implement similar to login modal');
        }

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
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
</body>
</html>