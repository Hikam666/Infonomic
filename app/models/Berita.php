<?php

class Berita {
    private $db;
    private $table = 'berita';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    // ============================================================
    // CRUD DASAR
    // ============================================================
    
    /**
     * Get berita by ID
     */
    public function getById($id_berita) {
        $query = "SELECT b.*, 
                         u.nama as nama_penulis,
                         e.nama as nama_editor,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN users e ON b.id_editor_penyetuju = e.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.id_berita = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id_berita, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get berita by slug (untuk frontend)
     */
    public function getBySlug($slug) {
        $query = "SELECT b.*, 
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.slug = :slug";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create draft berita (untuk Reporter)
     */
    public function createDraft($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (judul, slug, isi, id_penulis, thumbnail_media_id, status) 
                  VALUES (:judul, :slug, :isi, :id_penulis, :id_media, 'draft')
                  RETURNING id_berita";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':judul'      => $data['judul'],
            ':slug'       => $data['slug'],
            ':isi'        => $data['isi'],
            ':id_penulis' => $data['id_penulis'],
            ':id_media'   => $data['thumbnail_media_id'] ?? null
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id_berita'] ?? null;
    }

    /**
     * Update berita (untuk Reporter edit draft/revisi)
     */
    public function update($id_berita, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET judul = :judul,
                      slug = :slug,
                      isi = :isi,
                      thumbnail_media_id = :id_media
                  WHERE id_berita = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':judul'    => $data['judul'],
            ':slug'     => $data['slug'],
            ':isi'      => $data['isi'],
            ':id_media' => $data['thumbnail_media_id'] ?? null,
            ':id'       => $id_berita
        ]);
    }

    /**
     * Delete berita (untuk Admin)
     */
    public function delete($id_berita) {
        $query = "DELETE FROM " . $this->table . " WHERE id_berita = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id_berita]);
    }

    // ============================================================
    // FRONTEND - FITUR PUBLIK (sesuai requirements)
    // ============================================================
    
    /**
     * Berita terbaru yang sudah dipublikasikan
     */
    public function getPublished($limit = 10, $offset = 0) {
        $query = "SELECT b.id_berita, b.judul, b.slug, b.isi, 
                         b.waktu_publish, b.jumlah_view,
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'dipublikasikan'
                  ORDER BY b.waktu_publish DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Berita terpopuler (berdasarkan jumlah pembaca)
     */
    public function getTerpopuler($limit = 5) {
        $query = "SELECT b.id_berita, b.judul, b.slug, 
                         b.waktu_publish, b.jumlah_view,
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'dipublikasikan'
                  ORDER BY b.jumlah_view DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Berita terkait (berdasarkan kategori/tag)
     */
    public function getTerkait($id_berita, $limit = 5) {
        $query = "SELECT DISTINCT b.id_berita, b.judul, b.slug, 
                         b.waktu_publish, b.jumlah_view,
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  INNER JOIN berita_kategori bk ON b.id_berita = bk.id_berita
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'dipublikasikan'
                    AND b.id_berita != :id_berita
                    AND bk.id_kategori IN (
                        SELECT id_kategori FROM berita_kategori 
                        WHERE id_berita = :id_berita
                    )
                  ORDER BY b.waktu_publish DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_berita', $id_berita, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pencarian berita
     */
    public function search($keyword, $limit = 20) {
        $searchTerm = '%' . $keyword . '%';
        
        $query = "SELECT b.id_berita, b.judul, b.slug, b.isi,
                         b.waktu_publish, b.jumlah_view,
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'dipublikasikan'
                    AND (b.judul ILIKE :keyword OR b.isi ILIKE :keyword)
                  ORDER BY b.waktu_publish DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':keyword', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filter berita berdasarkan kategori
     */
    public function filterByKategori($id_kategori, $limit = 10, $offset = 0) {
        $query = "SELECT b.id_berita, b.judul, b.slug, b.isi,
                         b.waktu_publish, b.jumlah_view,
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  INNER JOIN berita_kategori bk ON b.id_berita = bk.id_berita
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'dipublikasikan'
                    AND bk.id_kategori = :id_kategori
                  ORDER BY b.waktu_publish DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_kategori', $id_kategori, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filter berita berdasarkan tanggal
     */
    public function filterByTanggal($start_date, $end_date, $limit = 10) {
        $query = "SELECT b.id_berita, b.judul, b.slug, b.isi,
                         b.waktu_publish, b.jumlah_view,
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'dipublikasikan'
                    AND b.waktu_publish BETWEEN :start_date AND :end_date
                  ORDER BY b.waktu_publish DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Increment view counter (tracking jumlah pembaca)
     */
    public function incrementView($id_berita) {
        $query = "UPDATE " . $this->table . " 
                  SET jumlah_view = jumlah_view + 1 
                  WHERE id_berita = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id_berita]);
    }

    // ============================================================
    // WORKFLOW - REPORTER
    // ============================================================
    
    /**
     * Get berita milik reporter
     * Untuk: Reporter melihat status berita (draft, diajukan, disetujui, ditolak)
     */
    public function getMyDrafts($id_penulis, $status = null) {
        $query = "SELECT b.*, m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.id_penulis = :id_penulis";
        
        if ($status) {
            $query .= " AND b.status = :status";
        }
        
        $query .= " ORDER BY b.waktu_diubah DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_penulis', $id_penulis, PDO::PARAM_INT);
        
        if ($status) {
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengirim berita ke editor (Draft → Diajukan)
     */
    public function ajukanKeEditor($id_berita) {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'diajukan', 
                      waktu_diajukan = NOW() 
                  WHERE id_berita = :id 
                    AND status IN ('draft', 'revisi')";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id_berita]);
    }

    // ============================================================
    // WORKFLOW - EDITOR
    // ============================================================
    
    /**
     * Melihat daftar berita dengan status Diajukan
     */
    public function getPendingReview() {
        $query = "SELECT b.*, 
                         u.nama as nama_penulis,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  WHERE b.status = 'diajukan'
                  ORDER BY b.waktu_diajukan ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menyetujui berita (Diajukan → Disetujui)
     */
    public function setujui($id_berita, $id_editor) {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'disetujui',
                      id_editor_penyetuju = :id_editor,
                      waktu_disetujui = NOW()
                  WHERE id_berita = :id 
                    AND status = 'diajukan'";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id'        => $id_berita,
            ':id_editor' => $id_editor
        ]);
    }

    /**
     * Menolak berita untuk revisi (Diajukan → Revisi)
     */
    public function beriRevisi($id_berita, $catatan) {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'revisi', 
                      catatan_revisi = :catatan 
                  WHERE id_berita = :id 
                    AND status = 'diajukan'";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id'      => $id_berita, 
            ':catatan' => $catatan
        ]);
    }

    /**
     * Publish berita ke frontend (Disetujui → Dipublikasikan)
     */
    public function publish($id_berita) {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'dipublikasikan',
                      waktu_publish = NOW()
                  WHERE id_berita = :id 
                    AND status = 'disetujui'";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id_berita]);
    }

    // ============================================================
    // ADMIN - MANAGEMENT
    // ============================================================
    
    /**
     * Get semua berita (untuk admin)
     */
    public function getAll($limit = 50, $offset = 0) {
        $query = "SELECT b.*, 
                         u.nama as nama_penulis,
                         e.nama as nama_editor,
                         m.path_file as thumbnail_url
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.id_penulis = u.id_user
                  LEFT JOIN users e ON b.id_editor_penyetuju = e.id_user
                  LEFT JOIN media m ON b.thumbnail_media_id = m.id_media
                  ORDER BY b.waktu_diubah DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count berita by status (untuk dashboard)
     */
    public function countByStatus($status) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = :status";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Count total berita (untuk dashboard)
     */
    public function countTotal() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // ============================================================
    // RELASI - KATEGORI & TAG (sesuai requirements)
    // ============================================================
    
    /**
     * Tambah kategori ke berita (boleh lebih dari satu)
     */
    public function addKategori($id_berita, $id_kategori) {
        $query = "INSERT INTO berita_kategori (id_berita, id_kategori) 
                  VALUES (:id_berita, :id_kategori)
                  ON CONFLICT (id_berita, id_kategori) DO NOTHING";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id_berita'   => $id_berita,
            ':id_kategori' => $id_kategori
        ]);
    }

    /**
     * Hapus semua kategori dari berita
     */
    public function removeAllKategori($id_berita) {
        $query = "DELETE FROM berita_kategori WHERE id_berita = :id_berita";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id_berita' => $id_berita]);
    }

    /**
     * Get kategori dari berita
     */
    public function getKategoriByBerita($id_berita) {
        $query = "SELECT k.* 
                  FROM kategori k
                  INNER JOIN berita_kategori bk ON k.id_kategori = bk.id_kategori
                  WHERE bk.id_berita = :id_berita";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_berita', $id_berita, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tambah tag/kata kunci ke berita
     */
    public function addTag($id_berita, $id_kata_kunci) {
        $query = "INSERT INTO berita_kata_kunci (id_berita, id_kata_kunci) 
                  VALUES (:id_berita, :id_kata_kunci)
                  ON CONFLICT (id_berita, id_kata_kunci) DO NOTHING";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id_berita'     => $id_berita,
            ':id_kata_kunci' => $id_kata_kunci
        ]);
    }

    /**
     * Hapus semua tag dari berita
     */
    public function removeAllTags($id_berita) {
        $query = "DELETE FROM berita_kata_kunci WHERE id_berita = :id_berita";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id_berita' => $id_berita]);
    }

    /**
     * Get tags/kata kunci dari berita
     */
    public function getTagsByBerita($id_berita) {
        $query = "SELECT kk.* 
                  FROM kata_kunci kk
                  INNER JOIN berita_kata_kunci bkk ON kk.id_kata_kunci = bkk.id_kata_kunci
                  WHERE bkk.id_berita = :id_berita";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_berita', $id_berita, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // HELPER
    // ============================================================
    
    /**
     * Cek apakah berita milik reporter tertentu
     */
    public function isOwnedBy($id_berita, $id_penulis) {
        $query = "SELECT COUNT(*) as total 
                  FROM " . $this->table . " 
                  WHERE id_berita = :id_berita AND id_penulis = :id_penulis";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':id_berita'  => $id_berita,
            ':id_penulis' => $id_penulis
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['total'] ?? 0) > 0;
    }

    /**
     * Generate slug otomatis dari judul
     */
    public function generateSlug($judul) {
        $slug = strtolower($judul);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Cek keunikan
        $original_slug = $slug;
        $counter = 1;
        
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE slug = :slug";
        $stmt = $this->db->prepare($query);
        
        while (true) {
            $stmt->execute([':slug' => $slug]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (($result['total'] ?? 0) == 0) {
                break;
            }
            
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Cek apakah berita bisa diedit oleh user tertentu
     * Reporter hanya bisa edit berita miliknya dengan status draft/revisi
     */
    public function canEdit($id_berita, $id_user, $role) {
        // Admin dan Editor bisa edit semua
        if (in_array($role, ['admin', 'editor'])) {
            return true;
        }
        
        // Reporter hanya bisa edit miliknya dengan status draft/revisi
        if ($role === 'reporter') {
            $query = "SELECT COUNT(*) as total 
                      FROM " . $this->table . " 
                      WHERE id_berita = :id_berita 
                        AND id_penulis = :id_user 
                        AND status IN ('draft', 'revisi')";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':id_berita' => $id_berita,
                ':id_user'   => $id_user
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['total'] ?? 0) > 0;
        }
        
        return false;
    }
}