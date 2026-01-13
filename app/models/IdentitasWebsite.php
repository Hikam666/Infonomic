<?php

class IdentitasWebsite {
    private $db;
    private $table = 'identitas_website';
    private $singleton_id = 1;

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    // ============================================================
    // CORE METHODS (sesuai requirements)
    // ============================================================
    
    /**
     * Get identitas website (singleton - hanya 1 row)
     * Digunakan untuk: tampil di frontend (header, footer, meta tags)
     */
    public function get() {
        $query = "SELECT * FROM " . $this->table . " WHERE id_identitas = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $this->singleton_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update identitas website (untuk Admin)
     * Digunakan untuk: admin mengelola struktur website
     */
    public function update($data) {
        $query = "UPDATE " . $this->table . " 
                  SET nama_website = :nama, 
                      logo_path = :logo, 
                      warna_utama = :warna_u, 
                      warna_latar = :warna_l, 
                      deskripsi_singkat = :deskripsi
                  WHERE id_identitas = :id";
        
        $stmt = $this->db->prepare($query);
        
        return $stmt->execute([
            ':nama'      => $data['nama_website'],
            ':logo'      => $data['logo_path'],
            ':warna_u'   => $data['warna_utama'],
            ':warna_l'   => $data['warna_latar'],
            ':deskripsi' => $data['deskripsi_singkat'] ?? null,
            ':id'        => $this->singleton_id
        ]);
    }
}