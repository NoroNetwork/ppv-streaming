<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Security;
use App\Core\Validator;

class SecurityTest extends TestCase
{
    public function testPasswordHashing(): void
    {
        $password = 'test_password_123';
        $hash = Security::hashPassword($password);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue(Security::verifyPassword($password, $hash));
        $this->assertFalse(Security::verifyPassword('wrong_password', $hash));
    }

    public function testSecureTokenGeneration(): void
    {
        $token1 = Security::generateSecureToken();
        $token2 = Security::generateSecureToken();

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token1);
    }

    public function testFilenamesSanitization(): void
    {
        $dangerous = '../../../etc/passwd';
        $sanitized = Security::sanitizeFilename($dangerous);
        $this->assertStringNotContainsString('..', $sanitized);
        $this->assertStringNotContainsString('/', $sanitized);

        $normal = 'document-2024.pdf';
        $this->assertEquals($normal, Security::sanitizeFilename($normal));
    }

    public function testSQLInjectionDetection(): void
    {
        $sqlInjections = [
            "1' OR 1=1--",
            "'; DROP TABLE users;--",
            "1 UNION SELECT * FROM users",
            "admin'/*",
        ];

        foreach ($sqlInjections as $injection) {
            $this->assertTrue(
                Security::detectSQLInjection($injection),
                "Failed to detect SQL injection: $injection"
            );
        }

        $safeInputs = [
            'normal text',
            'user@email.com',
            'password123',
            'Product Name 2024'
        ];

        foreach ($safeInputs as $input) {
            $this->assertFalse(
                Security::detectSQLInjection($input),
                "False positive for safe input: $input"
            );
        }
    }

    public function testXSSDetection(): void
    {
        $xssAttempts = [
            '<script>alert("xss")</script>',
            'javascript:alert(1)',
            '<img src="x" onerror="alert(1)">',
            '<body onload="evil()">',
            'eval(malicious_code)',
        ];

        foreach ($xssAttempts as $xss) {
            $this->assertTrue(
                Security::detectXSS($xss),
                "Failed to detect XSS: $xss"
            );
        }

        $safeInputs = [
            'Normal text content',
            'Email: user@domain.com',
            '<p>Safe HTML paragraph</p>',
            '<strong>Bold text</strong>'
        ];

        foreach ($safeInputs as $input) {
            $this->assertFalse(
                Security::detectXSS($input),
                "False positive for safe input: $input"
            );
        }
    }

    public function testEncryptionDecryption(): void
    {
        $plaintext = 'Sensitive data that needs encryption';
        $key = 'test_encryption_key_32_characters!';

        $encrypted = Security::encrypt($plaintext, $key);
        $decrypted = Security::decrypt($encrypted, $key);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertEquals($plaintext, $decrypted);

        // Test with wrong key
        $wrongKey = 'wrong_key_32_characters_exactly!';
        $wrongDecryption = Security::decrypt($encrypted, $wrongKey);
        $this->assertNotEquals($plaintext, $wrongDecryption);
    }

    public function testFileUploadValidation(): void
    {
        // Test valid file
        $validFile = [
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
            'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
            'name' => 'document.pdf'
        ];

        file_put_contents($validFile['tmp_name'], 'test content');
        $errors = Security::validateFileUpload($validFile, ['text/plain'], 2048);
        $this->assertEmpty($errors);
        unlink($validFile['tmp_name']);

        // Test file too large
        $largeFile = [
            'error' => UPLOAD_ERR_OK,
            'size' => 20971520, // 20MB
            'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
            'name' => 'large.pdf'
        ];

        $errors = Security::validateFileUpload($largeFile, [], 10485760); // 10MB limit
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('too large', $errors[0]);

        // Test upload error
        $errorFile = [
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 1024,
            'tmp_name' => '',
            'name' => 'error.pdf'
        ];

        $errors = Security::validateFileUpload($errorFile);
        $this->assertNotEmpty($errors);
    }
}

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testRequiredValidation(): void
    {
        $rules = ['name' => ['required']];

        $this->assertTrue($this->validator->validate(['name' => 'John'], $rules));
        $this->assertFalse($this->validator->validate(['name' => ''], $rules));
        $this->assertFalse($this->validator->validate([], $rules));
    }

    public function testEmailValidation(): void
    {
        $rules = ['email' => ['email']];

        $this->assertTrue($this->validator->validate(['email' => 'test@example.com'], $rules));
        $this->assertFalse($this->validator->validate(['email' => 'invalid-email'], $rules));
        $this->assertFalse($this->validator->validate(['email' => '@example.com'], $rules));
    }

    public function testMinLengthValidation(): void
    {
        $rules = ['password' => [['min_length', 8]]];

        $this->assertTrue($this->validator->validate(['password' => 'password123'], $rules));
        $this->assertFalse($this->validator->validate(['password' => 'short'], $rules));
    }

    public function testNumericValidation(): void
    {
        $rules = ['age' => ['numeric']];

        $this->assertTrue($this->validator->validate(['age' => '25'], $rules));
        $this->assertTrue($this->validator->validate(['age' => 25], $rules));
        $this->assertFalse($this->validator->validate(['age' => 'not-a-number'], $rules));
    }

    public function testInValidation(): void
    {
        $rules = ['status' => [['in', ['active', 'inactive', 'pending']]]];

        $this->assertTrue($this->validator->validate(['status' => 'active'], $rules));
        $this->assertFalse($this->validator->validate(['status' => 'invalid-status'], $rules));
    }

    public function testNoScriptValidation(): void
    {
        $rules = ['content' => ['no_script']];

        $this->assertTrue($this->validator->validate(['content' => 'Safe content'], $rules));
        $this->assertFalse($this->validator->validate(['content' => '<script>alert("xss")</script>'], $rules));
        $this->assertFalse($this->validator->validate(['content' => 'javascript:void(0)'], $rules));
    }

    public function testMultipleRules(): void
    {
        $rules = [
            'email' => ['required', 'email'],
            'password' => ['required', ['min_length', 8]],
            'age' => ['numeric', ['min', 18]]
        ];

        $validData = [
            'email' => 'user@example.com',
            'password' => 'securepass123',
            'age' => 25
        ];

        $this->assertTrue($this->validator->validate($validData, $rules));

        $invalidData = [
            'email' => 'invalid-email',
            'password' => 'short',
            'age' => 15
        ];

        $this->assertFalse($this->validator->validate($invalidData, $rules));
        $errors = $this->validator->getErrors();
        $this->assertCount(3, $errors);
    }

    public function testInputSanitization(): void
    {
        $dirtyData = [
            'name' => '  John Doe  ',
            'description' => '<script>alert("xss")</script>',
            'email' => 'test@example.com'
        ];

        $cleanData = Validator::sanitizeInput($dirtyData);

        $this->assertEquals('John Doe', $cleanData['name']);
        $this->assertStringNotContainsString('<script>', $cleanData['description']);
        $this->assertEquals('test@example.com', $cleanData['email']);
    }

    public function testCSRFTokenGeneration(): void
    {
        // Start session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token1 = Validator::generateCSRFToken();
        $token2 = Validator::generateCSRFToken();

        $this->assertNotEmpty($token1);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
        $this->assertTrue(Validator::validateCSRFToken($token2));
        $this->assertFalse(Validator::validateCSRFToken('invalid-token'));

        session_destroy();
    }
}