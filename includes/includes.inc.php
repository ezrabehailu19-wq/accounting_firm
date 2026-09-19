<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/csrf.php';

spl_autoload_register('loader');

function loader($class) {
    $file = __DIR__ . '/../classes/' . $class . '.class.php';

    if (file_exists($file)) {
        require_once $file;
    }
}
?>