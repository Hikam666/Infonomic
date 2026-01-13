<?php

class LogAktivitas {
    private $db;
    private $table = 'log_aktivitas';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function record($id_user, $aksi, $tipe, $id_objek = null, $keterangan = null) {
        $query = "INSERT INTO " . $this->table . " 
                  (id_user, aksi, objek_tipe, objek_id, keterangan) 
                  VALUES (:uid, :aksi, :tipe, :oid, :ket)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':uid'   => $id_user,
            ':aksi'  => $aksi,
            ':tipe'  => $tipe,
            ':oid'   => $id_objek,
            ':ket'   => $keterangan
        ]);
    }

    public function getAllLogs($limit = 50) {
        $query = "SELECT l.*, u.nama 
                  FROM " . $this->table . " l
                  LEFT JOIN users u ON l.id_user = u.id_user
                  ORDER BY l.dibuat_pada DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}