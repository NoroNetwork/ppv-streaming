<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;

class AuthTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();

        // Clean database before each test
        TestDatabase::cleanDatabase();
    }

    protected function tearDown(): void
    {
        TestDatabase::cleanDatabase();
        parent::tearDown();
    }

    public function testUserRegistration(): void
    {
        $email = 'newuser@test.com';
        $password = 'securepassword123';

        $result = $this->authService->register($email, $password);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($email, $result['user']['email']);
        $this->assertEquals('user', $result['user']['role']);
    }

    public function testRegistrationWithInvalidEmail(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email must be a valid email address');

        $this->authService->register('invalid-email', 'password123');
    }

    public function testRegistrationWithShortPassword(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');

        $this->authService->register('user@test.com', 'short');
    }

    public function testRegistrationWithDuplicateEmail(): void
    {
        $email = 'duplicate@test.com';
        $password = 'password123';

        // First registration should succeed
        $this->authService->register($email, $password);

        // Second registration should fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email already exists');

        $this->authService->register($email, $password);
    }

    public function testSuccessfulLogin(): void
    {
        $email = 'logintest@test.com';
        $password = 'testpassword123';

        // Register user first
        $this->authService->register($email, $password);

        // Then login
        $result = $this->authService->login($email, $password);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($email, $result['user']['email']);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->authService->login('nonexistent@test.com', 'wrongpassword');
    }

    public function testLoginWithCorrectEmailWrongPassword(): void
    {
        $email = 'wrongpass@test.com';
        $password = 'correctpassword';

        // Register user
        $this->authService->register($email, $password);

        // Try login with wrong password
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->authService->login($email, 'wrongpassword');
    }

    public function testTokenVerification(): void
    {
        $email = 'tokentest@test.com';
        $password = 'testpassword123';

        // Register and get token
        $result = $this->authService->register($email, $password);
        $token = $result['token'];

        // Verify token
        $payload = $this->authService->verifyToken($token);

        $this->assertEquals($result['user']['id'], $payload['user_id']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals('user', $payload['role']);
    }

    public function testInvalidTokenVerification(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid token');

        $this->authService->verifyToken('invalid-token');
    }

    public function testGetCurrentUser(): void
    {
        $email = 'currentuser@test.com';
        $password = 'testpassword123';

        // Register user
        $result = $this->authService->register($email, $password);
        $token = $result['token'];

        // Get current user
        $user = $this->authService->getCurrentUser('Bearer ' . $token);

        $this->assertNotNull($user);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals('user', $user['role']);
    }

    public function testGetCurrentUserWithInvalidToken(): void
    {
        $user = $this->authService->getCurrentUser('Bearer invalid-token');
        $this->assertNull($user);
    }

    public function testAdminRoleRegistration(): void
    {
        $email = 'admin@test.com';
        $password = 'adminpassword123';

        $result = $this->authService->register($email, $password, 'admin');

        $this->assertEquals('admin', $result['user']['role']);
    }

    public function testRoleValidation(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Role must be one of: user, admin');

        $this->authService->register('test@test.com', 'password123', 'invalid-role');
    }

    public function testRateLimitingRegistration(): void
    {
        // Mock multiple registrations from same IP
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->authService->register("user$i@test.com", 'password123');
            } catch (\Exception $e) {
                // Continue even if user exists
                if (!str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        }

        // Fourth registration should be rate limited
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Too many registration attempts');

        $this->authService->register('user4@test.com', 'password123');
    }

    public function testMaliciousInputDetection(): void
    {
        $maliciousEmail = '<script>alert("xss")</script>@test.com';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid input detected');

        $this->authService->register($maliciousEmail, 'password123');
    }

    public function testSQLInjectionAttempt(): void
    {
        $sqlInjection = "admin'; DROP TABLE users;--@test.com";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid input detected');

        $this->authService->register($sqlInjection, 'password123');
    }
}

// Include TestDatabase class for feature tests
class TestDatabase
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'] ?? 3306,
                $_ENV['DB_NAME']
            );

            self::$connection = new PDO(
                $dsn,
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$connection;
    }

    public static function cleanDatabase(): void
    {
        $pdo = self::getConnection();

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Disable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Truncate all tables
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }

        // Re-enable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}