<?php
declare(strict_types=1);

class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    private const TTL_SECONDS = 7200;

    public static function token(): string
    {
        if (!isset($_SESSION)) {

            @session_start();
        }

        $now = time();

        if (
            empty($_SESSION[self::SESSION_KEY]['value']) ||
            empty($_SESSION[self::SESSION_KEY]['created_at']) ||
            ($now - (int)$_SESSION[self::SESSION_KEY]['created_at']) > self::TTL_SECONDS
        ) {
            $_SESSION[self::SESSION_KEY] = [
                'value' => bin2hex(random_bytes(32)),
                'created_at' => $now,
            ];
        }

        return (string)$_SESSION[self::SESSION_KEY]['value'];
    }

    public static function verify(?string $token): bool
    {
        if (!isset($_SESSION)) {
            @session_start();
        }

        if ($token === null || $token === '') {
            return false;
        }

        if (empty($_SESSION[self::SESSION_KEY]['value']) || empty($_SESSION[self::SESSION_KEY]['created_at'])) {
            return false;
        }

        $now = time();
        $createdAt = (int)$_SESSION[self::SESSION_KEY]['created_at'];

        // expired
        if (($now - $createdAt) > self::TTL_SECONDS) {
            return false;
        }

        return hash_equals((string)$_SESSION[self::SESSION_KEY]['value'], $token);
    }

    public static function field(): string
    {
        $t = self::token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function regenerate(): void
    {
        if (!isset($_SESSION)) {
            @session_start();
        }

        $_SESSION[self::SESSION_KEY] = [
            'value' => bin2hex(random_bytes(32)),
            'created_at' => time(),
        ];
    }
}
