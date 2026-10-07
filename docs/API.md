# Laravel API Blueprint

## API conventions

- Base URL: `/api`
- Authentication: bearer token returned by register or login
- Content-Type: `application/json`
- Success response: `200` or `201`
- Errors: standard `message` and `errors` fields; HTTP status is conveyed by the response status code.
- All date values use ISO 8601 format

## Authentication

The following authentication endpoints are implemented. API tokens are stored hashed and expire after 30 days.

### Register

`POST /api/auth/register`

Request:

```json
{
  "name": "Rafi Ahmed",
  "email": "rafi@example.com",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}
```

Successful registration returns `201` with a bearer token and user profile.

### Login

`POST /api/auth/login`

Request:

```json
{
  "email": "rafi@example.com",
  "password": "Password@123"
}
```

Response:

```json
{
  "token": "opaque-api-token",
  "user": {
    "id": 1,
    "name": "Rafi Ahmed",
    "email": "rafi@example.com",
    "role": "student"
  }
}
```

### Logout

`POST /api/auth/logout` (authentication required). Revokes the current token.

### Current user

`GET /api/auth/me` (authentication required)

### Request password reset code

`POST /api/auth/forgot-password`

```json
{
  "email": "rafi@example.com"
}
```

Sends a six-digit code by email when the account exists. The response is the same whether or not the email is registered. Codes expire in 10 minutes and are limited to five verification attempts.

### Reset password

`POST /api/auth/reset-password`

```json
{
  "email": "rafi@example.com",
  "otp": "123456",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}
```

Resets the password and revokes all existing API tokens for the account.

## User profile

All profile endpoints require authentication using the token returned by register or login.

- `GET /api/profile` — get the current user's profile.
- `PATCH /api/profile` — update `name`, `phone`, `date_of_birth`, `gender`, or `address`.
- `POST /api/profile/photo` — upload a `photo` multipart field (JPEG, PNG, or WebP; maximum 2 MB).

Profile responses include `avatar_url`. Run `php artisan storage:link` to make uploaded photos available via the public storage disk.

Configure a real mail transport (for example `MAIL_MAILER=smtp` with SMTP host, port, username, and password) to deliver reset codes outside local development.

## Subjects

These subject endpoints are public and return active subjects only. The database seeder adds Nursing, General Knowledge, English, and Information and Communication Technology.

### List subjects

`GET /api/subjects`

Optional query parameters:

- `search`
- `page`
- `per_page`

### Get subject

`GET /api/subjects/{id}`

## Topics

### List topics

`GET /api/topics?subject_id={id}`

### Create topic

`POST /api/topics`

Required fields:

- `subject_id`
- `name`

## Questions

### List questions

`GET /api/questions?subject_id={id}&topic_id={id}&difficulty={easy}`

Hidden answer correctness should be omitted unless the current user owns the active attempt.

### Get question

`GET /api/questions/{id}`

### Create question

`POST /api/questions`

Required:

- `subject_id`
- `question_text`
- `options`
- `correct_option`

### Update question

`PUT /api/questions/{id}`

### Delete question

`DELETE /api/questions/{id}`

## Tests

### List tests

`GET /api/tests?status=published`

Implemented: returns published tests only; supports `search`, `subject_id`, `page`, and `per_page`.

### Get test details

`GET /api/tests/{id}`

Implemented: draft and non-published tests return `404`. Premium test questions are not served until the Subscription module can verify an active entitlement. Free test response includes:

- Test metadata
- Question count
- Duration
- Subject and topic information
- Question IDs and options
- Correct answer omitted from student-facing response

### Create test

`POST /api/admin/tests` (Bearer token; `admin` or `editor` role required)

Required:

- `title`
- `subject_id`
- `duration_minutes`
- `question_count`
- `passing_score`
- `total_marks`
- Optional `description`, `negative_marking`, `is_negative_marking_enabled`, `is_premium`, and `question_ids`

Creates a draft. Assigned questions must be active and belong to the selected subject.

### Update draft test

`PATCH /api/admin/tests/{id}` (draft only; `admin` or `editor`)

### Replace draft test questions

`PUT /api/admin/tests/{id}/questions`

Request body: `{ "question_ids": [1, 2, 3] }`. The API distributes total marks evenly over the configured question count.

### Publish draft test

`POST /api/admin/tests/{id}/publish`

Publishing requires exactly `question_count` active questions from the test subject and assigned points equal to `total_marks`. Published tests cannot be edited; create a new draft for changes.

## Admin Dashboard API

### Get platform overview

`GET /api/admin/dashboard` (Bearer token; `admin` or `editor` role required)

Returns live aggregate metrics for student accounts, questions, tests, exam attempts, average finished-attempt score, completion rate, and seven-day exam activity. Metrics not backed by an implemented data module (including revenue, premium subscriptions, and referrals) are identified as unavailable and are not estimated.

### Manage student accounts

These endpoints require an authenticated `admin` role. Editors and students are denied access.

- `GET /api/admin/users?search=ayesha&status=active&sort=newest&page=1&per_page=15` lists student accounts. `search` matches name, email, or phone; `status` is `all`, `active`, or `blocked`; `sort` is `newest` or `activity`; `per_page` is 1–100. The response includes paginated student summaries and overall active/blocked counts. Passwords and tokens are never returned.
- `GET /api/admin/users/{user}` returns a student's profile, account state, aggregate exam activity, and up to 10 recent attempts. Non-student accounts return 404.
- `PATCH /api/admin/users/{user}/status` changes a student's access state:

```json
{
  "is_active": false
}
```

Blocking a student revokes all their API tokens in the same transaction, so existing sessions stop working immediately. Reactivation permits a new login; it does not restore revoked tokens. Admin user management cannot change staff accounts.

### Admin interface availability

The frontend admin console is available at `/admin/dashboard`. The Users menu provides student search, active/blocked filters, profile details, exam activity, pagination, and block/reactivate actions. Personal student data and user controls are administrator-only. The dashboard and published model-test view use implemented endpoints. Creating a test draft is supported; draft listing and question assignment are not yet available in the admin UI. Other planned admin menu modules are marked as coming soon until their APIs are implemented.

## Attempt API

### Start attempt

`POST /api/attempts` (authentication required)

Request:

```json
{
  "test_id": 1
}
```

Implemented: creates a timed attempt and snapshots the question text, options, correct option, explanation, and points in server storage. Response includes attempt ID, `started_at`, server-calculated `expires_at` and `time_remaining_seconds`, stable ordered questions, and saved answers. Correct answers and explanations are omitted until a future solution endpoint is implemented. Premium tests return `403` until subscription entitlements are implemented.

### Get current question

`GET /api/attempts/{attemptId}/questions/{attemptQuestionId}` (authentication required)

Implemented: `attemptQuestionId` is the question item ID returned for this attempt by start/resume; response includes `previous_question_id` and `next_question_id` for navigation. The server returns only questions belonging to this user's in-progress attempt.

### Save answer

`PUT /api/attempts/{attemptId}/answers/{attemptQuestionId}` (authentication required)

Request:

```json
{
  "selected_option": 2
}
```

Implemented autosave: `selected_option` is required and must be `null` (clear) or a 1-based option index that exists. Writes are persisted immediately. Requests after the server deadline return `410` and finalize the attempt as expired.

### Submit attempt

`POST /api/attempts/{attemptId}/submit` (authentication required)

Request body can be empty or include a final confirmation flag. The server must calculate:

- Correct count
- Incorrect count
- Unanswered count
- Score with negative marking
- Percentage
- Pass/fail status

Implemented: submission is idempotent; retries return the same persisted attempt result. The server expires attempts at the deadline and calculates score/counts from the saved question snapshot. `GET /api/attempts/{attemptId}` resumes an owned in-progress attempt or returns its final status/result.

### Test unlock

`GET /api/tests/{id}/unlock-status`

The response should indicate whether the test is available to the current student. Test `02` remains locked until test `01` is completed; test `03` remains locked until test `02` is completed.

Response:

```json
{
  "attempt": {
    "id": 12,
    "status": "submitted",
    "score": 7.5,
    "percentage": 75,
    "correct_count": 6,
    "incorrect_count": 1,
    "unanswered_count": 1
  },
  "analysis": {
    "subject_scores": [],
    "topic_scores": [],
    "weak_topics": []
  }
}
```

### Get attempt history

`GET /api/attempts?test_id={id}` (authentication required)

Implemented: returns only the authenticated student's submitted/expired attempts, paginated; optional `test_id` filter and `per_page` from 1 to 100.

## Result API

### Get result summary

`GET /api/results/{attemptId}/summary` (authentication required)

### Get full result

`GET /api/results/{attemptId}` (authentication required)

Implemented: both endpoints return the caller's own submitted or expired attempt only; in-progress attempts return `409`, another user's result returns `404`. The full result additionally includes safe test metadata. Both responses include:

- Attempt status and timestamps
- Correct, wrong, and skipped counts
- Score, total marks, percentage, pass/fail, and duration
- Full-result endpoint: test ID, title, code, and subject ID

Question-by-question answers, correct choices, and explanations are reserved for the Solution Module; no answer key is returned from these endpoints.

## Solution API

### List solutions for an attempt

`GET /api/results/{attemptId}/solutions?per_page=15` (authentication required)

Implemented: returns paginated answer reviews for the authenticated owner's submitted or expired attempt. `per_page` is optional and must be between 1 and 100. In-progress attempts return `409`; another user's attempt returns `404`.

### Get one solution

`GET /api/results/{attemptId}/solutions/{questionAnswerId}` (authentication required)

Returns the snapshot answer review for the requested question in that attempt. Use the attempt-question `id` (the snapshot answer ID) as `{questionAnswerId}`. Each item includes `id`, `question_id`, `sequence`, `question_text`, `options`, `selected_option`, `correct_option`, `is_correct`, `explanation`, and `points`. `is_correct` is `null` for skipped questions.

The review is read from the attempt's question snapshot, so later edits to the source question do not change historical solutions. Correct answers and explanations are never included in active-exam question responses.

## Analytics API

### Overview

`GET /api/analytics/overview`

Returns:

- Total tests
- Last score
- Average score
- Accuracy rate
- Test count
- Completion rate

### Subject analysis

`GET /api/analytics/subjects`

### Topic analysis

`GET /api/analytics/topics`

## Modules 08–20: implementation specification

The requirements and proposed endpoint/data contracts for Solutions, Leaderboards, Gamification, Referrals, Coupons, Wallet, Subscriptions, Payments, Challenges, Notifications, Progress/Analytics, Admin, and Settings are documented in [MODULES.md](./MODULES.md).

**Implementation status:** Authentication, Profile, Subject, Model Test, Exam, and Result APIs are implemented. Remaining sections in this API blueprint are planned contracts, not a claim that those routes or features are currently implemented. Confirm the current route registry and code before relying on any endpoint. `MODULES.md` defines the business rules, authorization, validation, and response expectations that an implementation must follow.

## Error response format

```json
{
  "message": "The test could not be started.",
  "errors": {
    "test_id": ["The test id is invalid."]
  },
  "status": 422
}
```

## Recommended middleware

- `auth:sanctum` for protected endpoints
- `auth:admin` for content-management endpoints
- `can:manage-tests` or equivalent policy
- Rate limiting for login and test submission APIs
- CSRF protection for browser requests
