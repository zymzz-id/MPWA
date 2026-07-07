@echo off
setlocal enabledelayedexpansion

cls
echo.
echo ====================================
echo    MPWA Auto Startup Script (Windows)
echo ====================================
echo.

set PORT_LARAVEL=8000
set PORT_NODE=3000
set LOG_DIR=logs

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

if not exist .env (
    echo [!] .env file not found. Creating from .env.example...
    if exist .env.example (
        copy .env.example .env
        echo [OK] .env created from .env.example
    ) else (
        echo [ERROR] .env.example not found.
        pause
        exit /b 1
    )
)

php -v >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP is not installed or not in PATH.
    pause
    exit /b 1
) else (
    echo [OK] PHP found
)

node -v >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js is not installed or not in PATH.
    pause
    exit /b 1
) else (
    echo [OK] Node.js found
)

composer -v >nul 2>&1
if errorlevel 1 (
    echo [!] Composer not found. Attempting to use local composer.phar...
    if not exist composer.phar (
        echo [i] Downloading Composer...
        powershell -Command "(New-Object Net.WebClient).DownloadFile('https://getcomposer.org/installer', 'composer-setup.php'); php composer-setup.php; del composer-setup.php"
    )
    set COMPOSER=php composer.phar
) else (
    set COMPOSER=composer
)
echo [OK] Composer ready

echo.
echo [i] Installing dependencies...
echo.

if not exist vendor (
    echo [i] Installing PHP dependencies...
    call %COMPOSER% install --no-interaction
    echo [OK] PHP dependencies installed
) else (
    echo [OK] PHP dependencies already installed
)

if not exist node_modules (
    echo [i] Installing Node.js dependencies...
    call npm install
    echo [OK] Node.js dependencies installed
) else (
    echo [OK] Node.js dependencies already installed
)

echo.
echo [i] Setting up Laravel...
echo.

findstr /M "APP_KEY=" .env >nul 2>&1
if errorlevel 1 (
    echo [i] Generating Laravel application key...
    call php artisan key:generate
    echo [OK] Application key generated
) else (
    echo [OK] Application key already set
)

echo [i] Running database migrations...
call php artisan migrate --force
echo [OK] Database migrations completed

echo.
echo ====================================
echo    Starting Servers
echo ====================================
echo.

echo [i] Starting Node.js server on port %PORT_NODE%...
start "MPWA - Node.js Server" cmd /k node server.js
echo [OK] Node.js server started

echo [i] Starting Laravel server on port %PORT_LARAVEL%...
start "MPWA - Laravel Server" cmd /k php artisan serve --port=%PORT_LARAVEL%
echo [OK] Laravel server started

echo.
echo ====================================
echo    MPWA is Running
echo ====================================
echo.
echo [i] Web Dashboard: http://localhost:%PORT_LARAVEL%
echo [i] Node.js Server: http://localhost:%PORT_NODE%
echo [i] API Documentation: http://localhost:%PORT_LARAVEL%/api-docs
echo.
echo [i] Close these windows to stop the servers
echo.
pause
