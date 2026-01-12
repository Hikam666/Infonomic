<?php
declare(strict_types=1);

class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id_user']) ? (int)$_SESSION['user']['id_user'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function login(array $userRow): void
    {

        $_SESSION['user'] = [
            'id_user' => (int)$userRow['id_user'],
            'nama'    => $userRow['nama'],
            'email'   => $userRow['email'],
            'role'    => $userRow['role'],
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }
}
