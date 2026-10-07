@echo off
setlocal

:: Load environment variables from .env file
for /f "usebackq delims=" %L in (.env) do (
    set %L
)

:: Set Laravel environment
set APP_ENV=local
set APP_DEBUG=true
set DB_CONNECTION=mysql
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_DATABASE=nursingexam
set DB_USERNAME=root
set DB_PASSWORD=

:: Run Laravel artisan serve
cd /d "%~dp0"
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000 %*