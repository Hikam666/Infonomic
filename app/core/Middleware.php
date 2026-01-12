<?php
declare(strict_types=1);

class Middleware
{
    public static function requireLogin(): void
    {
        if (!Auth::check()) {
            header('Location: ' . BASE_URL . '/admin/login');
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $role = Auth::role();
        if ($role === null || !in_array($role, $roles, true)) {
            http_response_code(403);
            echo "403 Forbidden";
            exit;
        }
    }
}
