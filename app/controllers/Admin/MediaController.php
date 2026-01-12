<?php
declare(strict_types=1);

class MediaController extends Controller
{
    public function index(): void
    {
        Middleware::requireLogin();

        $q = trim($_GET['q'] ?? '');
        $params = [];
        $where = '';

        if ($q !== '') {
            $where = "WHERE (judul_media ILIKE :q OR path_file ILIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $rows = Database::query(
            "SELECT m.*, u.nama AS pengunggah
             FROM media m
             LEFT JOIN users u ON u.id_user = m.id_pengunggah
             {$where}
             ORDER BY m.dibuat_pada DESC
             LIMIT 100",
            $params
        )->fetchAll();

        $this->view('admin/media/index', [
            'title' => 'Media Library',
            'rows' => $rows,
            'q' => $q,
        ]);
    }

    public function upload(): void
    {
        Middleware::requireLogin();
        $this->csrfGuard();

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Upload gagal.');
            header('Location: ' . BASE_URL . '/admin/media'); exit;
        }

        $tipe = $_POST['tipe'] ?? 'gambar';
        $allowedTipe = ['gambar','infografis','video'];
        if (!in_array($tipe, $allowedTipe, true)) $tipe = 'gambar';

        $judul = trim($_POST['judul_media'] ?? '');

        $tmp = $_FILES['file']['tmp_name'];
        $origName = $_FILES['file']['name'];
        $size = (int) $_FILES['file']['size'];
        $mime = $_FILES['file']['type'] ?? null;

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $safeName = bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
        $uploadDir = BASE_PATH . '/public/uploads';

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $dest = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            $this->flash('error', 'Gagal menyimpan file.');
            header('Location: ' . BASE_URL . '/admin/media'); exit;
        }

        $publicPath = BASE_URL . '/uploads/' . $safeName;

        $idUser = Auth::id();
        $stmt = Database::query(
            "INSERT INTO media (tipe, judul_media, path_file, ukuran_file, mime_type, ekstensi, id_pengunggah)
             VALUES (:tipe, :judul, :path, :size, :mime, :ext, :uid)
             RETURNING id_media",
            [
                'tipe' => $tipe,
                'judul' => $judul !== '' ? $judul : null,
                'path' => $publicPath,
                'size' => $size,
                'mime' => $mime,
                'ext' => $ext !== '' ? $ext : null,
                'uid' => $idUser
            ]
        );
        $idMedia = (int) $stmt->fetch()['id_media'];

        Database::query(
            "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
             VALUES (:id_user, 'UPLOAD_MEDIA', 'media', :objek_id, NULL)",
            ['id_user' => $idUser, 'objek_id' => $idMedia]
        );

        $this->flash('success', 'Media berhasil diupload.');
        header('Location: ' . BASE_URL . '/admin/media'); exit;
    }

    public function delete(): void
    {
        Middleware::requireRole(['admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();

        $row = Database::query("SELECT * FROM media WHERE id_media = :id", ['id' => $id])->fetch();
        if (!$row) { http_response_code(404); echo "Not found"; return; }

        if (isset($row['path_file']) && is_string($row['path_file'])) {
            $uploadsPrefix = BASE_URL . '/uploads/';
            if (str_starts_with($row['path_file'], $uploadsPrefix)) {
                $fileName = substr($row['path_file'], strlen($uploadsPrefix));
                $filePath = BASE_PATH . '/public/uploads/' . $fileName;
                if (is_file($filePath)) @unlink($filePath);
            }
        }

        Database::query("DELETE FROM media WHERE id_media = :id", ['id' => $id]);

        Database::query(
            "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
             VALUES (:id_user, 'HAPUS_MEDIA', 'media', :objek_id, NULL)",
            ['id_user' => Auth::id(), 'objek_id' => $id]
        );

        $this->flash('success', 'Media dihapus.');
        header('Location: ' . BASE_URL . '/admin/media'); exit;
    }

    private function getIdFromUri(): int
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        $parts = array_values(array_filter(explode('/', $path)));
        return (int) end($parts);
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
