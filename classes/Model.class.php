<?php
class Model extends Db {

    
    protected function getUser($user) {
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        return $stmt->get_result();
    }
protected function setUser($user, $pass, $role = 'user') {
    $check = $this->getUser($user);
    if ($check->num_rows > 0) {
        return "Username already taken!";
    }
    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    $conn = $this->conn();
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $hashed, $role);
    $stmt->execute();
    return "You are Registered!";
}   

    // CONTACT MESSAGE METHODS 
    protected function saveMessage($fullname, $email, $phone, $service, $message) {
        $conn = $this->conn();
        $stmt = $conn->prepare("INSERT INTO contact_messages (fullname, email, phone, service, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $fullname, $email, $phone, $service, $message);
        $stmt->execute();
        return "Message saved!";
    }

    protected function getAllMessages() {
        $stmt = "SELECT * FROM contact_messages ORDER BY submitted_at DESC";
        return $this->conn()->query($stmt);
    }

    protected function getUserCount() {
        $result = $this->conn()->query("SELECT COUNT(*) as total FROM users");
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    protected function removeMessage($id) {
        $conn = $this->conn();
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    protected function fetchAllUsers() {
    $result = $this->conn()->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
    return $result;
}

}
?>