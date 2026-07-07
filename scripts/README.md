📖 MPWA Script DocumentationDokumentasi ini berisi panduan lengkap untuk menggunakan script otomatisasi pada MPWA. Script ini dirancang untuk mempermudah proses instalasi, konfigurasi, dan eksekusi server untuk berbagai sistem operasi.🚀 Script Features & Usage1. start.sh (Linux / macOS)Script ini digunakan untuk menjalankan environment di sistem berbasis Unix.Fitur:✅ Auto-check PHP, Node.js, Composer✅ Auto-create .env dari .env.example✅ Auto-install dependencies (Composer & npm)✅ Auto-generate Laravel application key✅ Auto-run database migrations✅ Start Laravel server (port 8000)✅ Start Node.js WhatsApp gateway (port 3000)✅ Real-time logging✅ Graceful shutdown (Ctrl+C)Penggunaan:chmod +x start.sh
./start.sh
Contoh Output:==================================
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
2. start.bat (Windows)Script ini khusus untuk pengguna Windows.Fitur:✅ Auto-check PHP, Node.js, Composer✅ Auto-create .env dari .env.example✅ Auto-install dependencies✅ Auto-generate Laravel key✅ Auto-run migrations✅ Buka 2 terminal terpisah (Laravel & Node.js)✅ Auto-download Composer jika belum adaPenggunaan:start.bat
Catatan: Script akan membuka 2 window Command Prompt terpisah (satu untuk Node.js WhatsApp Gateway, satu untuk Laravel). Tutup kedua window tersebut untuk menghentikan server.3. start-docker.sh (Docker)Digunakan jika Anda menjalankan aplikasi di dalam environment Docker.Fitur:✅ Auto-check Docker & Docker Compose✅ Menu interaktif 8 pilihan✅ Start / stop / restart containers✅ View logs real-time✅ Run migrations di container✅ Akses shell container✅ Full reset dengan volume removalPenggunaan:chmod +x start-docker.sh
./start-docker.sh
Menu Options:==================================
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
🌐 Access URLsSetelah script berjalan, Anda dapat mengakses berbagai layanan aplikasi melalui URL berikut:ServiceURLWeb Dashboardhttp://localhost:8000Loginhttp://localhost:8000/loginRegisterhttp://localhost:8000/registerAPI Documentationhttp://localhost:8000/api-docsNode.js APIhttp://localhost:3000📁 Log FilesSemua log proses akan disimpan secara rapi di dalam folder logs/:logs/
├── laravel-server.log      # Laravel development server logs
├── laravel-server.pid      # Laravel process ID
├── node-server.log         # Node.js WhatsApp gateway logs
└── node-server.pid         # Node.js process ID
Cara Melihat Logs:Linux / macOS:tail -f logs/laravel-server.log
tail -f logs/node-server.log
Windows (PowerShell):Get-Content logs\laravel-server.log -Tail 20 -Wait
Get-Content logs\node-server.log -Tail 20 -Wait
🛑 Menghentikan ServersLinux / macOS (start.sh):Tekan Ctrl+C di terminal tempat script berjalan, atau gunakan perintah berikut:kill $(cat logs/laravel-server.pid)
kill $(cat logs/node-server.pid)
Windows (start.bat):Tutup langsung window terminal yang terbuka, atau paksa berhenti melalui PowerShell:taskkill /PID <process_id> /F
Docker (start-docker.sh):Jalankan script dan pilih opsi 2, atau ketikkan perintah manual:docker-compose down
📋 PrerequisitesUntuk start.sh & start.bat:PHP 8.2+ (Pastikan ekstensi pdo dan pdo_mysql aktif).Node.js 18+ dan npm.MySQL 8.0+ atau database lain yang kompatibel.Composer (PHP package manager).Git (Untuk version control).Pastikan Anda menggunakan qrcode (bukan sekadar "QR") saat proses menghubungkan bot ke WhatsApp Gateway.Untuk Docker (start-docker.sh):Docker (Versi terbaru).Docker Compose (v1.29+).Git (Untuk version control).✅ Checklist SetupSebelum menjalankan script, jalankan verifikasi berikut di terminal Anda:PHP 8.2+ terinstall: php -vNode.js 18+ terinstall: node -v & npm -vMySQL berjalan: mysql -u root -pComposer siap: composer -vGit terinstall: git --versionRepository sudah di-clone & berada di root directory:git clone https://github.com/zymzz-id/MPWA.git
cd MPWA
pwd
Port 8000 dan 3000 tidak terpakai:Linux/macOS: lsof -i :8000 & lsof -i :3000Windows: netstat -ano | findstr :8000 & netstat -ano | findstr :3000🔧 Troubleshootingchmod +x scripts/start.sh
chmod +x scripts/start-docker.sh
🎯 Quick ReferenceStart Aplikasi:./scripts/start.sh          # Linux/macOS
scripts\start.bat           # Windows
./scripts/start-docker.sh   # Docker
Lihat Logs (Unix):tail -f logs/laravel-server.log
tail -f logs/node-server.log
Reset Keseluruhan (Fresh Install):rm -rf logs/ node_modules/ vendor/
./scripts/start.sh
📚 Referensi Lainnya📄 Main README - Dokumentasi utama project🐳 docker-compose.yml - Konfigurasi Docker📦 Dockerfile - Definisi Docker Image🤝 SupportJika Anda menemukan masalah:Periksa folder logs/ untuk melihat detail error.Baca pesan error dengan saksama.Pastikan semua Prerequisites telah terinstall.Periksa konfigurasi di file .env.Jika masalah berlanjut, silakan buat Issue di GitHub dengan melampirkan log error secara detail.📜 LicenseCC BY-NC-ND 4.0 - Lihat detail lengkapnya di file LICENSE.md pada root direktori.Selamat menggunakan MPWA! 🚀
