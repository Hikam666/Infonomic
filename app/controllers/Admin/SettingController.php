<?php
declare(strict_types=1);

class SettingController extends Controller
{
    public function index(): void
    {
        Middleware::requireRole(['admin']);

        $row = Database::query(
            "SELECT * FROM identitas_website WHERE id_identitas = 1"
        )->fetch();

        $this->view('admin/settings/index', [
            'title' => 'Pengaturan Website',
            'data' => $row,
        ]);
    }

    public function update(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $nama = trim($_POST['nama_website'] ?? '');
        $logo = trim($_POST['logo_path'] ?? '');
        $desc = trim($_POST['deskripsi_singkat'] ?? '');

        if ($nama === '') {
            $this->flash('error', 'Nama website wajib diisi.');
            header('Location: ' . BASE_URL . '/admin/settings'); exit;
        }

        Database::query(
            "UPDATE identitas_website
             SET nama_website = :nama,
                 logo_path = :logo,
                 deskripsi_singkat = :deskripsi
             WHERE id_identitas = 1",
            [
                'nama' => $nama,
                'logo' => $logo !== '' ? $logo : null,
                'deskripsi' => $desc !== '' ? $desc : null
            ]
        );

        Database::query(
            "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
             VALUES (:id_user, 'UPDATE_SETTING', 'setting', 1, NULL)",
            ['id_user' => Auth::id()]
        );

        $this->flash('success', 'Pengaturan disimpan.');
        header('Location: ' . BASE_URL . '/admin/settings'); exit;
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
