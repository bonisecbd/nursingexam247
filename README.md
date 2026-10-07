# NurseExam247

NurseExam247 is a nursing exam preparation platform. This repository contains a Laravel backend and JSON API, a React frontend, and project documentation.

## Project structure

```text
backend/   Laravel application, API, migrations, and backend tests
frontend/  React application built with Vite
docs/      API, architecture, database, and feature documentation
```

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite (the backend example configuration) or MySQL

On Windows with XAMPP, PHP is commonly at `C:\xampp\php\php.exe`. Add `C:\xampp\php` to `PATH` to run `php` from PowerShell. If it is not on `PATH`, invoke PHP directly, for example:

```powershell
& "C:\xampp\php\php.exe" -v
```

## Getting started

### 1. Install and configure the backend

In PowerShell:

```powershell
cd E:\nurseexam247\backend
composer install

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
}

php artisan key:generate
```

The example backend configuration uses SQLite. Create the database file and apply migrations:

```powershell
if (-not (Test-Path database\database.sqlite)) {
    New-Item -ItemType File database\database.sqlite | Out-Null
}

php artisan migrate
```

### Demo test accounts

In a local environment, the database seeder creates these demo accounts. Open the frontend login screen and enter the email and password shown for the role you want to test:

| Role | Login user (email) | Login password | Access |
| --- | --- | --- | --- |
| Student | `test@example.com` | `password` | Profile, subjects, model tests, exam attempts, results, and solutions |
| Admin | `admin@nurseexam247.test` | `Admin@12345` | Student features plus the admin dashboard and model-test management API |

Create or refresh the student demo account and seed the demo subjects by running this from `backend/`:

```powershell
php artisan db:seed
```

The seeder only creates demo users when `APP_ENV=local`. These are development credentials; never use them in production. The admin account can access the authenticated admin API endpoints.

For MySQL configuration, Composer/PHP troubleshooting, and backend-specific instructions, see [backend/README.md](backend/README.md).

### 2. Install and start the frontend

In a second PowerShell terminal:

```powershell
cd E:\nurseexam247\frontend
npm install
npm run dev
```

Vite prints the frontend URL when it starts (typically <http://localhost:5173>). The Vite development server proxies `/api` requests to `http://127.0.0.1:8000` by default. To use another backend URL in PowerShell, set `VITE_API_PROXY_TARGET` before starting Vite:

```powershell
$env:VITE_API_PROXY_TARGET = "http://127.0.0.1:8000"
npm run dev
```

Make sure the backend server is also running. In another terminal:

```powershell
cd E:\nurseexam247\backend
php artisan serve
```

The backend is then available at <http://127.0.0.1:8000>; `GET /api` returns the API status and endpoint overview.

## Development commands

Run from `frontend/`:

```powershell
npm run lint
npm run build
```

Run backend tests from `backend/`:

```powershell
php artisan test
```

## API and documentation

- API routes: `backend/routes/api.php`
- API controllers: `backend/app/Http/Controllers/Api`
- Database migrations: `backend/database/migrations`
- Backend setup and endpoint summary: [backend/README.md](backend/README.md)
- API reference: [docs/API.md](docs/API.md)
- Architecture notes: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

## Environment and secrets

The backend reads local configuration from `backend/.env`. Do not commit `.env`, database passwords, application keys, or other credentials. Start from `backend/.env.example` when creating a local environment file.
