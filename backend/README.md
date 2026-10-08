# NurseExam247 Backend

NurseExam247 is a Laravel-based web application and JSON API for nursing exam preparation. It provides account and profile management, subject and model-test browsing, timed exam attempts, results, and answer solutions.

## Features

- User registration, login, logout, and password reset
- User profile and profile photo management
- Public subject and model-test listings
- Authenticated exam attempts, answer saving, submission, and history
- Result summaries and question-by-question solutions
- Authenticated admin dashboard and model-test management
- Laravel-powered web pages for login, registration, tests, exams, results, history, and profiles

## Requirements

- PHP 8.2 or later
- Composer
- A database supported by Laravel (SQLite is the default in `.env.example`; MySQL can also be used)
- Node.js and npm for building or serving frontend assets

On Windows with XAMPP, the PHP executable is commonly located at `C:\xampp\php\php.exe`. Add `C:\xampp\php` to your Windows `PATH` to use `php` directly. Otherwise, replace `php` in the commands below with `& "C:\xampp\php\php.exe"`.

## Setup

Run these commands in PowerShell from the `backend` directory:

```powershell
cd E:\nurseexam247\backend
composer install

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
}

php artisan key:generate
```

If Composer is not installed, install it from [getcomposer.org](https://getcomposer.org/download/) and select the XAMPP PHP executable (`C:\xampp\php\php.exe`) when prompted.

### Database

The example environment uses SQLite. Create its database file if it does not already exist, then run the migrations:

```powershell
if (-not (Test-Path database\database.sqlite)) {
    New-Item -ItemType File database\database.sqlite | Out-Null
}

php artisan migrate
```

To use MySQL instead, create a database in MySQL (for example, `nursingexam`) and update the database settings in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nursingexam
DB_USERNAME=root
DB_PASSWORD=
```

Make sure the PHP `pdo_mysql` extension is enabled, then run `php artisan migrate`. Keep `.env` local and do not commit credentials or application keys.

## Run the application

Start Laravel's local web server:

```powershell
php artisan serve
```

Open <http://127.0.0.1:8000>. The API health/endpoint overview is available at <http://127.0.0.1:8000/api>.

To install and build the frontend assets:

```powershell
npm install
npm run build
```

For frontend development with Vite, run `npm run dev` in a second terminal while `php artisan serve` is running.

## API overview

All API routes are under `/api`. Authenticated routes require the API token returned by login, sent as a Bearer token in the `Authorization` header.

| Method | Endpoint | Description | Authentication |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | Register an account | No |
| POST | `/api/auth/login` | Log in | No |
| POST | `/api/auth/forgot-password` | Request password reset | No |
| POST | `/api/auth/reset-password` | Reset password | No |
| POST | `/api/auth/logout` | Log out | Yes |
| GET | `/api/auth/me` | Get the current account | Yes |
| GET, PATCH | `/api/profile` | View or update profile | Yes |
| POST | `/api/profile/photo` | Update profile photo | Yes |
| GET | `/api/subjects` | List subjects | No |
| GET | `/api/subjects/{subject}` | View a subject | No |
| GET | `/api/tests` | List published tests | No |
| GET | `/api/tests/{test}` | View a test | No |
| POST | `/api/attempts` | Start an exam attempt | Yes |
| GET | `/api/attempts` | View attempt history | Yes |
| GET | `/api/attempts/{attempt}` | Resume an attempt | Yes |
| GET | `/api/attempts/{attempt}/questions/{question}` | Get an attempt question | Yes |
| PUT | `/api/attempts/{attempt}/answers/{question}` | Save an answer | Yes |
| POST | `/api/attempts/{attempt}/submit` | Submit an attempt | Yes |
| GET | `/api/results/{attempt}` | View a result | Yes |
| GET | `/api/results/{attempt}/summary` | View a result summary | Yes |
| GET | `/api/results/{attempt}/solutions` | List solutions | Yes |
| GET | `/api/results/{attempt}/solutions/{question}` | View a question solution | Yes |
| GET | `/api/admin/dashboard` | View admin dashboard | Yes |
| GET | `/api/admin/users` | Search and list student accounts (admin only) | Yes |
| GET | `/api/admin/users/{user}` | View a student profile and recent exam activity (admin only) | Yes |
| PATCH | `/api/admin/users/{user}/status` | Activate or block a student account (admin only) | Yes |
| GET, POST | `/api/admin/subjects` | List or create subjects (admin/editor) | Yes |
| PATCH | `/api/admin/subjects/{subject}` | Update subject details or availability (admin/editor) | Yes |
| GET, POST | `/api/admin/questions` | List or create questions (admin/editor) | Yes |
| PATCH | `/api/admin/questions/{question}` | Update or activate/deactivate a question (admin/editor) | Yes |
| POST, PATCH | `/api/admin/tests[/{test}]` | Create or update a test | Yes |
| PUT | `/api/admin/tests/{test}/questions` | Replace a test's questions | Yes |
| POST | `/api/admin/tests/{test}/publish` | Publish a test | Yes |

The root API endpoint (`GET /api`) returns a concise endpoint overview. For request and response details, see the route definitions in `routes/api.php` and their controllers in `app/Http/Controllers/Api`.

## Tests

Run the Laravel test suite:

```powershell
php artisan test
```

Or use Composer's test script:

```powershell
composer test
```

## Useful directories

- `app/Http/Controllers/Api` — API controllers
- `app/Models` — Eloquent models
- `routes/api.php` — API routes
- `routes/web.php` — web page routes
- `database/migrations` — database schema migrations
- `tests` — PHPUnit tests
- `resources` — Blade views and frontend source assets