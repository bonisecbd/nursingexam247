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
  "password_confirmation": "Password@123",
  "referral_code": "optional-student-referral-code"
}
```

`referral_code` is optional. When valid, referral attribution is created in the same transaction as the new student account; account creation does not issue a reward. Successful registration returns `201` with a bearer token and user profile.

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
- `PATCH /api/profile` — update `name`, `phone`, `date_of_birth`, `gender`, `address`, or `leaderboard_opt_in`.
- `POST /api/profile/photo` — upload a `photo` multipart field (JPEG, PNG, or WebP; maximum 2 MB).

Profile responses include the authenticated user's editable details, `avatar_url`, and leaderboard privacy preference. Leaderboard participation defaults to opted out; the user can enable or disable it from the profile editor. The signed-in dashboard provides a profile editor and photo upload control. Run `php artisan storage:link` to make uploaded photos available via the public storage disk.

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

### Admin subject management

These endpoints require an authenticated `admin` or `editor` role:

- `GET /api/admin/subjects?search=nur&status=all&page=1&per_page=15` lists active and inactive subjects, with question/test counts. `status` is `all`, `active`, or `inactive`.
- `POST /api/admin/subjects` creates a subject.
- `PATCH /api/admin/subjects/{subject}` updates a subject.

Create and update requests accept `name` (required, unique), `code` (required, unique ASCII letters/numbers/underscore/hyphen, up to 20 characters), optional `description` (up to 2,000 characters), and optional `is_active`. Inactive subjects are hidden from public student catalogue endpoints. Subjects are deactivated instead of deleted to preserve question and test references.

The `/admin/dashboard` Subjects page supports searching, status filters, create/edit, and activation state.

## Topics

### List topics

`GET /api/topics?subject_id={id}&search={text}&per_page={1..100}`

Implemented: publicly lists active topics ordered by name, paginated (default 15), optionally filtered by subject or name search. Each item includes its `subject` (`id`, `name`, `code`).

### Create topic

`POST /api/topics` (authentication required; `admin` or `editor` role)

Required fields:

- `subject_id`
- `name`

Optional: `is_active` (default `true`). The subject must exist and be active, and the name must be unique within that subject (`422` otherwise). Responds `201` with `message` and `topic`.

### Update topic

`PATCH /api/topics/{topic}` (authentication required; `admin` or `editor` role)

Optional fields: `name`, `is_active`. Duplicate names within the subject are rejected. Topics are deactivated rather than deleted so historical question and analytics references remain intact.

## Questions

The generic student-facing question endpoints in this legacy blueprint are proposals, not live routes. Implemented admin question-bank routes and authorization are documented below.

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

### Admin question bank

The following question-bank management endpoints require an authenticated `admin` or `editor` role:

- `GET /api/admin/questions?search=airway&subject_id=1&difficulty=easy&status=active&page=1&per_page=15` lists questions, including answer keys for authorized staff. Filters are optional; difficulty is `easy`, `medium`, or `hard`, status is `all`, `active`, or `inactive`, and page size is limited to 100.
- `POST /api/admin/questions` creates a question.
- `PATCH /api/admin/questions/{question}` updates a question or changes its active state.

Create/update fields: `subject_id`, `question_text`, optional `topic_id` (a topic belonging to that subject; moving a question to another subject without supplying `topic_id` detaches the old topic), ordered `options` (2–6 strings), one-based `correct_option`, optional `explanation`, `difficulty`, and `is_active`. New questions require an active subject. Questions assigned to published tests are locked against edits and deactivation to preserve published test content. Referenced questions are deactivated instead of deleted. Bulk import and question-report workflows remain unimplemented.

The admin console Question Bank supports search, subject/status/difficulty filters, paginated results, question create/edit, and activation state.

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

### List and inspect admin tests

- `GET /api/admin/tests?status=all&search=nursing&page=1&per_page=15` lists draft and published tests for admins/editors. `status` is `all`, `draft`, or `published`; search matches the test title or code. The paginated response includes each test's assigned question count and overall draft/published counts.
- `GET /api/admin/tests/{id}` returns test details and assigned question text/options for the protected test builder. Correct answers are not included.

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

### Monitor exam attempts

These read-only endpoints require an authenticated `admin` or `editor` role. Attempt listings include student names and aggregate progress/results only; student email, answer selections, correct answers, and explanations are not returned.

- `GET /api/admin/exams?status=all&search=nursing&page=1&per_page=15` lists attempts for student accounts. `status` can be `all`, `in_progress`, `submitted`, `expired`, or `completed` (`submitted` and `expired` together). Search matches the student's name, test title, or test code. The response includes paginated attempts and status totals.
- `GET /api/admin/exams/{attempt}` returns a single student's attempt summary and timing/progress/result fields; it does not return answer records.

Suspicious-attempt classification is not implemented because the platform has no configured detection policy or reliable signal to support it. These endpoints do not modify, submit, or expire attempts.

### Review results and analytics

`GET /api/admin/results?status=all&search=amina&subject_id=1&test_id=2&page=1&per_page=15` requires an authenticated `admin` or `editor`. It lists only finalized (`submitted` or `expired`) attempts by student accounts. `status` is `all`, `submitted`, `expired`, `passed`, or `failed`; search matches student name, test title, or test code. Responses contain aggregate scores and counts but not contact details, answer records, correct answers, or explanations.

Each response also includes platform-wide aggregates over finalized student attempts: attempt count, average percentage, pass rate, plus the ten most-attempted subjects and tests with attempt counts, average percentage, and pass rate. These are descriptive aggregates over all stored finalized attempts; no date-window adjustment or risk classification is implied. In-progress attempts are excluded.

### Admin interface availability

The frontend admin console is available at `/admin/dashboard`. The Users menu provides student search, active/blocked filters, profile details, exam activity, pagination, and block/reactivate actions. Personal student data and user controls are administrator-only. Model Tests provides a draft/published directory, question assignment from active questions in the test subject, and publishing with server-side validation. Exams provides live/completed/all-attempt views, search, pagination, and safe attempt summaries for admins/editors. Results & Analytics provides a finalized-results directory with subject/test/status/search filters, score summaries, and subject/test performance aggregates. Student-level analytics and suspicious-attempt detection remain unavailable until their rules and screens are implemented.

## Attempt API

### Start attempt

`POST /api/attempts` (authentication required)

Request:

```json
{
  "test_id": 1
}
```

Implemented: creates a timed attempt and snapshots the question text, options, correct option, explanation, and points in server storage. Response includes attempt ID, `started_at`, server-calculated `expires_at` and `time_remaining_seconds`, stable ordered questions, and saved answers, plus `resumed` (`false` on a fresh start). Correct answers and explanations are omitted. Premium tests return `403` unless the caller holds an active premium entitlement (see `GET /api/tests/{test}/access`). Starting is idempotent: if the student already has a running attempt for the test, the endpoint returns `200` with that same attempt (`resumed: true`) instead of creating a duplicate timer, and any stale open attempt past its deadline is finalized as `expired`.

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

Implemented: submission is idempotent; retries return the same persisted attempt result. The server expires attempts at the deadline and calculates score/counts from the saved question snapshot. The response includes `reward: { awarded, points, xp, new_badges }` only when this is the first valid submitted completion of a published test by an active student who is not its author; otherwise `reward` is `null`. Rewards are committed in the same transaction as the finalized attempt. `GET /api/attempts/{attemptId}` resumes an owned in-progress attempt or returns its final status/result.

### Test unlock

`GET /api/tests/{id}/unlock-status` (authentication required)

Implemented: reports whether the published test is available to the current student. Published tests unlock sequentially by creation order: test `02` stays locked until test `01` is completed (submitted or expired), test `03` until `02` is completed, and so on. The first test in the sequence is always unlocked, and a test the student already finished never reports as locked. Draft tests return `404`.

Response:

```json
{
  "test": { "id": 2, "title": "Nursing Model Test 02", "code": "MT02" },
  "locked": true,
  "reason": "Complete \"Nursing Model Test 01\" first to unlock this test.",
  "code": "previous_test_incomplete",
  "required_test": { "id": 1, "title": "Nursing Model Test 01", "code": "MT01" },
  "previous_test": { "id": 1, "title": "Nursing Model Test 01", "code": "MT01" },
  "completed_attempt": null
}
```

`code` is one of `first_in_sequence`, `previous_test_incomplete`, `previous_test_completed`, or `completed`. `required_test` is only present while locked. `completed_attempt` contains `id`, `status`, `score`, `percentage`, and `finished_at` when the student already finished this test, otherwise `null`.

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

## Leaderboard API

### List period standings

`GET /api/leaderboards?period=daily|weekly|monthly|overall&page=1&per_page=50` (public; period required, page size 1–100)

Returns ranked entries with display name, optional public avatar, score, and eligible test count. Only active student accounts that opted in are included. Scores sum each student's best percentage for each published test in the requested period; period date boundaries use `Asia/Dhaka`. Tie-break order is higher score, more eligible tests, earlier achievement time (the latest timestamp among that student's best-per-test attempts), then lower stable user ID. The response includes period, timezone, and pagination metadata. Exact attempt timestamps and private profile fields are not exposed.

### Get my period position

`GET /api/leaderboards/{period}/me` (authentication required)

Returns the caller's rank, aggregate score, eligible test count, and own achievement timestamp even when beyond the first leaderboard page. Opted-out or otherwise ineligible users receive `rank: null` and `eligible: false`. Private profile data and answer-level results are never returned.

## Gamification API

### My progress

`GET /api/gamification/me` (authentication required)

Returns the caller's XP total, non-spendable level progress, points balance derived from the ledger, completed-test count, earned/unearned badges, and up to 10 recent point transactions. XP and points are separate, server-calculated balances.

### Badge catalogue

`GET /api/gamification/badges` (public)

Returns active badge definitions, versioned eligibility requirements, and descriptions without user-specific data.

### My achievements

`GET /api/gamification/achievements` (authentication required)

Returns the caller's completed-test progress and badge award state.

### Admin reward rules

`GET /api/admin/gamification/rules` and `PATCH /api/admin/gamification/rules` (admin only)

Admins can view or set `points_per_test` and `xp_per_test` (integer values from 1 to 10,000). New values apply only to future first-time completions; historical append-only ledger entries are unchanged. The level thresholds and active badge rules are included in the GET response.

The first submitted completion of each published test earns 5 points and 10 XP by default. Repeated submissions, attempts that expire, inactive/non-student accounts, and test-author previews do not earn rewards. Point and XP events use unique idempotency keys tied to user and test.

## Referral API

### My referral code and counts

`GET /api/referrals/me` (active student authentication required)

Creates a cryptographically random, unique referral code on first use and returns the stable code, aggregate invite counts by status, the caller's own attribution (if any), onboarding `can_redeem` state, and `reward_status`. The frontend builds a shareable registration URL from the current site origin and code. Staff and inactive users cannot access referral features.

### My invitations

`GET /api/referrals/me/invites?page=1&per_page=15` (active student authentication required)

Returns only the authenticated inviter's paginated statuses and referral/qualification timestamps. Invitee IDs, names, email addresses, phone numbers, and other private profile fields are not returned.

### Redeem a referral code

`POST /api/referrals/redeem` with `{ "code": "ABC123" }` (active student authentication required)

Links the authenticated new account to an active student inviter once, within 24 hours of registration and before starting an exam. Registration can instead include optional `referral_code`; the account and referral attribution are committed atomically. The student dashboard supports redeeming a code during onboarding. Invalid, self, inactive, or already-used codes fail validation. Attribution stays pending; account creation does not issue points, XP, or money. Purchase qualification and rewards are unavailable until the purchase/subscription module defines an approved qualifying event and anti-abuse policy.

## Analytics API

All three endpoints require authentication and only aggregate the caller's own finalized (`submitted` or `expired`) attempts. Scores are computed on the server; the client never supplies them.

### Overview

`GET /api/analytics/overview`

Returns a flat object:

```json
{
  "total_tests": 12,
  "last_score": 60,
  "average_score": 70,
  "accuracy_rate": 70,
  "test_count": 6,
  "completion_rate": 50,
  "attempt_count": 9,
  "in_progress_count": 1
}
```

- `total_tests` - published tests currently available on the platform
- `test_count` - distinct tests the student has completed
- `last_score` - percentage of the most recent finalized attempt (`null` with no attempts)
- `average_score` - mean percentage across finalized attempts (`0` with no attempts)
- `accuracy_rate` - `correct / (correct + incorrect) * 100` across finalized attempts
- `completion_rate` - `test_count / total_tests * 100`
- `attempt_count` and `in_progress_count` - finalized and open attempts

Whole numbers are serialized as integers and fractional values as decimals.

### Subject analysis

`GET /api/analytics/subjects`

Returns `data` (one row per subject with finalized attempts) plus `meta.subject_count`. Each row: `subject_id`, `subject_name`, `subject_code`, `attempt_count`, `test_count`, `correct_count`, `incorrect_count`, `unanswered_count`, `average_percentage`, `best_percentage`, `pass_rate`, and `accuracy_rate`.

### Topic analysis

`GET /api/analytics/topics`

Returns `data` sorted weakest-first (ascending `accuracy_rate`) plus `meta.topic_count`, `meta.weak_threshold` (`50`), and `meta.min_sample` (`5`). Each row: `topic_id`, `topic_name`, `subject_id`, `subject_name`, `answered_count`, `attempted_count`, `skipped_count`, `correct_count`, `incorrect_count`, `accuracy_rate`, and `is_weak` (accuracy at or below the weak threshold **and** at least `min_sample` answered questions, so a thin sample is never labeled weak). Built from saved answer snapshots joined to each question's topic; questions without a topic never appear. Coverage is capped at the 100 most-answered topics.

## Modules 08–20: implementation specification

The requirements and proposed endpoint/data contracts for Solutions, Leaderboards, Referrals, Coupons, Wallet, Subscriptions, Payments, Challenges, Notifications, Progress/Analytics, Admin, and Settings are documented in [MODULES.md](./MODULES.md). Gamification endpoints are now implemented as described above.

**Implementation status:** Authentication, Profile, Subject, Topics, Model Test, Exam (including idempotent start and sequential test unlock), Result, Leaderboard, Gamification, referral attribution, and student Progress/Analytics APIs are implemented and covered by feature tests. The Coupon, Wallet, Subscription/Premium, and Payment endpoints from modules 12-15 are implemented and specified in [MODULES.md](./MODULES.md). Remaining sections in this blueprint (generic student question CRUD, bulk import) are planned contracts, not a claim that those routes are currently implemented. Confirm the current route registry and code before relying on any endpoint. `MODULES.md` defines the business rules, authorization, validation, and response expectations that an implementation must follow.

API routes are registered in two files: `backend/routes/api.php` and `backend/routes/api_progress.php` (topics, unlock status, analytics), both mounted under the `/api` prefix by `backend/bootstrap/app.php`.

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
