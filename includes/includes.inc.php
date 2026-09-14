<?php
require_once __DIR__ . '/../config.php';

spl_autoload_register('loader');

function loader($class) {
    $path = "classes/";
    $extension = ".class.php";
    $file = $path . $class . $extension;
    if (!file_exists($file)) {
        return false;
    }
    include_once $path . $class . $extension;
}
?>