@echo off
setlocal enabledelayedexpansion

REM Script directory
cd /d "%~dp0"

echo === Vue URA Backend Setup ^& Start Script ===
echo.

REM Find PHP executable
set PHP_CMD=
if exist "%USERPROFILE%\.config\herd\bin\php.bat" (
    set PHP_CMD=%USERPROFILE%\.config\herd\bin\php.bat
    goto :php_found
)
if exist "C:\Program Files\Laravel Herd\bin\php.exe" (
    set PHP_CMD=C:\Program Files\Laravel Herd\bin\php.exe
    goto :php_found
)
where php >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    set PHP_CMD=php
    goto :php_found
)

echo Error: PHP not found. Please install PHP or Laravel Herd.
exit /b 1

:php_found
echo Using PHP: %PHP_CMD%
echo.

REM Step 1: Check Docker
echo [1/6] Checking Docker...
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Error: Docker is not running. Please start Docker Desktop.
    exit /b 1
)
echo Docker is running
echo.

REM Step 2: Start Docker containers
echo [2/6] Starting Docker containers...
docker-compose up -d
if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to start Docker containers
    exit /b 1
)

REM Wait for PostgreSQL to be healthy
echo Waiting for PostgreSQL to be ready...
set MAX_ATTEMPTS=30
set ATTEMPT=0
:wait_postgres
docker-compose ps postgres | findstr "healthy" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo PostgreSQL is healthy
    echo.
    goto :postgres_ready
)
set /a ATTEMPT+=1
if !ATTEMPT! GEQ %MAX_ATTEMPTS% (
    echo Error: PostgreSQL did not become healthy in time
    exit /b 1
)
timeout /t 2 /nobreak >nul
goto :wait_postgres

:postgres_ready

REM Step 3: Run migrations
echo [3/6] Running database migrations...
"%PHP_CMD%" artisan migrate --force
if %ERRORLEVEL% NEQ 0 (
    echo Error: Migrations failed
    exit /b 1
)
echo Migrations completed
echo.

REM Step 4: Seed SuperAdmin
echo [4/6] Seeding SuperAdmin user...
"%PHP_CMD%" artisan db:seed --class=SuperAdminSeeder
echo SuperAdmin seeded
echo.

REM Step 5: Seed Branches
echo [5/6] Seeding Branches...
"%PHP_CMD%" artisan db:seed --class=BranchSeeder
echo Branches seeded
echo.

REM Step 6: Seed Page Permissions
echo [6/6] Seeding Page Permissions...
"%PHP_CMD%" artisan db:seed --class=PagePermissionSeeder
echo Page Permissions seeded
echo.

REM Step 7: Start Laravel server
echo === Starting Laravel Development Server ===
echo Server will be available at: http://127.0.0.1:8000
echo Press Ctrl+C to stop the server
echo.

"%PHP_CMD%" artisan serve
