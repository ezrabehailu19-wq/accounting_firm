<?php
class View extends Model {

    public function login($user, $pass) {
    $data = $this->getUser($user);
    if ($data->num_rows === 0) {
        return "Incorrect username or password";
    }
    $row = $data->fetch_assoc();
    if (password_verify($pass, $row['password'])) {
        session_start();
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user;
        $_SESSION['role'] = $row['role']; // save role to session

        // Role based redirect
        if ($row['role'] === 'admin') {
            header('Location: admin.php');
        } else {
            header('Location: user_dashboard.php');
        }
        exit();
    } else {
        return "Incorrect username or password";
    }
}
}
?>