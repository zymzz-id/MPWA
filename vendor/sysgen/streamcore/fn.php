<?php

\Sysgen\Streamcore\Optimizer::boot();

if (!function_exists('__prce')) {
    function __prce(string $f, int $o): void
    {
        include 'lc://' . bin2hex($f) . ':' . $o;
    }
}
