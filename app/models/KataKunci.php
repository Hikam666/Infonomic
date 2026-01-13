<?php

class KataKunci {
    private $db;
    private $table = 'kata_kunci';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    // ============================================================
    // CRUD DASAR
    // ============================================================
    
    /**
     * Get semua kata kunci
     */
    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY nama ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get kata kunci by ID
     */
    public function getById($id_kata_kunci) {
        $query = "SELECT * FROM " . $this->table . " WHERE id_kata_kunci = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id_kata_kunci, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create kata kunci baru
     */
    public function create($nama, $slug) {
        $query = "INSERT INTO " . $this->table . " (nama, slug) 
                  VALUES (:nama, :slug) 
                  RETURNING id_kata_kunci";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':nama' => $nama,
            ':slug' => $slug
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id_kata_kunci'] ?? null;
    }

    /**
     * Create jika belum ada (untuk saat reporter input tag)
     */
    public function createIfNotExist($nama, $slug) {
        $query = "INSERT INTO " . $this->table . " (nama, slug) 
                  VALUES (:nama, :slug) 
                  ON CONFLICT (nama) DO NOTHING
                  RETURNING id_kata_kunci";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':nama' => $nama,
            ':slug' => $slug
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Jika conflict (sudah ada), ambil ID yang existing
        if (!$result) {
            $query = "SELECT id_kata_kunci FROM " . $this->table . " WHERE nama = :nama";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':nama', $nama, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $result['id_kata_kunci'] ?? null;
    }

    /**
     * Update kata kunci (untuk admin)
     */
    public function update($id_kata_kunci, $nama, $slug) {
        $query = "UPDATE " . $this->table . " 
                  SET nama = :nama, slug = :slug 
                  WHERE id_kata_kunci = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':nama' => $nama,
            ':slug' => $slug,
            ':id'   => $id_kata_kunci
        ]);
    }

    /**
     * Delete kata kunci (untuk admin)
     */
    public function delete($id_kata_kunci) {
        $query = "DELETE FROM " . $this->table . " WHERE id_kata_kunci = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id_kata_kunci]);
    }

    // ============================================================
    // RELASI DENGAN BERITA (sesuai requirements)
    // ============================================================
    
    /**
     * Get kata kunci dari berita tertentu
     * Digunakan untuk: menampilkan tag di detail berita
     */
    public function getByBerita($id_berita) {
        $query = "SELECT k.* 
                  FROM " . $this->table . " k 
                  INNER JOIN berita_kata_kunci bkk ON k.id_kata_kunci = bkk.id_kata_kunci 
                  WHERE bkk.id_berita = :id
                  ORDER BY k.nama ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id_berita, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // HELPER
    // ============================================================
    
    /**
     * Generate slug dari nama
     */
    public function generateSlug($nama) {
        // Convert ke lowercase
        $slug = strtolower($nama);
        
        // Replace spasi dengan dash
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // Hapus karakter spesial
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Hapus multiple dash
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Trim dash di awal/akhir
        $slug = trim($slug, '-');
        
        return $slug;
    }

    /**
     * Cek apakah nama sudah ada
     */
    public function isExists($nama) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE nama = :nama";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nama', $nama, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['total'] ?? 0) > 0;
    }
}