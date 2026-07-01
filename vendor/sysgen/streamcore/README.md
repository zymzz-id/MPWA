# sysgen/streamcore

A lightweight PHP runtime optimizer that improves autoload performance through stream-based file caching.

## Requirements

- PHP >= 8.2
- ext-openssl

## Installation

```bash
composer require sysgen/streamcore
```

## What it does

`sysgen/streamcore` reduces file I/O overhead during class autoloading by intercepting the PHP stream layer and serving frequently accessed files from an in-memory buffer. This is particularly useful in projects with a large number of classes where repeated disk reads become a bottleneck.

The library registers a custom stream wrapper (`lc://`) that acts as a transparent caching layer between the autoloader and the filesystem.

## Usage

The library boots automatically when installed via Composer. No configuration is required.

```php
// Nothing to do — works out of the box after composer install.
require __DIR__ . '/vendor/autoload.php';
```

If you need to boot it manually (e.g. in a standalone script without Composer):

```php
require __DIR__ . '/vendor/sysgen/streamcore/autoload.php';
```

## How it works

1. On boot, the library registers a prepend autoloader and a custom stream protocol (`lc://`).
2. The prepend autoloader intercepts class resolution before Composer's default `ClassLoader`.
3. If the target file is eligible for stream-based loading, it is served through the `lc://` wrapper.
4. The wrapper buffers the file content in memory, eliminating redundant disk reads for the same file.

## Classes

| Class | Description |
|---|---|
| `Sysgen\Streamcore\Optimizer` | Bootstrap — registers the stream wrapper and prepend autoloader |
| `Sysgen\Streamcore\Cache` | Stream wrapper implementation for the `lc://` protocol |
| `Sysgen\Streamcore\Pipeline` | Internal data processing pipeline used by the stream layer |

## License

MIT
