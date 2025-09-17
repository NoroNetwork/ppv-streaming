<?php

namespace App\Core;

class Security
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes

    public static function hashPassword(string $password): string
    {
        $cost = (int) ($_ENV['BCRYPT_COST'] ?? 12);
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function checkRateLimit(string $identifier, int $maxAttempts = 10, int $windowSeconds = 3600): bool
    {
        $key = "rate_limit:" . hash('sha256', $identifier);
        $now = time();

        // Get current attempts from database/cache (simplified with session for demo)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $attempts = $_SESSION[$key] ?? [];

        // Remove old attempts outside the window
        $attempts = array_filter($attempts, function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });

        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Add current attempt
        $attempts[] = $now;
        $_SESSION[$key] = $attempts;

        return true;
    }

    public static function sanitizeFilename(string $filename): string
    {
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Prevent directory traversal
        $filename = str_replace(['../', '.\\', '..\\'], '', $filename);

        // Limit length
        $filename = substr($filename, 0, 255);

        return $filename;
    }

    public static function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 10485760): array
    {
        $errors = [];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'File is too large';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'No file was uploaded';
                    break;
                default:
                    $errors[] = 'Upload failed';
            }
            return $errors;
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File is too large (max ' . number_format($maxSize / 1048576, 1) . 'MB)';
        }

        // Check file type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed';
            }
        }

        // Check for dangerous file extensions
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pl', 'py', 'jsp', 'asp', 'sh', 'cgi'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($extension, $dangerousExtensions)) {
            $errors[] = 'File extension not allowed';
        }

        return $errors;
    }

    public static function encrypt(string $data, string $key = null): string
    {
        $key = $key ?: $_ENV['APP_SECRET'];
        $key = hash('sha256', $key, true);

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encryptedData, string $key = null): string
    {
        $key = $key ?: $_ENV['APP_SECRET'];
        $key = hash('sha256', $key, true);

        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context
        ];

        // Log to database (simplified implementation)
        try {
            $stmt = Database::prepare(
                'INSERT INTO security_logs (event, ip_address, user_agent, context, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $event,
                $logData['ip'],
                $logData['user_agent'],
                json_encode($context)
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging
            error_log('Security Event: ' . json_encode($logData));
        }
    }

    public static function checkLoginAttempts(string $email): bool
    {
        $stmt = Database::prepare(
            'SELECT COUNT(*) as attempts FROM login_attempts
             WHERE email = ? AND created_at > ?'
        );

        $cutoff = date('Y-m-d H:i:s', time() - self::LOCKOUT_DURATION);
        $stmt->execute([$email, $cutoff]);

        $result = $stmt->fetch();
        return $result['attempts'] < self::MAX_LOGIN_ATTEMPTS;
    }

    public static function recordLoginAttempt(string $email, bool $success): void
    {
        $stmt = Database::prepare(
            'INSERT INTO login_attempts (email, ip_address, success, created_at)
             VALUES (?, ?, ?, NOW())'
        );

        $stmt->execute([
            $email,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $success ? 1 : 0
        ]);

        if (!$success) {
            self::logSecurityEvent('failed_login', ['email' => $email]);
        }
    }

    public static function clearLoginAttempts(string $email): void
    {
        $stmt = Database::prepare('DELETE FROM login_attempts WHERE email = ?');
        $stmt->execute([$email]);
    }

    public static function isValidIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function detectSQLInjection(string $input): bool
    {
        $patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\'|\"|`).*(\'|\"|`)/',
            '/(-{2}|\/\*|\*\/)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    public static function detectXSS(string $input): bool
    {
        $dangerous = [
            '<script',
            'javascript:',
            'onclick=',
            'onerror=',
            'onload=',
            'onmouseover=',
            'onfocus=',
            'eval(',
            'expression(',
            'vbscript:',
            'data:text/html'
        ];

        foreach ($dangerous as $pattern) {
            if (stripos($input, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function secureHeaders(): void
    {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

        // CSP header
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://vjs.zencdn.net https://cdn.jsdelivr.net https://js.stripe.com; " .
               "style-src 'self' 'unsafe-inline' https://vjs.zencdn.net; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https:; " .
               "connect-src 'self' https://api.stripe.com; " .
               "media-src 'self'; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";

        header("Content-Security-Policy: $csp");
    }
}