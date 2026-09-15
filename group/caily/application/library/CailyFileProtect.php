<?php
/**
 * AES-256-GCM helpers for CAILY protected attachments (.cailyenc).
 * Ciphertext is useless without the server master key.
 */
class CailyFileProtect
{
    const MAGIC = 'CAILY1';
    const ALGO = 'aes-256-gcm';
    const IV_LEN = 12;
    const TAG_LEN = 16;
    const KEY_LEN = 32;

    public static function masterKey()
    {
        $fromEnv = getenv('CAILY_FILE_PROTECT_KEY');
        if (is_string($fromEnv) && strlen($fromEnv) >= 16) {
            return hash('sha256', $fromEnv, true);
        }
        // Fallback: derive from install path (set CAILY_FILE_PROTECT_KEY in .env for production)
        $seed = (defined('DIR_PATH') ? DIR_PATH : __DIR__) . '|caily-file-protect-v1';
        return hash('sha256', $seed, true);
    }

    /**
     * Build .cailyenc binary: MAGIC(6) | ver(1) | iv(12) | tag(16) | ciphertext
     */
    public static function encryptBytes($plaintext)
    {
        $key = self::masterKey();
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $cipher = openssl_encrypt(
            $plaintext,
            self::ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );
        if ($cipher === false) {
            throw new RuntimeException('encrypt failed');
        }
        return self::MAGIC . chr(1) . $iv . $tag . $cipher;
    }

    public static function decryptBytes($blob)
    {
        if (!is_string($blob) || strlen($blob) < 6 + 1 + self::IV_LEN + self::TAG_LEN + 1) {
            throw new RuntimeException('invalid blob');
        }
        if (substr($blob, 0, 6) !== self::MAGIC) {
            throw new RuntimeException('bad magic');
        }
        $offset = 6;
        $ver = ord($blob[$offset]);
        $offset += 1;
        if ($ver !== 1) {
            throw new RuntimeException('unsupported version');
        }
        $iv = substr($blob, $offset, self::IV_LEN);
        $offset += self::IV_LEN;
        $tag = substr($blob, $offset, self::TAG_LEN);
        $offset += self::TAG_LEN;
        $cipher = substr($blob, $offset);
        $plain = openssl_decrypt(
            $cipher,
            self::ALGO,
            self::masterKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($plain === false) {
            throw new RuntimeException('decrypt failed');
        }
        return $plain;
    }

    public static function encryptedDownloadName($originalName)
    {
        $base = preg_replace('/\.[^.]+$/', '', (string)$originalName);
        if ($base === null || $base === '') {
            $base = 'file';
        }
        return $base . '.cailyenc';
    }

    public static function isProtectedFlag($value)
    {
        return $value === 1 || $value === '1' || $value === true;
    }
}
