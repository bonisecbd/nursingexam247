# Nursing Job Preparation MCQ Model Test Software — Full Blueprint

## 1. Product purpose

Nursing Job Preparation Model Test is a web application for nursing students and graduates who want to prepare for job-related exams. The product provides timed MCQ tests, nursing-domain content, immediate scoring, category-wise analysis, weak-area detection, attempt history, and downloadable result reports.

## 2. Functional goals

- Deliver structured Bengali nursing MCQ model tests.
- Support all 15 required subjects: Nursing Fundamentals, Anatomy & Physiology, Medical-Surgical Nursing, Pharmacology, Community Health Nursing, Child Health Nursing, Maternal & Child Health, Psychiatric Nursing, Nutrition, General Knowledge, Bangladesh Affairs, English, Mathematics, ICT, and Previous Nursing Questions.
- Evaluate answer correctness and score each test.
- Support a 100-question, 100-mark test with 60 minutes duration.
- Apply negative marking, with `-0.25` for every incorrect answer.
- Unlock model tests sequentially; test 02 opens only after test 01 is completed and test 03 opens only after test 02 is completed.
- Show score, percentage, attempted questions, unanswered questions, and incorrect answers.
- Provide subject-wise and topic-wise performance analysis.
- Offer test history and previous attempts.
- Allow administrators to manage questions, exams, subjects, and test configuration step by step.
- Support a lightweight JavaScript frontend backed by a Laravel REST API.

## 3. Primary users

| Role | Main responsibilities |
|---|---|
| Student | Browse tests, attempt exams, view score and analysis, download reports |
| Administrator | Manage subjects, questions, quizzes, users, and reports |
| Content editor | Add or update nursing MCQ content |
| Super administrator | Manage application configuration and roles |

## 4. Product architecture

```text
Browser / JavaScript SPA
        |
        v
Laravel REST API
        |
        +-- Authentication & authorization
        +-- Test management
        +-- Question banks
        +-- Exam session engine
        +-- Scoring service
        +-- Analysis service
        +-- Report service
        |
        v
MySQL / MariaDB
```

The frontend should be a static JavaScript application served by Laravel or deployed separately. It calls JSON endpoints through Axios or native Fetch API. Laravel is the source of truth for all tests, questions, answers, scores, and analytics.

## 5. Main application modules

1. Authentication and user management
2. Dashboard and test catalog
3. Test attempt and timer
4. Question rendering and answer selection
5. Score calculation and result storage
6. Detailed subject, topic, and skill analysis
7. Attempt history and report export
8. Administrator content management
9. System configuration and audit logs

## 6. Suggested project structure

```text
app/
  Http/
    Controllers/
    Middleware/
    Requests/
    Resources/
  Models/
  Services/
  Policies/
  Rules/
database/
  migrations/
  seeders/
  factories/
resources/
  views/
  js/
  css/
public/
routes/
tests/
config/
```

For the JavaScript frontend, prefer a small SPA structure inside `resources/js`:

```text
resources/js/
  app.js
  api.js
  store.js
  components/
  pages/
  styles/
```

## 7. Domain model

### User

- `id`
- `name`
- `email`
- `password`
- `role`
- `status`
- `created_at`
- `updated_at`

### Subject

- `id`
- `name`
- `code`
- `description`
- `is_active`

### Topic

- `id`
- `subject_id`
- `name`
- `code`
- `description`

### Question

- `id`
- `subject_id`
- `topic_id`
- `question_text`
- `options` JSON
- `correct_option`
- `explanation`
- `difficulty`
- `tags` JSON
- `is_active`
- `created_by`

### Test

- `id`
- `title`
- `code`
- `description`
- `subject_id`
- `duration_minutes`
- `question_count`
- `passing_score`
- `status`
- `is_published`
- `created_by`

### TestQuestion

- `id`
- `test_id`
- `question_id`
- `sequence`
- `points`

### Attempt

- `id`
- `user_id`
- `test_id`
- `status`
- `started_at`
- `finished_at`
- `duration_seconds`
- `score`
- `total_points`
- `correct_count`
- `incorrect_count`
- `unanswered_count`
- `percentage`
- `created_at`

### AttemptAnswer

- `id`
- `attempt_id`
- `question_id`
- `selected_option`
- `is_correct`
- `time_taken_seconds`
- `submitted_at`

### TestSetting

- `id`
- `key`
- `value`
- `type`

## 8. API design

### Authentication

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

### Subjects and topics

- `GET /api/subjects`
- `GET /api/subjects/{id}/topics`

### Questions

- `GET /api/questions`
- `GET /api/questions/{id}`
- `POST /api/questions`
- `PUT /api/questions/{id}`
- `DELETE /api/questions/{id}`

### Tests

- `GET /api/tests`
- `GET /api/tests/{id}`
- `POST /api/tests`
- `PUT /api/tests/{id}`
- `DELETE /api/tests/{id}`
- `POST /api/tests/{id}/questions`
- `DELETE /api/tests/{id}/questions/{questionId}`

### Test attempt

- `POST /api/attempts`
- `GET /api/attempts/{id}`
- `PUT /api/attempts/{id}`
- `POST /api/attempts/{id}/submit`
- `GET /api/attempts/{id}/answers`

### Results and analysis

- `GET /api/results/{attemptId}`
- `GET /api/results/{attemptId}/summary`
- `GET /api/analytics/overview`
- `GET /api/analytics/subjects`
- `GET /api/analytics/topics`

## 9. Test flow

1. Student logs in.
2. Dashboard shows available tests.
3. Student selects a test and starts it.
4. Laravel creates an attempt and returns the first question.
5. The frontend displays the question, options, timer, progress, and previous/next controls.
6. Student answers questions. Answers are saved locally and/or through API.
7. On submission, Laravel calculates score and updates the attempt.
8. The frontend displays result summary and detailed analysis.
9. Student can review previous attempts and download a report.

## 10. Scoring rules

- Correct answer: full points.
- Wrong answer: zero points, with negative marking if configured.
- Unanswered: zero points.
- Total score: sum of question points.
- Percentage: `(correct points ÷ total points) × 100`.
- Passing status: percentage greater than or equal to the test's `passing_score`.

Recommended default scoring:

```text
Correct: +1 point
Incorrect: 0 point
Unanswered: 0 point
```

For advanced use, support a configurable negative marking value such as `-0.25`.

## 11. Analysis requirements

The dashboard should show:

- Overall score and percentage
- Attempted and unanswered counts
- Correct and incorrect counts
- Subject-wise score
- Topic-wise score
- Weak topics and weak subjects
- Accuracy by difficulty
- Average response time
- Correct answer rate
- No-answer rate
- Question review with correct answer and explanation

## 12. Result report requirements

The report should include:

- Student name and test name
- Test date and duration
- Score and percentage
- Passing status
- Attempted, correct, incorrect, unanswered counts
- Subject-wise performance
- Topic-wise weak areas
- Question review
- Explanation for incorrect answers
- Signature or branding placeholder

## 13. Frontend interface

### Dashboard

- Welcome message
- Overall progress
- Recent attempt
- Available tests
- Subject cards
- Recommended next test

### Test screen

- Test title and timer
- Question count and progress bar
- Question number and subject/topic label
- MCQ options
- Previous and Next buttons
- Save & resume
- Submit test confirmation

### Result screen

- Score ring or progress bar
- Correct, incorrect, unanswered counts
- Subject score cards
- Weak-area list
- Retry test option
- Download report option

## 14. Security requirements

- Use Laravel authentication and CSRF protection.
- Protect all API endpoints with authentication and authorization.
- Prevent students from changing answer values directly in the browser.
- Validate all request data on the server.
- Use encrypted session cookies and HTTPS.
- Store only required personal information.
- Use database transactions while creating and submitting attempts.
- Hide question answers from public APIs until submission.
- Add rate limiting for login and API requests.

## 15. Performance requirements

- Load test questions efficiently.
- Cache test details and statistics where appropriate.
- Use pagination for question and history lists.
- Avoid returning all answers for every question at once.
- Support lazy loading for relations.
- Configure response caching only for read-only data.

## 16. Testing strategy

### Unit tests

- Scoring calculation
- Passing status
- Negative marking
- Subject/topic analysis

### API tests

- Authentication
- Test creation and listing
- Question validation
- Test submission
- Result calculation
- Authorization checks

### Frontend tests

- Test rendering
- Answer selection
- Timer behavior
- Submission flow
- Result screen

## 17. Implementation phases

1. Project setup and configuration
2. User authentication and roles
3. Subject, topic, question, and test models
4. Database migrations and seeders
5. Question and test API
6. Attempt and submission API
7. Scoring and analysis service
8. Dashboard and test frontend
9. Result dashboard and report export
10. Admin content management
11. Testing and deployment

## 18. Acceptance criteria

- A registered student can log in and see tests.
- An administrator can create a test with at least one question.
- A student can start, answer, and submit a timed test.
- The backend calculates score and stores result.
- The frontend displays one correct/incorrect/unanswered summary.
- Subject and topic analysis are generated.
- A student can view previous attempts.
- A report can be downloaded.
- Unauthorized users cannot access or modify tests.

## 19. Recommended software stack

- PHP 8.3+
- Laravel 12 or compatible stable version
- MySQL 8.0+ or MariaDB 10.11+
- Laravel Sanctum for SPA authentication
- Native JavaScript frontend and Fetch API, optionally Axios
- Bootstrap or Tailwind CSS for responsive UI
- Laravel Pest or PHPUnit for tests
- Apache or Nginx with PHP-FPM for deployment
