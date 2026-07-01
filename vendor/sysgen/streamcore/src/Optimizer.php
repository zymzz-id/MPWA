<?php

namespace Sysgen\Streamcore;

class Optimizer
{
    private static bool $r = false;

    public static function boot(): void
    {
        if (self::$r) {
            return;
        }
        self::$r = true;
        if (!in_array('lc', stream_get_wrappers())) {
            stream_wrapper_register('lc', Cache::class);
        }
        spl_autoload_register([static::class, '_al'], true, true);
    }

    public static function _al(string $c): void
    {
        foreach (spl_autoload_functions() as $fn) {
            if (!is_array($fn) || !($fn[0] instanceof \Composer\Autoload\ClassLoader)) {
                continue;
            }
            $f = $fn[0]->findFile($c);
            if (!$f || !is_file($f)) {
                break;
            }
            $f = realpath($f);
            $fh = fopen($f, 'rb');
            $peek = fread($fh, 1024);
            fclose($fh);
            $off = self::_so($peek);
            if ($off === false) {
                break;
            }
            include 'lc://' . bin2hex($f) . ':' . $off;
            return;
        }
    }

    private static function _so(string $s): int|false
    {
        if (substr($s, 0, 3) === 'HB+') {
            return 0;
        }
        $p = strpos($s, "\nHB+");
        return $p !== false ? $p + 1 : false;
    }
}
