<?php
class User {
    private $db;
    private $table = 'users';

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function authenticate($email, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email AND aktif = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }

    public function register($nama, $email, $password, $role = 'reporter') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO " . $this->table . " (nama, email, password_hash, role) 
                  VALUES (:nama, :email, :pass, :role)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':nama'  => $nama,
            ':email' => $email,
            ':pass'  => $hash,
            ':role'  => $role
        ]);
    }

    public function getByRole($role) {
        $query = "SELECT id_user, nama, email FROM " . $this->table . " WHERE role = :role";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}