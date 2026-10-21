<?php

declare(strict_types=1);

namespace Modules\Core\Support;

final class SensitiveDataMasker
{
    public const REDACTED = '[REDACTED]';

    /**
     * Keys are compared after lowercasing and removing "-" and "_", so "X-Api-Key" and "apiKey" both match.
     *
     * @var list<string>
     */
    private const CARD_KEYS = ['cardnumber', 'pan'];

    /**
     * @var list<string>
     */
    private const SECRET_KEY_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'apikey',
        'authorization',
        'cvv',
        'cvc',
        'clientid',
        'cookie',
    ];

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public function mask(array $payload): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            $normalized = is_string($key) ? $this->normalize($key) : null;

            if ($normalized !== null && in_array($normalized, self::CARD_KEYS, true) && is_scalar($value)) {
                $masked[$key] = $this->maskCard((string) $value);

                continue;
            }

            if ($normalized !== null && $this->isSecret($normalized)) {
                $masked[$key] = self::REDACTED;

                continue;
            }

            $masked[$key] = is_array($value) ? $this->mask($value) : $value;
        }

        return $masked;
    }

    private function normalize(string $key): string
    {
        return str_replace(['-', '_'], '', strtolower($key));
    }

    private function isSecret(string $normalizedKey): bool
    {
        foreach (self::SECRET_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function maskCard(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) <= 4) {
            return self::REDACTED;
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }
}
