# Database Schema and Data Model

## Database requirements

Use MySQL 8.0+ or MariaDB 10.11+. The database is the source of truth for tests, questions, attempts, and results.

## Tables

### users

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| name | string | Display name |
| email | string unique | Login identifier |
| password | string | Hashed password |
| role | string | Defaults to `student` |
| is_active | boolean | Disabled users cannot log in |
| email_verified_at | timestamp nullable | Optional verification |
| phone | string nullable | Profile phone number |
| date_of_birth | date nullable | Profile date of birth |
| gender | string nullable | Profile gender |
| address | text nullable | Profile address |
| avatar_path | string nullable | Path on the public storage disk |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

### subjects

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| name | string | e.g. Nursing |
| code | string unique | NUR |
| description | text nullable | Subject description |
| is_active | boolean | Active flag |

### questions

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| subject_id | bigint unsigned | Active subject that owns the question |
| question_text | text | Student-facing question |
| options | json | Ordered answer choices |
| correct_option | unsigned small integer | 1-based correct choice; never return in an active test response |
| explanation | text nullable | Shown only after submission/result release |
| difficulty | string | easy, medium, hard |
| is_active | boolean | Only active questions can be assigned |
| created_by | bigint unsigned nullable | Author |

### tests

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| title | string | Student-facing title |
| code | string unique | Generated identifier |
| description | text nullable | Test description |
| subject_id | bigint unsigned | Test subject |
| duration_minutes | unsigned small integer | Positive duration |
| question_count | unsigned small integer | Exact required assignment count |
| total_marks | decimal(8,2) | Positive maximum score |
| passing_score | decimal(8,2) | Between zero and total marks |
| negative_marking | decimal(8,2) | Non-negative penalty per incorrect answer |
| is_negative_marking_enabled | boolean | Whether to apply the penalty |
| is_premium | boolean | Access flag for the planned subscription module |
| status | string | draft or published |
| created_by | bigint unsigned | Author/admin |

### test_questions

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| test_id | bigint unsigned | Test definition |
| question_id | bigint unsigned | Assigned question |
| sequence | unsigned small integer | Display order, unique within test |
| points | decimal(8,2) | Points awarded for a correct answer |

### API authentication and password reset

- `api_tokens`: stores SHA-256 hashes of 30-day bearer tokens; each token belongs to one user and is revoked on logout or password reset.
- `password_reset_otps`: stores hashed, expiring email OTPs and failed verification attempt counts.

## Planned product module data

Tables and fields for exam delivery, results, rewards, payments, subscriptions, challenges, notifications, administration, and settings are specified in [MODULES.md](./MODULES.md). These are a logical design, not a claim that the corresponding migrations or features already exist. Keep money/reward ledgers append-only, persist payment provider references uniquely, and derive results from saved answers on the server.

### topics

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| subject_id | bigint unsigned | Foreign key |
| name | string | e.g. Cardiovascular System |
| code | string | Topic code |
| description | text nullable | Topic description |
| is_active | boolean | Active flag |

### questions

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| subject_id | bigint unsigned | Foreign key |
| topic_id | bigint unsigned nullable | Foreign key |
| question_text | text | Question text |
| options | json | Options as array |
| correct_option | integer | 1-based option index |
| explanation | text nullable | Answer explanation |
| difficulty | string | easy, medium, hard |
| tags | json | Tags |
| is_active | boolean | Active flag |
| created_by | bigint unsigned nullable | Creator |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

### tests

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| title | string | Student-facing test title |
| code | string unique | Generated test code |
| description | text nullable | Test description |
| subject_id | bigint unsigned | Test subject |
| duration_minutes | unsigned small integer | Positive duration |
| question_count | unsigned small integer | Exact question count required before publish |
| total_marks | decimal(8,2) | Positive maximum score |
| passing_score | decimal(8,2) | Between zero and total marks |
| negative_marking | decimal(8,2) | Non-negative penalty per incorrect answer |
| is_negative_marking_enabled | boolean | Whether incorrect answers are penalized |
| is_premium | boolean | Premium access flag; entitlement checks are not implemented yet |
| status | string | draft or published |
| created_by | bigint unsigned | Admin/editor who created the test |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

### test_questions

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| test_id | bigint unsigned | Foreign key |
| question_id | bigint unsigned | Foreign key |
| sequence | unsigned small integer | Question order, unique within test |
| points | decimal(8,2) | Points per question |
| created_at | timestamp | Assignment creation time |
| updated_at | timestamp | Assignment update time |

### attempts (Exam Module, implemented)

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| user_id | bigint unsigned | Student |
| test_id | bigint unsigned | Test taken |
| status | string | in_progress, submitted, expired |
| started_at | timestamp | Start time |
| expires_at | timestamp | Server-authoritative deadline |
| finished_at | timestamp nullable | End time |
| duration_seconds | integer | Time spent |
| score | decimal nullable | Final score |
| total_marks | decimal | Total possible marks snapshotted on start |
| correct_count | integer | Correct answers |
| incorrect_count | integer | Wrong answers |
| unanswered_count | integer | Unanswered count |
| percentage | decimal nullable | Final percentage |
| passed | boolean nullable | Server-calculated pass status |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

### attempt_answers (Exam Module, implemented)

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned | Primary key |
| attempt_id | bigint unsigned | Foreign key |
| question_id | bigint unsigned nullable | Source question; may become null if deleted |
| sequence | unsigned small integer | Stable position within attempt |
| question_text | text | Immutable snapshot taken when the attempt starts |
| options | json | Immutable ordered options snapshot |
| correct_option | unsigned small integer | Server-only answer key snapshot |
| explanation | text nullable | Solution snapshot; never return during an active exam |
| points | decimal | Marks for a correct answer |
| selected_option | integer nullable | 1-based selected option |
| answered_at | timestamp nullable | Last answer-write time |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

### roles and permissions

A separate table is recommended for production:

- `roles`: id, name, guard_name
- `permissions`: id, name, guard_name
- `role_user`: user_id, role_id
- `permissions_role`: role_id, permission_id

## Important relationships

- Subject has many topics.
- Subject has many questions.
- Topic belongs to one subject.
- Test has many test-subject assignments.
- Test has many test questions.
- Question can belong to multiple tests through test questions.
- Attempt belongs to one user and one test.
- Attempt has many answers.
- Attempt answer belongs to one attempt and one question.

## Database constraints

- Each test question should be unique per test and question.
- Each attempt answer should be unique per attempt and question.
- Test code and user email should be unique.
- `correct_option` must be within the option range.
- `question_count` must not exceed the available question bank.
- The final attempt score must be calculated only after submission.
