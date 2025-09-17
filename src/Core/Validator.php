<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules);
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    private function validateField(string $field, $value, array $rules): void
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $this->applyRule($field, $value, $rule);
            } elseif (is_array($rule)) {
                $ruleName = $rule[0];
                $ruleValue = $rule[1] ?? null;
                $this->applyRule($field, $value, $ruleName, $ruleValue);
            }
        }
    }

    private function applyRule(string $field, $value, string $rule, $ruleValue = null): void
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->errors[] = ucfirst($field) . ' is required';
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[] = ucfirst($field) . ' must be a valid email address';
                }
                break;

            case 'min_length':
                if (!empty($value) && strlen($value) < $ruleValue) {
                    $this->errors[] = ucfirst($field) . " must be at least {$ruleValue} characters";
                }
                break;

            case 'max_length':
                if (!empty($value) && strlen($value) > $ruleValue) {
                    $this->errors[] = ucfirst($field) . " must not exceed {$ruleValue} characters";
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[] = ucfirst($field) . ' must be a number';
                }
                break;

            case 'min':
                if (!empty($value) && (float)$value < $ruleValue) {
                    $this->errors[] = ucfirst($field) . " must be at least {$ruleValue}";
                }
                break;

            case 'max':
                if (!empty($value) && (float)$value > $ruleValue) {
                    $this->errors[] = ucfirst($field) . " must not exceed {$ruleValue}";
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $ruleValue)) {
                    $allowed = implode(', ', $ruleValue);
                    $this->errors[] = ucfirst($field) . " must be one of: {$allowed}";
                }
                break;

            case 'unique':
                if (!empty($value)) {
                    [$table, $column] = explode(':', $ruleValue);
                    if ($this->recordExists($table, $column, $value)) {
                        $this->errors[] = ucfirst($field) . ' already exists';
                    }
                }
                break;

            case 'datetime':
                if (!empty($value) && !strtotime($value)) {
                    $this->errors[] = ucfirst($field) . ' must be a valid date and time';
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[] = ucfirst($field) . ' must be a valid URL';
                }
                break;

            case 'alpha_numeric':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    $this->errors[] = ucfirst($field) . ' must contain only letters and numbers';
                }
                break;

            case 'no_script':
                if (!empty($value) && $this->containsScript($value)) {
                    $this->errors[] = ucfirst($field) . ' contains invalid content';
                }
                break;
        }
    }

    private function recordExists(string $table, string $column, $value): bool
    {
        try {
            $stmt = Database::prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function containsScript(string $value): bool
    {
        $dangerous = ['<script', 'javascript:', 'onclick=', 'onerror=', 'onload=', 'eval(', 'expression('];

        foreach ($dangerous as $pattern) {
            if (stripos($value, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function sanitizeInput(array $data): array
    {
        return array_map(function($value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace("\0", '', $value);

                // Trim whitespace
                $value = trim($value);

                // Remove dangerous characters for SQL injection prevention
                // (Note: We use prepared statements, but this is additional protection)
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $data);
    }

    public static function validateCSRFToken(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return hash_equals($sessionToken, $token);
    }

    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
}