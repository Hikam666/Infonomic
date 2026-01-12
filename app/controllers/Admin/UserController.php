<?php
declare(strict_types=1);

class UserController extends Controller
{
    public function index(): void
    {
        Middleware::requireRole(['admin']);

        $rows = Database::query(
            "SELECT id_user, nama, email, role, aktif, dibuat_pada
             FROM users
             ORDER BY dibuat_pada DESC
             LIMIT 200"
        )->fetchAll();

        $this->view('admin/users/index', [
            'title' => 'Manajemen User',
            'rows' => $rows,
        ]);
    }

    public function create(): void
    {
        Middleware::requireRole(['admin']);

        $this->view('admin/users/form', [
            'title' => 'Tambah User',
            'mode' => 'create',
            'data' => null,
        ]);
    }

    public function store(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'reporter';
        $aktif = isset($_POST['aktif']) ? true : true; 
        $password = (string)($_POST['password'] ?? '');

        if ($nama === '' || $email === '' || $password === '') {
            $this->flash('error', 'Nama, email, dan password wajib.');
            header('Location: ' . BASE_URL . '/admin/users/create'); exit;
        }

        $allowed = ['admin','editor','reporter'];
        if (!in_array($role, $allowed, true)) $role = 'reporter';

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = Database::query(
            "INSERT INTO users (nama, email, password_hash, role, aktif)
             VALUES (:nama, :email, :hash, :role::role_user, :aktif)
             RETURNING id_user",
            [
                'nama' => $nama,
                'email' => $email,
                'hash' => $hash,
                'role' => $role,
                'aktif' => $aktif
            ]
        );
        $id = (int) $stmt->fetch()['id_user'];

        $this->log('TAMBAH_USER', 'user', $id, null);
        $this->flash('success', 'User berhasil ditambahkan.');
        header('Location: ' . BASE_URL . '/admin/users'); exit;
    }

    public function edit(): void
    {
        Middleware::requireRole(['admin']);

        $id = $this->getIdFromUri();
        $data = Database::query(
            "SELECT id_user, nama, email, role, aktif
             FROM users
             WHERE id_user = :id",
            ['id' => $id]
        )->fetch();

        if (!$data) {
            http_response_code(404); echo "User tidak ditemukan"; return;
        }

        $this->view('admin/users/form', [
            'title' => 'Edit User',
            'mode' => 'edit',
            'data' => $data,
        ]);
    }

    public function update(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'reporter';
        $aktif = isset($_POST['aktif']) ? true : false;
        $password = (string)($_POST['password'] ?? '');

        if ($nama === '' || $email === '') {
            $this->flash('error', 'Nama dan email wajib.');
            header('Location: ' . BASE_URL . '/admin/users/edit/' . $id); exit;
        }

        $allowed = ['admin','editor','reporter'];
        if (!in_array($role, $allowed, true)) $role = 'reporter';

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            Database::query(
                "UPDATE users
                 SET nama=:nama, email=:email, role=:role::role_user, aktif=:aktif, password_hash=:hash
                 WHERE id_user=:id",
                [
                    'nama' => $nama,
                    'email' => $email,
                    'role' => $role,
                    'aktif' => $aktif,
                    'hash' => $hash,
                    'id' => $id
                ]
            );
        } else {
            Database::query(
                "UPDATE users
                 SET nama=:nama, email=:email, role=:role::role_user, aktif=:aktif
                 WHERE id_user=:id",
                [
                    'nama' => $nama,
                    'email' => $email,
                    'role' => $role,
                    'aktif' => $aktif,
                    'id' => $id
                ]
            );
        }

        $this->log('EDIT_USER', 'user', $id, null);
        $this->flash('success', 'User diperbarui.');
        header('Location: ' . BASE_URL . '/admin/users'); exit;
    }

    public function delete(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();

        if (Auth::id() === $id) {
            $this->flash('error', 'Tidak bisa menghapus akun sendiri.');
            header('Location: ' . BASE_URL . '/admin/users'); exit;
        }

        Database::query("DELETE FROM users WHERE id_user = :id", ['id' => $id]);

        $this->log('HAPUS_USER', 'user', $id, null);
        $this->flash('success', 'User dihapus.');
        header('Location: ' . BASE_URL . '/admin/users'); exit;
    }

    private function getIdFromUri(): int
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        $parts = array_values(array_filter(explode('/', $path)));
        return (int) end($parts);
    }

    private function log(string $aksi, string $objekTipe, int $objekId, ?string $keterangan): void
    {
        Database::query(
            "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
             VALUES (:id_user, :aksi, :tipe, :oid, :ket)",
            [
                'id_user' => Auth::id(),
                'aksi' => $aksi,
                'tipe' => $objekTipe,
                'oid' => $objekId,
                'ket' => $keterangan
            ]
        );
    }

    private function flash(string $key, string $msg): void
    {
        $_SESSION['flash'][$key] = $msg;
    }

    private function csrfGuard(): void
    {
        if (class_exists('CSRF')) {
            $token = $_POST['_token'] ?? '';
            if (!CSRF::verify($token)) {
                http_response_code(419);
                echo "CSRF token tidak valid";
                exit;
            }
        }
    }
}
