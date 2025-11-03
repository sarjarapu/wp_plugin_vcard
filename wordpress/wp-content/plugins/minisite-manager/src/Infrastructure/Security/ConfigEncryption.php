<?php

namespace Minisite\Infrastructure\Security;

class ConfigEncryption
{
    private static ?string $key = null;
    
    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(16);
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return base64_encode($iv . $tag . $ciphertext);
    }
    
    public static function decrypt(string $encrypted): ?string
    {
        $key = self::getKey();
        $data = base64_decode($encrypted);
        
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        
        return openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        ) ?: null;
    }
    
    /**
     * Get encryption key from wp-config.php
     * 
     * Rationale: Key must be outside the database to avoid bootstrap problem
     * and maintain security separation between the key and encrypted data.
     */
    private static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        
        // Encryption key must be defined in wp-config.php
        // This is a security requirement, not a convenience choice
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            throw new \RuntimeException(
                'MINISITE_ENCRYPTION_KEY constant must be defined in wp-config.php. ' .
                'This key is required to decrypt sensitive configuration values. ' .
                'Generate with: base64_encode(random_bytes(32))'
            );
        }
        
        self::$key = base64_decode(constant('MINISITE_ENCRYPTION_KEY'));
        
        if (self::$key === false || strlen(self::$key) !== 32) {
            throw new \RuntimeException('Invalid MINISITE_ENCRYPTION_KEY - must be 32-byte key encoded as base64');
        }
        
        return self::$key;
    }
}

