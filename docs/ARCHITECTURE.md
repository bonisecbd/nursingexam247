# Architecture and Project Structure

## Overview

The application uses a Laravel API as the backend and a JavaScript frontend as the client. The frontend is responsible for presentation and user interaction, while Laravel performs authentication, question selection, test session management, scoring, and analytics.

## Request flow

```text
Browser
  -> JS frontend
  -> Laravel route
  -> Controller
  -> Request validation
  -> Service / Eloquent model
  -> Database
  -> JSON response
  -> JS frontend rendering
```

## Backend boundaries

### Controllers

Controllers should handle HTTP requests and return standardized JSON responses. They should not contain complex scoring or analytics logic.

### Services

Services should contain business rules such as:

- Calculating test scores
- Enforcing test limits
- Preparing analysis
- Generating reports
- Loading active questions

### Models

Models should contain relationships and basic data behavior. Database constraints must be defined in migrations, while model events should be used only for necessary lifecycle logic.

### Frontend state

The frontend should store temporary state in a small JavaScript state module or local storage:

- Current test id
- Current question index
- Selected answers
- Remaining time
- Attempt id
- Loading and error state

The server remains the authoritative source for final score and answer validation.

## Deployment options

1. Development: Laravel development server and local MySQL.
2. Production: Apache/Nginx + PHP-FPM + MySQL.
3. Optional: Laravel Artisan scheduler for background jobs.

## Security architecture

- Password authentication uses Laravel's hashing.
- API requests use bearer tokens or Laravel Sanctum cookies.
- Role-based authorization protects content management.
- Test attempt endpoints validate the current user and attempt ownership.
- The frontend never receives hidden correct answers before submission.
- All API responses use validation and error handling.
