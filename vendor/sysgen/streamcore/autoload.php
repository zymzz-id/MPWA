<?php

spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'Sysgen\\Streamcore\\', 18) !== 0) {
        return;
    }
    $name = substr($class, 18);
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/fn.php';
\Sysgen\Streamcore\Optimizer::boot();
