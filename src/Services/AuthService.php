<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private const TOKEN_EXPIRY = 86400; // 24 hours

    public function register(string $email, string $password, string $role = 'user'): array
    {
        // Rate limiting
        if (!Security::checkRateLimit('register:' . $_SERVER['REMOTE_ADDR'], 3, 3600)) {
            throw new \Exception('Too many registration attempts. Please try again later.');
        }

        // Input validation
        $validator = new Validator();
        $isValid = $validator->validate([
            'email' => $email,
            'password' => $password,
            'role' => $role
        ], [
            'email' => ['required', 'email', 'unique:users:email'],
            'password' => ['required', ['min_length', 8]],
            'role' => ['required', ['in', ['user', 'admin']]]
        ]);

        if (!$isValid) {
            throw new \Exception($validator->getFirstError());
        }

        // Security checks
        if (Security::detectXSS($email) || Security::detectSQLInjection($email)) {
            Security::logSecurityEvent('registration_attempt_malicious', ['email' => $email]);
            throw new \Exception('Invalid input detected');
        }

        // Create user
        $hashedPassword = Security::hashPassword($password);

        $stmt = Database::prepare(
            'INSERT INTO users (email, password, role, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$email, $hashedPassword, $role]);

        $userId = Database::lastInsertId();

        Security::logSecurityEvent('user_registered', ['user_id' => $userId, 'email' => $email]);

        return $this->createTokenResponse($userId, $email, $role);
    }

    public function login(string $email, string $password): array
    {
        // Rate limiting
        if (!Security::checkRateLimit('login:' . $_SERVER['REMOTE_ADDR'], 10, 900)) {
            throw new \Exception('Too many login attempts. Please try again later.');
        }

        // Check account lockout
        if (!Security::checkLoginAttempts($email)) {
            Security::logSecurityEvent('login_attempt_locked_account', ['email' => $email]);
            throw new \Exception('Account temporarily locked due to failed login attempts.');
        }

        // Input validation
        $validator = new Validator();
        $isValid = $validator->validate([
            'email' => $email,
            'password' => $password
        ], [
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if (!$isValid) {
            throw new \Exception($validator->getFirstError());
        }

        // Security checks
        if (Security::detectXSS($email) || Security::detectSQLInjection($email)) {
            Security::logSecurityEvent('login_attempt_malicious', ['email' => $email]);
            throw new \Exception('Invalid input detected');
        }

        $stmt = Database::prepare('SELECT id, email, password, role, locked_until FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            Security::recordLoginAttempt($email, false);
            throw new \Exception('Invalid credentials');
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            Security::logSecurityEvent('login_attempt_locked_user', ['email' => $email]);
            throw new \Exception('Account is temporarily locked');
        }

        if (!Security::verifyPassword($password, $user['password'])) {
            Security::recordLoginAttempt($email, false);
            throw new \Exception('Invalid credentials');
        }

        // Successful login
        Security::recordLoginAttempt($email, true);
        Security::clearLoginAttempts($email);

        // Update last login and clear lock
        $stmt = Database::prepare('UPDATE users SET last_login = NOW(), locked_until = NULL, failed_login_attempts = 0 WHERE id = ?');
        $stmt->execute([$user['id']]);

        Security::logSecurityEvent('user_login_success', ['user_id' => $user['id'], 'email' => $email]);

        return $this->createTokenResponse($user['id'], $user['email'], $user['role']);
    }

    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Invalid token');
        }
    }

    public function getCurrentUser(string $token): ?array
    {
        try {
            $payload = $this->verifyToken($token);

            $stmt = Database::prepare('SELECT id, email, role, created_at FROM users WHERE id = ?');
            $stmt->execute([$payload['user_id']]);

            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function createTokenResponse(string $userId, string $email, string $role): array
    {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + self::TOKEN_EXPIRY
        ];

        $token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        return [
            'token' => $token,
            'user' => [
                'id' => $userId,
                'email' => $email,
                'role' => $role
            ],
            'expires_in' => self::TOKEN_EXPIRY
        ];
    }

    public function requireAuth(): array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            throw new \Exception('Authorization token required');
        }

        $token = $matches[1];
        return $this->verifyToken($token);
    }

    public function requireRole(string $requiredRole): array
    {
        $user = $this->requireAuth();

        if ($user['role'] !== $requiredRole && $user['role'] !== 'admin') {
            http_response_code(403);
            throw new \Exception('Insufficient permissions');
        }

        return $user;
    }
}