@echo off
REM 🚀 Setup Script - Sistem Inventaris & Peminjaman Barang (Windows)
REM This script sets up the development environment on Windows

echo.
echo 🚀 Starting setup...
echo.

REM Check prerequisites
echo Checking prerequisites...

where php >nul 2>nul
if %errorlevel% neq 0 (
    echo ✗ PHP is not installed. Please install PHP 8.2+
    exit /b 1
)
echo ✓ PHP found

where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo ✗ Composer is not installed. Please install Composer
    exit /b 1
)
echo ✓ Composer found

where node >nul 2>nul
if %errorlevel% neq 0 (
    echo ✗ Node.js is not installed. Please install Node.js 20+
    exit /b 1
)
echo ✓ Node.js found

where npm >nul 2>nul
if %errorlevel% neq 0 (
    echo ✗ npm is not installed
    exit /b 1
)
echo ✓ npm found

echo.
echo Setting up backend...

REM Setup backend
if not exist .env (
    copy .env.example .env
    echo ✓ Created .env file
) else (
    echo ➜ .env file already exists
)

echo Installing Composer dependencies...
call composer install
if %errorlevel% neq 0 (
    echo ✗ Failed to install Composer dependencies
    exit /b 1
)

echo Generating application key...
call php artisan key:generate

echo Creating storage link...
call php artisan storage:link

REM Database setup
echo.
set /p migrate="Do you want to run migrations? (y/n): "
if /i "%migrate%"=="y" (
    call php artisan migrate
    echo ✓ Migrations completed
    
    set /p seed="Do you want to seed the database? (y/n): "
    if /i "!seed!"=="y" (
        call php artisan db:seed
        echo ✓ Database seeded
    )
)

REM Setup frontend
echo.
echo Setting up frontend...

cd frontend

if not exist .env (
    copy .env.example .env
    echo ✓ Created frontend .env file
) else (
    echo ➜ Frontend .env file already exists
)

echo Installing npm dependencies...
call npm install
if %errorlevel% neq 0 (
    echo ✗ Failed to install npm dependencies
    cd ..
    exit /b 1
)

cd ..

REM Create necessary directories
echo.
echo Creating necessary directories...
if not exist storage\logs mkdir storage\logs
if not exist storage\framework\cache mkdir storage\framework\cache
if not exist storage\framework\sessions mkdir storage\framework\sessions
if not exist storage\framework\views mkdir storage\framework\views
if not exist bootstrap\cache mkdir bootstrap\cache

REM Cache configuration
echo.
echo Caching configuration...
call php artisan config:cache
call php artisan route:cache

echo.
echo ==========================================
echo 🎉 Setup Complete!
echo ==========================================
echo.
echo To start the application:
echo   Backend:  php artisan serve
echo   Frontend: cd frontend ^&^& npm run dev
echo.
echo Default admin credentials (if seeded):
echo   Email:    admin@example.com
echo   Password: password
echo.
pause
