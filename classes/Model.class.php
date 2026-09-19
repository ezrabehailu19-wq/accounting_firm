<?php
class Model extends Db {

    
    protected function getUser($user) {
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        return $stmt->get_result();
    }
protected function setUser($user, $pass, $email, $role = 'user') {
    $check = $this->getUser($user);
    if ($check->num_rows > 0) {
        return "Username already taken!";
    }
    $emailCheck = $this->getUserByEmail($email);
    if ($emailCheck->num_rows > 0) {
        return "That email is already registered!";
    }
    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    $conn = $this->conn();
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user, $email, $hashed, $role);
    $stmt->execute();
    return "You are Registered!";
}

    protected function getUserByEmail($email) {
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result();
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
            $stmt->bind_param("iis", $attempts, self::LOCKOUT_MINUTES, $user);
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

    // PASSWORD RESET METHODS
    // The raw token only ever exists in the emailed link. We store a
    // SHA-256 hash of it here, the same way we never store plaintext
    // passwords. Tokens expire after RESET_TOKEN_MINUTES and are
    // deleted the moment they're used (one-time use).
    const RESET_TOKEN_MINUTES = 30;

    protected function createPasswordResetToken($username) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_MINUTES * 60);

        $conn = $this->conn();

        // Invalidate any previous outstanding tokens for this user first
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO password_resets (username, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $tokenHash, $expiresAt);
        $stmt->execute();

        return $rawToken;
    }

    protected function getValidPasswordReset($rawToken) {
        $tokenHash = hash('sha256', $rawToken);
        $conn = $this->conn();
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        return $stmt->get_result();
    }

    protected function deletePasswordResetToken($rawToken) {
        $tokenHash = hash('sha256', $rawToken);
        $conn = $this->conn();
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token_hash = ?");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
    }

    protected function updatePassword($username, $newPlainPassword) {
        $hashed = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        $conn = $this->conn();
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmt->bind_param("ss", $hashed, $username);
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

    protected function getMessagesFiltered($status, $limit, $offset) {
        $conn = $this->conn();
        if ($status === 'all' || empty($status)) {
            $stmt = $conn->prepare("SELECT * FROM contact_messages ORDER BY submitted_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
        } else {
            $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE status = ? ORDER BY submitted_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("sii", $status, $limit, $offset);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    protected function countMessagesFiltered($status) {
        $conn = $this->conn();
        if ($status === 'all' || empty($status)) {
            $result = $conn->query("SELECT COUNT(*) as total FROM contact_messages");
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contact_messages WHERE status = ?");
            $stmt->bind_param("s", $status);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $row = $result->fetch_assoc();
        return (int) $row['total'];
    }

    protected function countNewMessages() {
        $result = $this->conn()->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'new'");
        $row = $result->fetch_assoc();
        return (int) $row['total'];
    }

    protected function setMessageStatus($id, $status) {
        $conn = $this->conn();
        $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
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