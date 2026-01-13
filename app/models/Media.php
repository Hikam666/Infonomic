<?php
class Media {
    private $db;
    private $table = 'media';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function upload($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (tipe, judul_media, path_file, id_pengunggah) 
                  VALUES (:tipe, :judul, :path, :uid)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':tipe'  => $data['tipe'], // Enum: 'gambar', 'infografis', 'video'
            ':judul' => $data['judul'],
            ':path'  => $data['path'],
            ':uid'   => $data['id_user']
        ]);
        return $this->db->lastInsertId();
    }
}