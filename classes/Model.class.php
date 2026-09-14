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

    // LOGIN RATE-LIMITING METHODS
    // After MAX_ATTEMPTS failed logins for a username, that username is
    // locked out for LOCKOUT_MINUTES. The counter resets on a successful
    // login, or naturally once the lockout window passes.
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 15;

    protected function isLockedOut($user) {
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT locked_until FROM login_attempts WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return false;
        }
        $row = $result->fetch_assoc();
        if ($row['locked_until'] === null) {
            return false;
        }
        return strtotime($row['locked_until']) > time();
    }

    protected function getLockoutMinutesRemaining($user) {
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT locked_until FROM login_attempts WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return 0;
        }
        $row = $result->fetch_assoc();
        if ($row['locked_until'] === null) {
            return 0;
        }
        $remainingSeconds = strtotime($row['locked_until']) - time();
        return (int) max(0, ceil($remainingSeconds / 60));
    }

    protected function recordFailedLoginAttempt($user) {
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT attempts FROM login_attempts WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO login_attempts (username, attempts, last_attempt) VALUES (?, 1, NOW())");
            $stmt->bind_param("s", $user);
            $stmt->execute();
            return;
        }

        $row = $result->fetch_assoc();
        $attempts = $row['attempts'] + 1;

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $stmt = $conn->prepare(
                "UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE username = ?"
            );
           $lockoutMinutes = self::LOCKOUT_MINUTES;
$stmt->bind_param("iis", $attempts, $lockoutMinutes, $user);
        } else {
            $stmt = $conn->prepare(
                "UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE username = ?"
            );
            $stmt->bind_param("is", $attempts, $user);
        }
        $stmt->execute();
    }

    protected function resetLoginAttempts($user) {
        $conn = $this->conn();
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
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