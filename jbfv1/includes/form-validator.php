<?php
// Form validation helper for Joy Bible Fellowship
require_once __DIR__ . '/../config/security.php';

class FormValidator {
    private $errors = [];
    private $data = [];
    
    public function __construct($formData) {
        $this->data = $formData;
    }
    
    // Required field validation
    public function required($field, $label) {
        if (empty($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = "{$label} is required";
            return false;
        }
        return true;
    }
    
    // Email validation
    public function email($field, $label) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        if (!validateEmail($this->data[$field])) {
            $this->errors[$field] = "Please enter a valid {$label}";
            return false;
        }
        return true;
    }
    
    // Password validation
    public function password($field, $label) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        $passwordErrors = validatePasswordStrength($this->data[$field]);
        if (!empty($passwordErrors)) {
            $this->errors[$field] = implode(', ', $passwordErrors);
            return false;
        }
        return true;
    }
    
    // Password confirmation
    public function passwordConfirm($passwordField, $confirmField, $label) {
        if (!$this->required($confirmField, $label)) {
            return false;
        }
        
        if ($this->data[$passwordField] !== $this->data[$confirmField]) {
            $this->errors[$confirmField] = "Passwords do not match";
            return false;
        }
        return true;
    }
    
    // Length validation
    public function minLength($field, $label, $min) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        if (strlen($this->data[$field]) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters long";
            return false;
        }
        return true;
    }
    
    public function maxLength($field, $label, $max) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        if (strlen($this->data[$field]) > $max) {
            $this->errors[$field] = "{$label} must be no more than {$max} characters long";
            return false;
        }
        return true;
    }
    
    // Pattern validation
    public function pattern($field, $label, $pattern, $message) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        if (!preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = $message;
            return false;
        }
        return true;
    }
    
    // Numeric validation
    public function numeric($field, $label) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        if (!is_numeric($this->data[$field])) {
            $this->errors[$field] = "{$label} must be a number";
            return false;
        }
        return true;
    }
    
    // Date validation
    public function date($field, $label) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
        if (!$date || $date->format('Y-m-d') !== $this->data[$field]) {
            $this->errors[$field] = "Please enter a valid {$label}";
            return false;
        }
        return true;
    }
    
    // Phone validation
    public function phone($field, $label) {
        if (!$this->required($field, $label)) {
            return false;
        }
        
        $phone = preg_replace('/[^0-9+()-]/', '', $this->data[$field]);
        if (strlen($phone) < 10) {
            $this->errors[$field] = "Please enter a valid {$label}";
            return false;
        }
        return true;
    }
    
    // File validation
    public function file($field, $label, $allowedTypes = [], $maxSize = 5242880) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            $this->errors[$field] = "{$label} is required";
            return false;
        }
        
        $file = $_FILES[$field];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$field] = "Error uploading {$label}";
            return false;
        }
        
        if ($file['size'] > $maxSize) {
            $this->errors[$field] = "{$label} must be smaller than " . ($maxSize / 1024 / 1024) . "MB";
            return false;
        }
        
        if (!empty($allowedTypes)) {
            $fileInfo = pathinfo($file['name']);
            $extension = strtolower($fileInfo['extension']);
            
            if (!in_array($extension, $allowedTypes)) {
                $this->errors[$field] = "{$label} must be one of: " . implode(', ', $allowedTypes);
                return false;
            }
        }
        
        return true;
    }
    
    // Custom validation
    public function custom($field, $callback, $message) {
        if (!$this->required($field, '')) {
            return false;
        }
        
        if (!$callback($this->data[$field])) {
            $this->errors[$field] = $message;
            return false;
        }
        return true;
    }
    
    // Get sanitized data
    public function getSanitizedData() {
        $sanitized = [];
        foreach ($this->data as $key => $value) {
            $sanitized[$key] = sanitizeInput($value);
        }
        return $sanitized;
    }
    
    // Get errors
    public function getErrors() {
        return $this->errors;
    }
    
    // Check if validation passed
    public function isValid() {
        return empty($this->errors);
    }
    
    // Get first error
    public function getFirstError() {
        return reset($this->errors);
    }
    
    // Get error for specific field
    public function getFieldError($field) {
        return $this->errors[$field] ?? '';
    }
    
    // Clear errors
    public function clearErrors() {
        $this->errors = [];
    }
    
    // Add custom error
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
}

// Common validation patterns
class ValidationPatterns {
    const NAME = '/^[a-zA-Z\s\'-]+$/';
    const USERNAME = '/^[a-zA-Z0-9_-]{3,20}$/';
    const PHONE = '/^[\+]?[1-9][\d]{0,15}$/';
    const ZIP_CODE = '/^\d{5}(-\d{4})?$/';
    const URL = '/^https?:\/\/.+/';
    const ALPHA_NUMERIC = '/^[a-zA-Z0-9\s]+$/';
    const DECIMAL = '/^\d+(\.\d{1,2})?$/';
}
?>
