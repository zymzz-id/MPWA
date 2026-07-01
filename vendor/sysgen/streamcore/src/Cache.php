<?php

namespace Sysgen\Streamcore;

class Cache
{
    public $context;
    private string $buf = '';
    private int $pos = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $raw = substr($path, 5);
        $sep = strrpos($raw, ':');
        if ($sep === false) {
            return false;
        }
        $hex = substr($raw, 0, $sep);
        $off = (int) substr($raw, $sep + 1);
        $file = hex2bin($hex);
        if (!is_file($file)) {
            return false;
        }
        $fh = fopen($file, 'rb');
        if (!$fh) {
            return false;
        }
        fseek($fh, $off);
        $data = stream_get_contents($fh);
        fclose($fh);
        $data = ltrim($data);
        if (substr($data, 0, 3) === 'HB+') {
            $data = substr($data, 3);
        }
        $dec = Pipeline::dec($data);
        if ($dec === false) {
            return false;
        }
        $this->buf = $dec;
        $this->pos = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->buf, $this->pos, $count);
        $this->pos += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->pos >= strlen($this->buf);
    }

    public function stream_tell(): int
    {
        return $this->pos;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $len = strlen($this->buf);
        if ($whence === SEEK_SET) {
            $this->pos = $offset;
        } elseif ($whence === SEEK_CUR) {
            $this->pos += $offset;
        } elseif ($whence === SEEK_END) {
            $this->pos = $len + $offset;
        } else {
            return false;
        }
        $this->pos = max(0, min($this->pos, $len));
        return true;
    }

    public function stream_stat(): array
    {
        $len = strlen($this->buf);
        $t = time();
        return [
            'dev' => 0, 'ino' => 0, 'mode' => 0100444, 'nlink' => 1,
            'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => $len,
            'atime' => $t, 'mtime' => $t, 'ctime' => $t,
            'blksize' => -1, 'blocks' => -1,
        ];
    }

    public function url_stat(string $path, int $flags): array
    {
        return [];
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }
}
