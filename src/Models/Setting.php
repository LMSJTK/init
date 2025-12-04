<?php
/**
 * Settings Model
 */

namespace StartupGame\Models;

class Setting extends Model
{
    protected static string $table = 'settings';

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::findWhere(['setting_key' => $key]);
        if (!$setting) return $default;

        $value = $setting['setting_value'];

        // Decrypt if encrypted
        if ($setting['encrypted'] && $value) {
            $value = self::decrypt($value);
        }

        return $value;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value, bool $encrypt = false): void
    {
        $existing = self::findWhere(['setting_key' => $key]);

        if ($encrypt && $value) {
            $value = self::encrypt($value);
        }

        if ($existing) {
            self::update($existing['id'], [
                'setting_value' => $value,
                'encrypted' => $encrypt ? 1 : 0
            ]);
        } else {
            self::create([
                'setting_key' => $key,
                'setting_value' => $value,
                'encrypted' => $encrypt ? 1 : 0
            ]);
        }
    }

    /**
     * Delete a setting
     */
    public static function remove(string $key): void
    {
        $existing = self::findWhere(['setting_key' => $key]);
        if ($existing) {
            self::delete($existing['id']);
        }
    }

    /**
     * Simple encryption for API keys
     */
    private static function encrypt(string $value): string
    {
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt an encrypted value
     */
    private static function decrypt(string $value): string
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($value);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key from config or generate one
     */
    private static function getEncryptionKey(): string
    {
        $keyFile = __DIR__ . '/../../storage/.encryption_key';
        if (file_exists($keyFile)) {
            return file_get_contents($keyFile);
        }

        // Generate a new key
        $key = bin2hex(random_bytes(32));
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);
        return $key;
    }

    /**
     * Check if an API key is configured
     */
    public static function hasApiKey(string $provider): bool
    {
        $keyName = match($provider) {
            'gemini-3' => 'gemini_api_key',
            'chatgpt-5.1' => 'openai_api_key',
            'claude-sonnet-4.5', 'claude-opus-4.5' => 'anthropic_api_key',
            default => null
        };

        if (!$keyName) return false;

        return !empty(self::get($keyName));
    }
}
