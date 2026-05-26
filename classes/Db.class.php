<?php
class Db {
    private $host = 'localhost';
    private $user = 'root';
    private $password = '';
    private $db = 'accounting_firm';

    protected function conn() {
        $conn = new mysqli($this->host, $this->user, $this->password, $this->db);
        if ($conn->connect_errno) {
            return false;
        }
        return $conn;
    }
}
?>