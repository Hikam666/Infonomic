<?php
declare(strict_types=1);

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            header('Location: ' . BASE_URL . '/admin/dashboard');
            exit;
        }

        $this->view('admin/auth/login', [
            'title' => 'Login Admin'
        ]);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->view('admin/auth/login', [
                'title' => 'Login Admin',
                'error' => 'Email dan password wajib diisi.'
            ]);
            return;
        }

        $stmt = Database::query(
            "SELECT id_user, nama, email, password_hash, role, aktif
             FROM users
             WHERE email = :email
             LIMIT 1",
            ['email' => $email]
        );
        $user = $stmt->fetch();

        if (!$user || !$user['aktif'] || !password_verify($password, $user['password_hash'])) {
            $this->view('admin/auth/login', [
                'title' => 'Login Admin',
                'error' => 'Login gagal. Cek email/password.'
            ]);
            return;
        }

        Auth::login($user);

        Database::query(
            "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
             VALUES (:id_user, 'LOGIN', 'user', :objek_id, NULL)",
            ['id_user' => $user['id_user'], 'objek_id' => $user['id_user']]
        );

        header('Location: ' . BASE_URL . '/admin/dashboard');
        exit;
    }

    public function logout(): void
    {
        Middleware::requireLogin();

        $id = Auth::id();
        if ($id !== null) {
            Database::query(
                "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
                 VALUES (:id_user, 'LOGOUT', 'user', :objek_id, NULL)",
                ['id_user' => $id, 'objek_id' => $id]
            );
        }

        Auth::logout();
        header('Location: ' . BASE_URL . '/admin/login');
        exit;
    }
}
