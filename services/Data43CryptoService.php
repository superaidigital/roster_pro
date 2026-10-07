<?php
final class Data43CryptoService
{
    private string $key;

    public function __construct()
    {
        $raw = trim((string)(getenv('DATA43_RECORD_KEY') ?: ''));
        if ($raw === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า DATA43_RECORD_KEY สำหรับข้อมูล 43 แฟ้ม');
        }

        // Allow a 64-hex key or arbitrary secret text.
        $this->key = preg_match('/^[a-f0-9]{64}$/i', $raw)
            ? hex2bin($raw)
            : hash('sha256', $raw, true);
    }

    public function encrypt(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($json, $nonce, $this->key);
            return [
                'algorithm' => 'SODIUM_SECRETBOX',
                'ciphertext' => base64_encode($cipher),
                'nonce' => base64_encode($nonce),
                'sha256' => hash('sha256', $json),
            ];
        }

        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($json, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RuntimeException('ไม่สามารถเข้ารหัสข้อมูล 43 แฟ้มได้');
        }
        return [
            'algorithm' => 'AES-256-GCM',
            'ciphertext' => base64_encode($tag . $cipher),
            'nonce' => base64_encode($nonce),
            'sha256' => hash('sha256', $json),
        ];
    }

    public function decrypt(string $ciphertext, string $nonce, string $algorithm): array
    {
        $nonceRaw = base64_decode($nonce, true);
        $cipherRaw = base64_decode($ciphertext, true);
        if ($nonceRaw === false || $cipherRaw === false) {
            throw new RuntimeException('ข้อมูลเข้ารหัสเสียหาย');
        }

        if ($algorithm === 'SODIUM_SECRETBOX') {
            if (!function_exists('sodium_crypto_secretbox_open')) {
                throw new RuntimeException('PHP Sodium extension ไม่พร้อมใช้งาน');
            }
            $json = sodium_crypto_secretbox_open($cipherRaw, $nonceRaw, $this->key);
            if ($json === false) throw new RuntimeException('ถอดรหัสข้อมูลไม่สำเร็จ');
        } elseif ($algorithm === 'AES-256-GCM') {
            if (strlen($cipherRaw) < 16) throw new RuntimeException('ข้อมูลเข้ารหัสไม่สมบูรณ์');
            $tag = substr($cipherRaw, 0, 16);
            $cipher = substr($cipherRaw, 16);
            $json = openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonceRaw, $tag);
            if ($json === false) throw new RuntimeException('ถอดรหัสข้อมูลไม่สำเร็จ');
        } else {
            throw new RuntimeException('ไม่รู้จักวิธีเข้ารหัสข้อมูล');
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }

    public function cidHash(?string $cid): ?string
    {
        $digits = preg_replace('/\D/', '', (string)$cid);
        return strlen($digits) === 13 ? hash_hmac('sha256', $digits, $this->key) : null;
    }

    public function blindToken(string $value): string
    {
        return hash_hmac('sha256', $this->normalizeSearch($value), $this->key);
    }

    public function prefixTokens(string $value, int $min = 2, int $max = 20): array
    {
        $normalized = $this->normalizeSearch($value);
        if ($normalized === '') return [];
        $length = mb_strlen($normalized, 'UTF-8');
        $tokens = [];
        for ($i = max(1, $min); $i <= min($length, $max); $i++) {
            $tokens[] = hash_hmac('sha256', mb_substr($normalized, 0, $i, 'UTF-8'), $this->key);
        }
        return array_values(array_unique($tokens));
    }

    private function normalizeSearch(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }
}
?>