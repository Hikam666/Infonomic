<?php
class Komentar {
    private $db;
    private $table = 'komentar';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function create($id_berita, $data) {
        $query = "INSERT INTO " . $this->table . " 
                  (id_berita, nama_pengirim, email_pengirim, isi_komentar, ip_pengirim, user_agent, status) 
                  VALUES (:id_b, :nama, :email, :isi, :ip, :ua, 'pending')";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id_b'   => $id_berita,
            ':nama'   => $data['nama'],
            ':email'  => $data['email'],
            ':isi'    => $data['isi'],
            ':ip'     => $_SERVER['REMOTE_ADDR'],
            ':ua'     => $_SERVER['HTTP_USER_AGENT']
        ]);
    }

    public function updateStatus($id_komentar, $status) {
        // Status: 'tampil', 'pending', 'spam'
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id_komentar = :id";
        return $this->db->prepare($query)->execute([':status' => $status, ':id' => $id_komentar]);
    }
}