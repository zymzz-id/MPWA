# 📖 Dokumentasi Script MPWA

Berikut adalah panduan lengkap untuk menjalankan dan mengelola MPWA menggunakan berbagai script bantuan yang telah disediakan untuk Linux/macOS, Windows, dan Docker.

---

## 🚀 Quick Reference

**Start MPWA**
- Linux/macOS
```bash 
./scripts/start.sh
```
- Windows
```bash
./scripts\start.bat
```
- Docker
```bash
./scripts/start-docker.sh
```

## 🐧 start.sh (Linux/macOS)

**Fitur:**
* ✅ Auto-check PHP, Node.js, Composer
* ✅ Auto-create .env dari .env.example
* ✅ Auto-install dependencies (Composer & npm)
* ✅ Auto-generate Laravel application key
* ✅ Auto-run database migrations
* ✅ Start Laravel server (port 8000)
* ✅ Start Node.js WhatsApp gateway (port 3000)
* ✅ Real-time logging
* ✅ Graceful shutdown (Ctrl+C)

**Penggunaan:**
chmod +x start.sh
./start.sh

**Output:**
==================================
   MPWA Auto Startup Script
==================================

[✓] PHP found: PHP 8.2.x
[✓] Node.js found: v18.x.x
[✓] Composer ready

[i] Installing dependencies...
[✓] PHP dependencies installed
[✓] Node.js dependencies installed

[i] Setting up Laravel...
[✓] Application key generated
[✓] Database migrations completed

==================================
   Starting Servers
==================================

[✓] Node.js server started (PID: 12345)
[✓] Laravel server started (PID: 12346)

==================================
   MPWA is Running
==================================

[i] Web Dashboard: http://localhost:8000
[i] Node.js Server: http://localhost:3000
[i] API Documentation: http://localhost:8000/api-docs

[i] Press Ctrl+C to stop both servers

---

## 🪟 start.bat (Windows)

**Fitur:**
* ✅ Auto-check PHP, Node.js, Composer
* ✅ Auto-create .env dari .env.example
* ✅ Auto-install dependencies
* ✅ Auto-generate Laravel key
* ✅ Auto-run migrations
* ✅ Buka 2 terminal terpisah (Laravel & Node.js)
* ✅ Auto-download Composer jika belum ada

**Penggunaan:**
start.bat

*Keterangan: Script akan membuka 2 window command prompt terpisah. Window 1 untuk Node.js WhatsApp Gateway dan Window 2 untuk Laravel Development Server. Tutup (close) windows untuk menghentikan server.*

---

## 🐳 start-docker.sh (Docker)

**Fitur:**
* ✅ Auto-check Docker & Docker Compose
* ✅ Menu interaktif 8 pilihan
* ✅ Start/stop/restart containers
* ✅ View logs real-time
* ✅ Run migrations di container
* ✅ Akses shell container
* ✅ Full reset dengan volume removal

**Penggunaan:**
chmod +x start-docker.sh
./start-docker.sh

**Menu Options:**
==================================
   Docker Options
==================================

1) Start containers (docker-compose up -d)
2) Stop containers (docker-compose down)
3) View logs (docker-compose logs -f)
4) Restart containers (docker-compose restart)
5) Run migrations (docker-compose exec mpwa php artisan migrate --force)
6) Open shell (docker-compose exec mpwa bash)
7) Full reset (remove volumes and rebuild)
0) Exit

---

## 🌐 Access URLs

Setelah script berjalan, akses aplikasi di tautan berikut:

| Service | URL |
|---------|-----|
| **Web Dashboard** | http://localhost:8000 |
| **Login** | http://localhost:8000/login |
| **Register** | http://localhost:8000/register |
| **API Documentation** | http://localhost:8000/api-docs |
| **Node.js API** | http://localhost:3000 |

---

## 📁 Log Files

Semua log disimpan di folder logs/:

logs/
├── laravel-server.log      # Laravel development server logs
├── laravel-server.pid      # Laravel process ID
├── node-server.log         # Node.js WhatsApp gateway logs
└── node-server.pid         # Node.js process ID

**Cara Melihat Logs:**

**Linux/macOS:**
tail -f logs/laravel-server.log
tail -f logs/node-server.log

**Windows (PowerShell):**
Get-Content logs\laravel-server.log -Tail 20 -Wait
Get-Content logs\node-server.log -Tail 20 -Wait

---

## 🛑 Menghentikan Servers

**Linux/macOS (start.sh)**
# Press Ctrl+C di terminal dimana script berjalan
# atau gunakan:
kill $(cat logs/laravel-server.pid)
kill $(cat logs/node-server.pid)

**Windows (start.bat)**
# Close terminal windows
# atau gunakan PowerShell:
taskkill /PID <process_id> /F

**Docker (start-docker.sh)**
./start-docker.sh
# Pilih option 2 (Stop containers)
# atau:
docker-compose down

---

## 📋 Prerequisites

**Untuk start.sh dan start.bat:**
* PHP 8.2+ dengan extensions: pdo, pdo_mysql
* Node.js 18+ dan npm
* MySQL 8.0+ atau compatible database
* Composer (PHP package manager)
* Git (version control)

**Untuk start-docker.sh:**
* Docker (latest version)
* Docker Compose (v1.29+)
* Git (version control)

---

## ✅ Checklist Setup

Sebelum menjalankan script, pastikan seluruh kebutuhan telah terpenuhi:

1. **PHP 8.2+ terinstall:**
   php -v

2. **Node.js 18+ terinstall:**
   node -v
   npm -v

3. **MySQL running dan accessible:**
   mysql -u root -p

4. **Composer terinstall (atau akan di-download secara otomatis):**
   composer -v

5. **Git terinstall:**
   git --version

6. **Repository sudah di-clone:**
   git clone https://github.com/zymzz-id/MPWA.git
   cd MPWA

7. **Posisi direktori sudah di root MPWA:**
   pwd  # atau cd untuk verify

8. **Port 8000 dan 3000 tersedia:**
   # Linux/macOS
   lsof -i :8000
   lsof -i :3000

   # Windows (PowerShell)
   netstat -ano | findstr :8000
   netstat -ano | findstr :3000

---

## 🔧 Troubleshooting

* **Error: "Permission denied" (Linux/macOS)**
  chmod +x scripts/start.sh
  chmod +x scripts/start-docker.sh

* **Error: "PHP is not installed"**
  Pastikan PHP 8.2+ terinstall dan ada di PATH.
  Test: php -v
  Install dari: https://www.php.net/downloads

* **Error: "Node.js is not installed"**
  Pastikan Node.js 18+ terinstall dan ada di PATH.
  Test: node -v dan npm -v
  Install dari: https://nodejs.org/

* **Error: "Composer not found"**
  Install dari: https://getcomposer.org/download/
  *Catatan: Anda juga bisa membiarkan script untuk mengunduh composer.phar secara otomatis.*

* **Error: "Database connection failed"**
  Pastikan MySQL running: mysql -u root -p
  Cek DB credentials di file .env.
  Test connection: php artisan db:show

* **Error: "Port already in use"**
  Ubah konfigurasi PORT di script atau file .env.
  Linux/macOS: lsof -i :8000 untuk cek process.
  Windows: netstat -ano | findstr :8000.
  Kill process: kill -9 <PID> (Linux/macOS) atau taskkill /PID <PID> /F (Windows).

* **Error: "Docker daemon not running" (Docker)**
  Start Docker Desktop atau Docker service.
  Linux: sudo systemctl start docker
  macOS / Windows: Buka aplikasi Docker Desktop.

* **Error: ".env.example not found"**
  File harus ada di root direktori MPWA. Jika belum ada, lakukan copy kembali dari repository.

---

## 📚 Dokumentasi Lengkap

Untuk dokumentasi lebih lengkap mengenai proyek MPWA:
* Lihat README.md (Main Project README)
* Lihat docker-compose.yml (Docker Configuration)
* Lihat Dockerfile (Docker Image Definition)

---

## 🤝 Support

Jika Anda mengalami masalah saat menggunakan script:
1. Cek isi file log di folder logs/.
2. Baca pesan error dengan teliti.
3. Pastikan semua prerequisites telah terinstall dengan baik.
4. Periksa kembali konfigurasi di file .env Anda.
5. Buat issue di GitHub repository secara detail beserta log error yang dialami.

---

## 📜 License

CC BY-NC-ND 4.0 - Lihat LICENSE.md di direktori utama.

Selamat menggunakan MPWA! 🚀
