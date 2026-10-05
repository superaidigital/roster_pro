<?php
declare(strict_types=1);

final class ElectronicSignature {
    private const MAX_BYTES = 1048576; // 1 MiB decoded image
    private const MAX_WIDTH = 2400;
    private const MAX_HEIGHT = 1200;

    public static function normalize(string $dataUrl, string $method = 'DRAW'): array {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '') {
            throw new InvalidArgumentException('Signature data is empty.');
        }

        if (!preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $matches)) {
            throw new InvalidArgumentException('Signature must be a PNG or JPEG data URL.');
        }

        $subtype = strtolower((string)$matches[1]);
        $mime = $subtype === 'jpeg' ? 'image/jpeg' : 'image/png';
        $base64 = preg_replace('/\s+/', '', (string)$matches[2]) ?? '';
        $binary = base64_decode($base64, true);

        if (!is_string($binary) || $binary === '') {
            throw new InvalidArgumentException('Signature image payload is invalid.');
        }

        $bytes = strlen($binary);
        if ($bytes > self::MAX_BYTES) {
            throw new InvalidArgumentException('Signature image exceeds the 1 MiB limit.');
        }

        $info = @getimagesizefromstring($binary);
        if (!is_array($info) || empty($info[0]) || empty($info[1]) || empty($info[2])) {
            throw new InvalidArgumentException('Signature image structure is invalid.');
        }

        $expectedType = $mime === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if ((int)$info[2] !== $expectedType) {
            throw new InvalidArgumentException('Signature MIME type does not match its image signature.');
        }

        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            throw new InvalidArgumentException('Signature image dimensions are too large.');
        }

        $method = strtoupper(trim($method));
        if (!in_array($method, ['DRAW', 'UPLOAD'], true)) {
            throw new InvalidArgumentException('Signature capture method is invalid.');
        }

        return [
            'data_url' => 'data:' . $mime . ';base64,' . base64_encode($binary),
            'sha256' => hash('sha256', $binary),
            'method' => $method,
            'mime' => $mime,
            'bytes' => $bytes,
            'width' => $width,
            'height' => $height,
        ];
    }

    public static function isValid(?string $dataUrl): bool {
        if (!is_string($dataUrl) || trim($dataUrl) === '') {
            return false;
        }

        try {
            self::normalize($dataUrl, 'DRAW');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function fingerprint(?string $dataUrl): ?string {
        if (!is_string($dataUrl) || trim($dataUrl) === '') {
            return null;
        }

        try {
            return (string)self::normalize($dataUrl, 'DRAW')['sha256'];
        } catch (Throwable $e) {
            return null;
        }
    }
}
