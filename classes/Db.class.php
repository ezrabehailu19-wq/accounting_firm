<?php
class Db {

    protected function conn() {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_errno) {
            return false;
        }
        return $conn;
    }
}
?>