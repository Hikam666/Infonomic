<?php
class Kategori {
    private $db;
    private $table = 'kategori';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function getAll($only_active = true) {
        $query = "SELECT * FROM " . $this->table;
        if ($only_active) $query .= " WHERE aktif = TRUE";
        $query .= " ORDER BY nama_kategori ASC";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($nama, $slug) {
        $query = "INSERT INTO " . $this->table . " (nama_kategori, slug_kategori) VALUES (:nama, :slug)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':nama' => $nama, ':slug' => $slug]);
    }
}