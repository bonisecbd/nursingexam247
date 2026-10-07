# Implementation Plan

## Phase 1 — Foundation

- Create Laravel project.
- Configure environment variables.
- Add database and authentication.
- Create roles and permissions.
- Add initial migrations.

## Phase 2 — Content model

- Create subject, topic, question, and test models.
- Seed all 15 required Bengali subjects.
- Add sample nursing questions for every subject.
- Add 100-question 100-mark test configuration.
- Set duration to 60 minutes.
- Enable negative marking at `-0.25`.
- Add sequential unlock configuration for test `01` to test `02` and beyond.
- Implement seeders and question validation rules.

## Phase 3 — Test API

- Create test routes.
- Implement test listing and detail APIs.
- Add test question management.
- Protect admin endpoints.

## Phase 4 — Attempt engine

- Create attempt model and routes.
- Start, pause, and resume attempts.
- Save answer selections.
- Validate selected options.
- Add timer behavior.

## Phase 5 — Scoring and analysis

- Calculate correct, incorrect, unanswered, and total scores.
- Add subject/topic analysis.
- Add weak-area detection.
- Store results in the database.

## Phase 6 — Frontend

- Add login, dashboard, test, result, and report pages.
- Connect frontend with API endpoints.
- Add responsive styling.
- Add progress, timer, and error states.

## Phase 7 — Quality assurance

- Add unit tests for scoring.
- Add API tests for authentication and submission.
- Add UI tests for test flow.
- Test on mobile, tablet, and desktop.

## Phase 8 — Deployment

- Configure production environment.
- Configure HTTPS and database credentials.
- Enable logs and monitoring.
- Test production build and API.

## Recommended first deliverable

Build a minimum working path first:

1. Login
2. Test listing
3. Start a test
4. Answer multiple questions
5. Submit test
6. Display score
7. Display subject and topic analysis

This provides a complete end-to-end working product before adding advanced administration and reporting.
