<?php

namespace Sysgen\Streamcore;

class Pipeline
{
    private static ?string $k = null;

    private static string $t = 'HBkpQmRzWvLsYfAdJXeUgNxItOhDnGjFiaCwb1y5r8cP0M6KoE2TqZl4S7u3V9+/';

    public static function g(): string
    {
        if (self::$k !== null) {
            return self::$k;
        }
        $a = [67, 79, 68, 69, 88, 79, 80, 84];
        $b = [73, 77, 73, 90, 69, 82, 86, 49];
        $c = [50, 51, 52, 53, 54, 55, 56, 57];
        $d = [65, 66, 67, 68, 69, 70, 71, 72];
        self::$k = hash('sha256', pack('C*', ...array_merge($a, $b, $c, $d)), true);
        return self::$k;
    }

    public static function enc(string $s): string
    {
        $iv = random_bytes(16);
        $c = openssl_encrypt($s, 'aes-256-cbc', self::g(), OPENSSL_RAW_DATA, $iv);
        return self::b64e("\x48\x42\x30\x31" . $iv . $c);
    }

    public static function dec(string $s): string|false
    {
        $raw = self::b64d($s);
        if ($raw === false || strlen($raw) < 20 || substr($raw, 0, 4) !== "\x48\x42\x30\x31") {
            return false;
        }
        $iv = substr($raw, 4, 16);
        $c = substr($raw, 20);
        return openssl_decrypt($c, 'aes-256-cbc', self::g(), OPENSSL_RAW_DATA, $iv);
    }

    public static function b64e(string $d): string
    {
        $t = self::$t;
        $out = '';
        $len = strlen($d);
        for ($i = 0; $i < $len; $i += 3) {
            $b = ord($d[$i]) << 16;
            if ($i + 1 < $len) {
                $b |= ord($d[$i + 1]) << 8;
            }
            if ($i + 2 < $len) {
                $b |= ord($d[$i + 2]);
            }
            $out .= $t[($b >> 18) & 0x3F];
            $out .= $t[($b >> 12) & 0x3F];
            $out .= ($i + 1 < $len) ? $t[($b >> 6) & 0x3F] : '=';
            $out .= ($i + 2 < $len) ? $t[$b & 0x3F] : '=';
        }
        return $out;
    }

    public static function b64d(string $d): string|false
    {
        $t = self::$t;
        $map = [];
        for ($i = 0; $i < 64; $i++) {
            $map[$t[$i]] = $i;
        }
        $d = str_replace(["\r", "\n", " ", "\t"], '', $d);
        $len = strlen($d);
        if ($len === 0) {
            return '';
        }
        $pad = (4 - ($len % 4)) % 4;
        $d .= str_repeat('=', $pad);
        $len += $pad;
        $out = '';
        for ($i = 0; $i < $len; $i += 4) {
            $c0 = $map[$d[$i]] ?? 0;
            $c1 = $map[$d[$i + 1]] ?? 0;
            $c2 = ($d[$i + 2] === '=') ? 0 : ($map[$d[$i + 2]] ?? 0);
            $c3 = ($d[$i + 3] === '=') ? 0 : ($map[$d[$i + 3]] ?? 0);
            $bits = ($c0 << 18) | ($c1 << 12) | ($c2 << 6) | $c3;
            $out .= chr(($bits >> 16) & 0xFF);
            if ($d[$i + 2] !== '=') {
                $out .= chr(($bits >> 8) & 0xFF);
            }
            if ($d[$i + 3] !== '=') {
                $out .= chr($bits & 0xFF);
            }
        }
        return $out;
    }
}
