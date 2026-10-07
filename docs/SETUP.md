# Development Setup and Run Guide

## Requirements

- Windows 10/11
- PHP 8.3 or newer
- Laravel Composer
- Node.js 20 or newer
- npm
- MySQL 8.0+ or MariaDB 10.11+
- Git

## 1. Install PHP and Composer

Install PHP 8.3 through Windows Package Manager:

```powershell
winget install --id PHP.PHP.8.3 --exact --source winget --accept-source-agreements --accept-package-agreements --silent
```

Restart the terminal after installation.

Install Composer using the official installer or Windows Package Manager:

```powershell
winget install --id Composer.Composer --exact --source winget --accept-source-agreements --accept-package-agreements --silent
```

Verify:

```powershell
php -v
composer --version
```

## 2. Create the Laravel project

From the project root:

```powershell
composer create-project laravel/laravel .
```

Create the environment file:

```powershell
Copy-Item .env.example .env
```

Update `.env` with database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nurseexam247
DB_USERNAME=root
DB_PASSWORD=
```

Generate Laravel application key:

```powershell
php artisan key:generate
```

## 3. Install frontend dependencies

If using Vite or a frontend bundler:

```powershell
npm install
npm run dev
```

For the initial JavaScript SPA without Vite:

```powershell
npm init -y
```

Use the official Laravel Vite setup when the frontend becomes complex. The JavaScript frontend can be integrated through Laravel's Vite assets.

## 4. Run migrations and seeders

```powershell
php artisan migrate:fresh --seed
```

Create sample nursing subjects, topics, and MCQs using seeders.

## 5. Start the development server

```powershell
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

For separate frontend development:

```powershell
npm run dev
```

## 6. Production deployment

```text
DocumentRoot: public
PHP-FPM: Node.js optional for frontend build
MySQL database
```

Run the production frontend build and copy generated assets into Laravel's public directory.

## 7. Testing

```powershell
php artisan test
```

Run a single test:

```powershell
php artisan test tests/Feature/AttemptTest.php
```

## 8. Project checklist

- Laravel project created
- Environment file configured
- Database created
- Migrations run
- Seeders run
- Authentication configured
- API routes created
- Frontend pages created
- Score calculation implemented
- Tests run successfully
