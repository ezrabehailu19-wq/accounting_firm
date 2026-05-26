<?php
class Controler extends Model {

    
    public function signup($user, $pass) {
        return $this->setUser($user, $pass);
    }

    
    public function submitContact($fullname, $email, $phone, $service, $message) {
        return $this->saveMessage($fullname, $email, $phone, $service, $message);
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
}
?>