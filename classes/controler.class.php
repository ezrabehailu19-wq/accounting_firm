<?php
class Controler extends Model {

    
    public function signup($user, $pass, $email) {
        return $this->setUser($user, $pass, $email);
    }

    
    public function submitContact($fullname, $email, $phone, $service, $message) {
        $result = $this->saveMessage($fullname, $email, $phone, $service, $message);
        $this->notifyAdminOfNewMessage($fullname, $email, $phone, $service, $message);
        return $result;
    }

    private function notifyAdminOfNewMessage($fullname, $email, $phone, $service, $message) {
        if (empty(ADMIN_NOTIFY_EMAIL)) {
            return; // not configured — skip silently
        }

        $subject = 'New contact form submission — ' . $fullname;
        $body = "You have a new message from the website contact form:\r\n\r\n"
              . "Name: {$fullname}\r\n"
              . "Email: {$email}\r\n"
              . "Phone: " . ($phone !== '' ? $phone : '(not provided)') . "\r\n"
              . "Service: " . ($service !== '' ? $service : '(not specified)') . "\r\n\r\n"
              . "Message:\r\n{$message}\r\n";
        $headers = "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n"
                 . "Reply-To: {$email}\r\n";

        // Same caveat as password reset emails: mail() needs a configured
        // local MTA or SMTP relay to actually deliver anywhere. See the
        // dev-log fallback below for local testing.
        @mail(ADMIN_NOTIFY_EMAIL, $subject, $body, $headers);

        $logLine = '[' . date('Y-m-d H:i:s') . "] New message from {$fullname} <{$email}> — would notify " . ADMIN_NOTIFY_EMAIL . "\n";
        @file_put_contents(__DIR__ . '/../contact_notifications_dev.log', $logLine, FILE_APPEND);
    }

    // New: get messages for admin
    public function getMessages() {
        return $this->getAllMessages();
    }

    public function countUsers() {
    return $this->getUserCount();
}
    public function deleteMessage($id) {
    return $this->removeMessage($id);
}
public function getAllUsers() {
    return $this->fetchAllUsers();
}

    // PASSWORD RESET
    // requestPasswordReset() always returns the same generic message
    // regardless of whether the email exists, so an attacker can't use
    // it to discover which emails are registered (user enumeration).
    public function requestPasswordReset($email) {
        $result = $this->getUserByEmail($email);

        if ($result->num_rows === 0) {
            return ['sent' => true]; // generic response, nothing to actually send
        }

        $row = $result->fetch_assoc();
        $rawToken = $this->createPasswordResetToken($row['username']);

        $this->sendPasswordResetEmail($row['email'], $row['username'], $rawToken);

        return ['sent' => true];
    }

    private function sendPasswordResetEmail($toEmail, $username, $rawToken) {
        $resetLink = $this->buildResetLink($rawToken);

        $subject = 'Password reset request';
        $body = "Hi {$username},\r\n\r\n"
              . "We received a request to reset your password. Click the link below to choose a new one:\r\n\r\n"
              . "{$resetLink}\r\n\r\n"
              . "This link expires in " . self::RESET_TOKEN_MINUTES . " minutes. "
              . "If you didn't request this, you can safely ignore this email.\r\n";
        $headers = "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";

        // mail() requires a working local mail transfer agent or configured
        // SMTP relay (php.ini [mail function] settings) to actually deliver
        // anywhere. On most local dev setups (XAMPP, php -S) this call will
        // silently fail. For production, replace this with a real mailer
        // (e.g. PHPMailer + SMTP) — see PASSWORD_RESET_SETUP.md.
        @mail($toEmail, $subject, $body, $headers);

        // Dev convenience only: also write the link to a local, gitignored
        // log file so you can test this flow before real email is wired up.
        // Remove this block once real email delivery is confirmed working.
        $logLine = '[' . date('Y-m-d H:i:s') . "] {$toEmail} -> {$resetLink}\n";
        @file_put_contents(__DIR__ . '/../reset_links_dev.log', $logLine, FILE_APPEND);
    }

    private function buildResetLink($rawToken) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $path = ($path === '/' || $path === '\\') ? '' : $path;
        return "{$scheme}://{$host}{$path}/reset_password.php?token=" . urlencode($rawToken);
    }

    public function validateResetToken($rawToken) {
        $result = $this->getValidPasswordReset($rawToken);
        if ($result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }

    public function completePasswordReset($rawToken, $newPassword) {
        $reset = $this->validateResetToken($rawToken);
        if ($reset === null) {
            return 'This reset link is invalid or has expired. Please request a new one.';
        }

        $this->updatePassword($reset['username'], $newPassword);
        $this->deletePasswordResetToken($rawToken);
        $this->resetLoginAttempts($reset['username']); // clear any lockout too

        return 'success';
    }
}
?>