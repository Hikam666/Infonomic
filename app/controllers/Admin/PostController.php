<?php
declare(strict_types=1);

class PostController extends Controller
{
    public function index(): void
    {
        Middleware::requireLogin();

        $role = Auth::role();
        $idUser = Auth::id();

        $status = $_GET['status'] ?? null;
        $allowed = ['draft','diajukan','revisi','disetujui','dipublikasikan','arsip'];
        if ($status !== null && !in_array($status, $allowed, true)) {
            $status = null;
        }

        $params = [];
        $where = [];
        if ($status !== null) {
            $where[] = "b.status = :status::status_berita";
            $params['status'] = $status;
        }

        if ($role === 'reporter' && $idUser !== null) {
            $where[] = "b.id_penulis = :id_user";
            $params['id_user'] = $idUser;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $rows = Database::query(
            "SELECT b.*, u.nama AS nama_penulis
             FROM berita b
             JOIN users u ON u.id_user = b.id_penulis
             {$whereSql}
             ORDER BY b.waktu_diubah DESC
             LIMIT 50",
            $params
        )->fetchAll();

        $this->view('admin/posts/index', [
            'title' => 'Manajemen Berita',
            'rows' => $rows,
            'status' => $status,
            'role' => $role,
        ]);
    }

    public function create(): void
    {
        Middleware::requireLogin();

        $role = Auth::role();
        if (!in_array($role, ['admin','editor','reporter'], true)) {
            http_response_code(403); echo "403"; return;
        }

        $kategori = Database::query("SELECT id_kategori, nama_kategori FROM kategori WHERE aktif = TRUE ORDER BY nama_kategori")->fetchAll();
        $tags = Database::query("SELECT id_kata_kunci, nama FROM kata_kunci ORDER BY nama")->fetchAll();

        $this->view('admin/posts/form', [
            'title' => 'Buat Berita',
            'mode' => 'create',
            'kategori' => $kategori,
            'tags' => $tags,
            'data' => null,
            'selectedKategori' => [],
            'selectedTags' => [],
        ]);
    }

    public function store(): void
    {
        Middleware::requireLogin();
        $this->csrfGuard();

        $idPenulis = Auth::id();
        if ($idPenulis === null) {
            http_response_code(401); echo "Unauthorized"; return;
        }

        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $thumbnail = $_POST['thumbnail_media_id'] ?? null;
        $kategoriIds = $_POST['kategori_ids'] ?? [];
        $tagIds = $_POST['tag_ids'] ?? [];

        if ($judul === '' || $isi === '') {
            $this->flash('error', 'Judul dan isi wajib diisi.');
            header('Location: ' . BASE_URL . '/admin/posts/create'); exit;
        }

        $slug = $this->slugify($judul);

        $stmt = Database::query(
            "INSERT INTO berita (judul, slug, isi, status, id_penulis, thumbnail_media_id)
             VALUES (:judul, :slug, :isi, 'draft', :id_penulis, :thumb)
             RETURNING id_berita",
            [
                'judul' => $judul,
                'slug' => $slug,
                'isi' => $isi,
                'id_penulis' => $idPenulis,
                'thumb' => $thumbnail !== '' ? $thumbnail : null,
            ]
        );
        $idBerita = (int) $stmt->fetch()['id_berita'];

        $this->syncKategori($idBerita, $kategoriIds);

        $this->syncTags($idBerita, $tagIds);

        $this->log('BUAT_BERITA', 'berita', $idBerita, null);
        $this->flash('success', 'Berita berhasil dibuat (Draft).');

        header('Location: ' . BASE_URL . '/admin/posts/edit/' . $idBerita);
        exit;
    }

    public function edit(): void
    {
        Middleware::requireLogin();

        $id = $this->getIdFromUri();
        $role = Auth::role();
        $idUser = Auth::id();

        $data = Database::query(
            "SELECT b.*, u.nama AS nama_penulis
             FROM berita b
             JOIN users u ON u.id_user = b.id_penulis
             WHERE b.id_berita = :id",
            ['id' => $id]
        )->fetch();

        if (!$data) {
            http_response_code(404); echo "Berita tidak ditemukan"; return;
        }

        if ($role === 'reporter') {
            $allowedStatus = ['draft','revisi'];
            if ((int)$data['id_penulis'] !== (int)$idUser || !in_array($data['status'], $allowedStatus, true)) {
                http_response_code(403); echo "403 Forbidden"; return;
            }
        }

        $kategori = Database::query("SELECT id_kategori, nama_kategori FROM kategori WHERE aktif = TRUE ORDER BY nama_kategori")->fetchAll();
        $tags = Database::query("SELECT id_kata_kunci, nama FROM kata_kunci ORDER BY nama")->fetchAll();

        $selectedKategori = Database::query(
            "SELECT id_kategori FROM berita_kategori WHERE id_berita = :id",
            ['id' => $id]
        )->fetchAll();
        $selectedKategori = array_map(fn($r) => (int)$r['id_kategori'], $selectedKategori);

        $selectedTags = Database::query(
            "SELECT id_kata_kunci FROM berita_kata_kunci WHERE id_berita = :id",
            ['id' => $id]
        )->fetchAll();
        $selectedTags = array_map(fn($r) => (int)$r['id_kata_kunci'], $selectedTags);

        $this->view('admin/posts/form', [
            'title' => 'Edit Berita',
            'mode' => 'edit',
            'kategori' => $kategori,
            'tags' => $tags,
            'data' => $data,
            'selectedKategori' => $selectedKategori,
            'selectedTags' => $selectedTags,
        ]);
    }

    public function update(): void
    {
        Middleware::requireLogin();
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        $role = Auth::role();
        $idUser = Auth::id();

        $data = Database::query("SELECT * FROM berita WHERE id_berita = :id", ['id' => $id])->fetch();
        if (!$data) { http_response_code(404); echo "Not found"; return; }

        if ($role === 'reporter') {
            $allowedStatus = ['draft','revisi'];
            if ((int)$data['id_penulis'] !== (int)$idUser || !in_array($data['status'], $allowedStatus, true)) {
                http_response_code(403); echo "403 Forbidden"; return;
            }
        }

        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $thumbnail = $_POST['thumbnail_media_id'] ?? null;
        $kategoriIds = $_POST['kategori_ids'] ?? [];
        $tagIds = $_POST['tag_ids'] ?? [];

        if ($judul === '' || $isi === '') {
            $this->flash('error', 'Judul dan isi wajib diisi.');
            header('Location: ' . BASE_URL . '/admin/posts/edit/' . $id); exit;
        }

        $slug = $this->slugify($judul);

        Database::query(
            "UPDATE berita
             SET judul = :judul, slug = :slug, isi = :isi, thumbnail_media_id = :thumb
             WHERE id_berita = :id",
            [
                'judul' => $judul,
                'slug' => $slug,
                'isi' => $isi,
                'thumb' => $thumbnail !== '' ? $thumbnail : null,
                'id' => $id
            ]
        );

        $this->syncKategori($id, $kategoriIds);
        $this->syncTags($id, $tagIds);

        $this->log('EDIT_BERITA', 'berita', $id, null);
        $this->flash('success', 'Perubahan disimpan.');

        header('Location: ' . BASE_URL . '/admin/posts/edit/' . $id);
        exit;
    }

    public function submit(): void
    {
        Middleware::requireLogin();
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        $role = Auth::role();
        $idUser = Auth::id();

        $row = Database::query("SELECT * FROM berita WHERE id_berita = :id", ['id' => $id])->fetch();
        if (!$row) { http_response_code(404); echo "Not found"; return; }

        if ($role !== 'reporter' && $role !== 'admin') {
            http_response_code(403); echo "403"; return;
        }

        if ($role === 'reporter' && (int)$row['id_penulis'] !== (int)$idUser) {
            http_response_code(403); echo "403"; return;
        }
        Database::query(
            "UPDATE berita
             SET status = 'diajukan', waktu_diajukan = NOW()
             WHERE id_berita = :id AND status IN ('draft','revisi')",
            ['id' => $id]
        );

        $this->log('AJUKAN_BERITA', 'berita', $id, null);
        $this->flash('success', 'Berita diajukan ke editor.');

        header('Location: ' . BASE_URL . '/admin/posts?status=diajukan'); exit;
    }

    public function requestRevision(): void
    {
        Middleware::requireRole(['editor','admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        $catatan = trim($_POST['catatan_revisi'] ?? '');

        Database::query(
            "UPDATE berita
             SET status = 'revisi', catatan_revisi = :catatan
             WHERE id_berita = :id AND status = 'diajukan'",
            ['id' => $id, 'catatan' => $catatan !== '' ? $catatan : null]
        );

        $this->log('MINTA_REVISI', 'berita', $id, $catatan !== '' ? $catatan : null);
        $this->flash('success', 'Berita dikembalikan untuk revisi.');

        header('Location: ' . BASE_URL . '/admin/posts?status=revisi'); exit;
    }

    public function approve(): void
    {
        Middleware::requireRole(['editor','admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();
        $idEditor = Auth::id();

        Database::query(
            "UPDATE berita
             SET status = 'disetujui', id_editor_penyetuju = :id_editor, waktu_disetujui = NOW()
             WHERE id_berita = :id AND status = 'diajukan'",
            ['id' => $id, 'id_editor' => $idEditor]
        );

        $this->log('SETUJUI_BERITA', 'berita', $id, null);
        $this->flash('success', 'Berita disetujui.');

        header('Location: ' . BASE_URL . '/admin/posts?status=disetujui'); exit;
    }

    public function publish(): void
    {
        Middleware::requireRole(['editor','admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();

        Database::query(
            "UPDATE berita
             SET status = 'dipublikasikan', waktu_publish = NOW()
             WHERE id_berita = :id AND status IN ('disetujui','diajukan')",
            ['id' => $id]
        );

        $this->log('PUBLISH_BERITA', 'berita', $id, null);
        $this->flash('success', 'Berita dipublikasikan.');

        header('Location: ' . BASE_URL . '/admin/posts?status=dipublikasikan'); exit;
    }

    public function archive(): void
    {
        Middleware::requireRole(['editor','admin']);
        $this->csrfGuard();

        $id = $this->getIdFromUri();

        Database::query(
            "UPDATE berita
             SET status = 'arsip'
             WHERE id_berita = :id",
            ['id' => $id]
        );

        $this->log('ARSIP_BERITA', 'berita', $id, null);
        $this->flash('success', 'Berita diarsipkan.');

        header('Location: ' . BASE_URL . '/admin/posts?status=arsip'); exit;
    }

    private function getIdFromUri(): int
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        $parts = array_values(array_filter(explode('/', $path)));
        $id = (int) end($parts);
        return $id;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/u', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');

        $base = $text !== '' ? $text : 'berita';
        $slug = $base;
        $i = 2;

        while (true) {
            $exists = Database::query(
                "SELECT 1 FROM berita WHERE slug = :slug LIMIT 1",
                ['slug' => $slug]
            )->fetch();
            if (!$exists) break;
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function syncKategori(int $idBerita, array $kategoriIds): void
    {
        Database::query("DELETE FROM berita_kategori WHERE id_berita = :id", ['id' => $idBerita]);

        $kategoriIds = array_unique(array_map('intval', $kategoriIds));
        foreach ($kategoriIds as $kid) {
            if ($kid <= 0) continue;
            Database::query(
                "INSERT INTO berita_kategori (id_berita, id_kategori)
                 VALUES (:b, :k)
                 ON CONFLICT DO NOTHING",
                ['b' => $idBerita, 'k' => $kid]
            );
        }
    }

    private function syncTags(int $idBerita, array $tagIds): void
    {
        Database::query("DELETE FROM berita_kata_kunci WHERE id_berita = :id", ['id' => $idBerita]);

        $tagIds = array_unique(array_map('intval', $tagIds));
        foreach ($tagIds as $tid) {
            if ($tid <= 0) continue;
            Database::query(
                "INSERT INTO berita_kata_kunci (id_berita, id_kata_kunci)
                 VALUES (:b, :t)
                 ON CONFLICT DO NOTHING",
                ['b' => $idBerita, 't' => $tid]
            );
        }
    }

    private function log(string $aksi, string $objekTipe, int $objekId, ?string $keterangan): void
    {
        $idUser = Auth::id();
        Database::query(
            "INSERT INTO log_aktivitas (id_user, aksi, objek_tipe, objek_id, keterangan)
             VALUES (:id_user, :aksi, :tipe, :oid, :ket)",
            [
                'id_user' => $idUser,
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
