<?php
declare(strict_types=1);

class CommentAdminController extends Controller
{
    public function index(): void
    {
        Middleware::requireRole(['admin']);

        $status = $_GET['status'] ?? 'pending';
        $allowed = ['pending','tampil','spam'];
        if (!in_array($status, $allowed, true)) $status = 'pending';

        $rows = Database::query(
            "SELECT k.*, b.judul
             FROM komentar k
             JOIN berita b ON b.id_berita = k.id_berita
             WHERE k.status = :status::status_komentar
             ORDER BY k.dibuat_pada DESC
             LIMIT 100",
            ['status' => $status]
        )->fetchAll();

        $this->view('admin/comments/index', [
            'title' => 'Moderasi Komentar',
            'rows' => $rows,
            'status' => $status,
        ]);
    }

    public function approve(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        Database::query(
            "UPDATE komentar SET status='tampil' WHERE id_komentar = :id",
            ['id' => $id]
        );

        $this->log('APPROVE_KOMENTAR', 'komentar', $id, null);
        $this->flash('success', 'Komentar disetujui.');
        header('Location: ' . BASE_URL . '/admin/comments?status=pending'); exit;
    }

    public function spam(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        Database::query(
            "UPDATE komentar SET status='spam' WHERE id_komentar = :id",
            ['id' => $id]
        );

        $this->log('SPAM_KOMENTAR', 'komentar', $id, null);
        $this->flash('success', 'Komentar ditandai spam.');
        header('Location: ' . BASE_URL . '/admin/comments?status=pending'); exit;
    }

    public function delete(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        Database::query("DELETE FROM komentar WHERE id_komentar = :id", ['id' => $id]);

        $this->log('HAPUS_KOMENTAR', 'komentar', $id, null);
        $this->flash('success', 'Komentar dihapus.');
        header('Location: ' . BASE_URL . '/admin/comments?status=pending'); exit;
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
