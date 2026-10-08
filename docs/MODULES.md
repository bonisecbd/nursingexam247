# Product Module Specification (Modules 05–20)

## Purpose and implementation status

This document is the product and API contract for the next implementation phases of NurseExam247. It gives an AI coding agent explicit domain terms, proposed routes, payloads, authorization, validation, and business rules.

**These modules are planned unless explicitly marked implemented below.** The current application implements the Authentication, Profile, Subject, and Model Test APIs described in [API.md](./API.md). The route examples below are proposed contracts for the other modules; do not describe them as live, call them from the frontend, or assume their tables exist until the routes, migrations, policies, and tests have been implemented. Existing legacy API blueprint sections may also be plans.

Common API conventions:

- Prefix every route with `/api`; send and receive JSON unless an endpoint is explicitly a multipart upload or provider callback.
- Protected routes require `Authorization: Bearer <token>`. A user may read or change only their own attempts, results, wallet, subscriptions, referrals, and notifications.
- Admin routes require an explicit server-side permission/policy check. Hiding an admin UI control is not authorization.
- Validate every submitted ID, enum, amount, date, pagination size, and state transition. Return validation errors using Laravel's normal `message` and `errors` JSON fields with the appropriate HTTP status.
- Store timestamps in UTC and return ISO 8601. Use the configured application timezone for challenge periods and calendar leaderboard boundaries; the product's default timezone is `Asia/Dhaka`.
- Use database transactions for operations that change multiple related records. Make retryable operations idempotent.
- Never trust a client-supplied score, correct answer, price, discount amount, payment status, wallet balance, XP total, or admin flag. Calculate/verify these on the server.
- Financial amounts use integer minor units (poisha for BDT) or an exact fixed-precision decimal; never use binary floating point for money.

## Subject Catalogue

**Status: Implemented.** Students can list/search active subjects. Admins and editors can list all subjects, create and update subject metadata, and activate/deactivate catalogue entries using the protected `/api/admin/subjects` endpoints. Subject references are retained; deletion is not supported. See [API.md](./API.md#subjects).

## 05. Model Test Module

**Status: Implemented.** The API provides public paginated list and free published-test detail endpoints, plus protected draft create/update, ordered question replacement, and publish endpoints. Premium question detail is denied until subscription entitlements are implemented. `admin` and `editor` roles may manage tests; published tests are immutable. See [API.md](./API.md#tests) and `backend/tests/Feature/ModelTestApiTest.php` for the live contract and coverage.

**Purpose:** admins/authors create a named exam definition with a fixed question count, duration, marks, and selected questions. A model test is the reusable definition; an attempt is one student's sitting.

Implemented endpoints:

- `GET /api/tests?status=published&page=1&per_page=15` — list tests available to the current student; never expose draft tests.
- `GET /api/tests/{test}` — return metadata and student-safe question/options data, never `correct_option` or answer explanations.
- `GET /api/admin/tests?status=all&page=1&per_page=15` and `GET /api/admin/tests/{test}` — list and inspect tests for admins/editors; assigned correct answers are omitted.
- `POST /api/admin/tests` — create a draft. Admin/editor only.
- `PATCH /api/admin/tests/{test}` — edit a draft or other fields permitted by policy.
- `POST /api/admin/tests/{test}/publish` — validate and publish.
- `PUT /api/admin/tests/{test}/questions` — replace the ordered question assignment on a draft.

Create request:

```json
{
  "title": "Nursing Model Test 01",
  "subject_id": 1,
  "duration_minutes": 60,
  "question_count": 100,
  "total_marks": 100,
  "passing_score": 40,
  "negative_marking": 0.25,
  "question_ids": [11, 12, 13]
}
```

Rules:

- Require a non-empty title, positive duration/count/marks, a passing threshold within the documented score scale, and valid active question IDs.
- The number of distinct assigned questions must equal `question_count` before publish. All questions must be active and accessible to the chosen subject/test.
- Store test question order and per-question points on the assignment, not in the client. Sum of question points must equal `total_marks` unless the product explicitly allows a different scoring rule.
- Define one canonical negative-marking convention. Prefer a positive penalty amount such as `0.25` subtracted per wrong answer; do not mix that with the older blueprint's negative-valued `-0.25`.
- Published test content/scoring should not silently change for attempts already started. Snapshot assignments/scoring on attempt start, or version the test and bind each attempt to that version.
- Drafts are admin-only; only published, currently accessible tests appear to students. Premium/access rules are enforced by the Subscription module.
- List responses are paginated and include only safe metadata: `id`, `title`, `subject`, `question_count`, `duration_minutes`, `total_marks`, `passing_score`, availability, and premium requirement.

Logical data: `tests` (definition/status/scoring/access flags), `test_questions` (test, question, unique sequence, points), and `questions` (subject, text, options, correct option, explanation, difficulty, activation). The current question records are not versioned; instead, published tests cannot be edited. Before adding exam attempts, snapshot question text/options/correct answer/explanation and points so historical results survive question-bank edits.

## 06. Exam Module

**Status: Implemented.** `POST /api/attempts`, `GET /api/attempts/{attempt}`, `GET /api/attempts/{attempt}/questions/{question}`, `PUT /api/attempts/{attempt}/answers/{question}`, and `POST /api/attempts/{attempt}/submit` are protected by bearer auth and owner checks. Start snapshots the ordered question content/answer key/points; timer expiry is server-authoritative; answer writes are persisted immediately; submit is idempotent. Premium tests are denied until the Subscription Module exists. See [API.md](./API.md#attempt-api) and `backend/tests/Feature/ExamAttemptApiTest.php`.

**Purpose:** control one timed student attempt, question navigation, reliable answer saving, and final submission.

Proposed endpoints:

- `POST /api/attempts` with `{ "test_id": 1 }` — start an eligible published test; return attempt ID, authoritative `started_at`/`expires_at`, question order, and first safe question.
- `GET /api/attempts/{attempt}/questions/{question}` — fetch a question belonging to the authenticated user's in-progress attempt.
- `PUT /api/attempts/{attempt}/answers/{question}` with `{ "selected_option": 2 }` — save or clear one answer.
- `POST /api/attempts/{attempt}/submit` — submit early; body may be empty.
- `GET /api/attempts/{attempt}` — resume own in-progress attempt and retrieve saved answers/time remaining.

Rules:

- One attempt belongs to one user and one test/version. Enforce ownership and `in_progress` status on every read/write.
- The server is authoritative for `started_at` and deadline. A client timer is display-only. On deadline, close/score the attempt as expired/submitted under the same scoring rules as normal submission.
- Persist answer writes as they happen (autosave). Repeating the same answer write is idempotent. Define `selected_option: null` as clearing an answer; validate non-null choices against that question's options.
- Validate that each question belongs to the attempt snapshot. Never send correctness or explanation while the attempt is active.
- Support Next/Previous entirely through question IDs/order already provided; navigation does not itself change answers or score.
- Submission and expiry must be atomic and idempotent: retrying a submit returns the one saved final result and must not create duplicate results/rewards.
- A disconnected client can resume an attempt only before its server-side deadline and only with the owner's token.

Logical data: `attempts` (user, test, status, started/deadline/finished timestamps, final score fields) and `attempt_answers` (unique attempt/sequence, nullable source question foreign key, snapshotted question/options/correct answer/explanation/points, nullable selected option, answer timestamps). See [DATABASE.md](./DATABASE.md) for the base entities.

**Admin monitoring: Partially implemented.** Admins and editors can list/filter/paginate student exam attempts and inspect safe summary/timing/progress fields. The admin view does not reveal selected answers, answer keys, explanations, or student contact details. Suspicious-attempt detection is intentionally not implemented until detection rules are defined.

## 07. Result Module

**Status: Implemented.** `GET /api/results/{attempt}/summary`, `GET /api/results/{attempt}`, and `GET /api/attempts?test_id=...` return only the authenticated user's finalized results/history. In-progress results return `409`; a different user's attempt returns `404`. Result responses contain aggregate data only; use the Solution API for answer review. See [API.md](./API.md#result-api) and `backend/tests/Feature/ResultApiTest.php`.

**Purpose:** present the authoritative score and answer counts after final submission.

Proposed endpoints:

- `GET /api/results/{attempt}` — aggregate result and test metadata for the attempt owner.
- `GET /api/results/{attempt}/summary` — compact score card.
- `GET /api/attempts?test_id={test}` — the authenticated student's own attempt history for a test.

Required result fields: `attempt_id`, `test`, `status`, `correct_count`, `wrong_count`, `skipped_count`, `score`, `total_marks`, `percentage`, `passed`, `started_at`, `finished_at`, and `duration_seconds`.

Rules:

- Calculate counts from the immutable attempt question set: unanswered questions are skipped; answered options are correct or wrong.
- For per-question point value `p` and positive wrong-answer penalty `n`, score is `sum(correct p) - sum(wrong n)`. Skipped answers add/subtract zero. Clamp the displayed/final score to a minimum of zero unless the product explicitly changes this rule.
- `percentage = score / total_marks * 100`; return `0` when total marks is zero (although published tests must prohibit zero total marks). Compare pass status using the configured passing-score scale consistently.
- Calculate and persist once on submission/expiry. Never accept counts or final score from the browser.
- Do not reveal a result to a different user. If result release is delayed, reveal only the allowed summary until the configured release time.
- Repeated submit/result requests must return the same stored values.

**Admin reporting: Partially implemented.** Admins and editors can browse finalized student results with status, student/test search, subject/test filters, and pagination. Aggregate analytics report completed attempt count, average percentage, pass rate, and top subject/test performance. In-progress attempts and answer-level data are excluded. Student-level trend reports remain planned.

## 08. Solution Module

**Status: Implemented.** The Solution API serves immutable answer snapshots only to the owner of a submitted or expired attempt. In-progress and other users' attempts are rejected. See [API.md](./API.md#solution-api) and `backend/tests/Feature/ResultApiTest.php`.

**Purpose:** teach the student by explaining the correct answer after exam submission.

Endpoints:

- `GET /api/results/{attempt}/solutions` — paginated solutions for a submitted attempt.
- `GET /api/results/{attempt}/solutions/{question}` — one answer review.

Each solution item includes `question_id`, `question_text`, safe options, `selected_option`, `correct_option`, `is_correct`, `explanation`, and optional subject/topic metadata.

Rules:

- Confirm the caller owns the submitted attempt. Admin access requires an explicit policy.
- Do not expose `correct_option`, answer keys, or explanations through test/question endpoints before submission.
- Answers are released immediately after submission or expiry; in-progress attempts return `409`.
- Source correctness and explanations from the question version attached to that attempt so later edits cannot rewrite historical answers.

## 09. Leaderboard Module

**Purpose:** rank eligible, opted-in students from completed exam/challenge performance.

Proposed endpoints:

- `GET /api/leaderboards?period=daily|weekly|monthly|overall&page=1&per_page=50`
- `GET /api/leaderboards/{period}/me` — caller's own rank and aggregate score, including when outside the current page.

Rules:

- Period boundaries use `Asia/Dhaka`: daily is the local calendar day, weekly is Monday–Sunday, monthly is the calendar month, and overall is all eligible history.
- Count submitted/expired scored attempts only once. Exclude drafts, abandoned/in-progress attempts, test authors' preview attempts, and users who opt out or are inactive.
- Define the ranking score consistently (recommended: sum of best eligible percentage per test within the period, with the eligible test count displayed). Do not rank by raw marks across tests with different totals.
- Deterministic tie-break: higher score, then more completed eligible tests, then earlier achievement time, then stable user ID. Document the exact rule in the response.
- Return only public display name/avatar and aggregate ranking fields. Never return email, phone, address, token, or private profile.
- Cache may be used for reads but must be invalidated/rebuilt from authoritative result records.

Logical data: optional `leaderboard_entries` cache keyed by period type/key and user; result records remain the source of truth.

## 10. Gamification Module

**Purpose:** reward verified learning activity with points, XP, levels, badges, and achievements.

Proposed endpoints:

- `GET /api/gamification/me` — XP, level, points balance, badges, achievement progress.
- `GET /api/gamification/badges` — public badge catalogue and eligibility descriptions.
- `GET /api/gamification/achievements` — caller's achievement progress/completions.

Rules:

- Award points/XP only from server-verified events such as a first valid test submission or challenge completion. Specify amount and eligibility in admin-controlled rules.
- Use a unique idempotency key per user/source event/reward type, so retried submissions cannot grant duplicate rewards.
- Store an immutable `point_transactions` ledger; derive current point balance from the ledger or reconcile a cached balance against it.
- Store XP events separately from spendable points. XP/level progress cannot be spent as wallet money.
- Level thresholds and badges are explicit versioned rules; badge award is unique per user/badge and records award time/source.
- Never grant rewards for an unsubmitted, invalid, duplicate, refunded, or administratively disqualified activity. Reversals must be a separate compensating ledger entry, never mutation/deletion of the original.

Logical data: `reward_rules`, `point_transactions`, `xp_events`, `badge_definitions`, `user_badges`, `achievement_definitions`, and `user_achievements`.

## 11. Referral Module

**Purpose:** allow a user to invite a new student and receive an auditable reward when the referral qualifies.

Proposed endpoints:

- `GET /api/referrals/me` — own referral code and aggregate counts.
- `GET /api/referrals/me/invites?page=1` — own invite statuses; do not expose the invitee's private details.
- `POST /api/referrals/redeem` with `{ "code": "ABC123" }` — attach an eligible new account to an inviter during registration/onboarding.

Rules:

- Generate a unique, non-guessable referral code per eligible inviter; code cannot be changed by a client.
- One account can be referred at most once, cannot refer itself, and cannot change inviter after qualifying activity.
- Qualifying event must be explicit and configurable (recommended: referred user completes first paid, non-refunded purchase, or a policy-approved activation). Creating an account alone must not issue a valuable reward.
- Award inviter/invitee benefits once, only after anti-abuse checks, using idempotent reward ledger entries. Reversals for refunded/disqualified purchases are compensating entries.
- Admin adjustments require reason, actor, audit log, and permission.

Logical data: `referral_codes`, `referrals` (inviter, invitee, status, qualifying event/time), reward ledger references, and audit events.

## 12. Coupon Module

**Purpose:** apply validated promotions to eligible orders/subscriptions.

Proposed endpoints:

- `POST /api/coupons/validate` with `{ "code": "NURSE10", "product_id": 2 }` — authenticated quote/check before payment.
- `GET /api/admin/coupons` — admin list.
- `POST /api/admin/coupons` — create.
- `PATCH /api/admin/coupons/{coupon}` — update under policy.
- `POST /api/admin/coupons/{coupon}/disable` — disable.

Coupon fields: normalized unique code, discount type (`fixed` or `percentage`), discount amount, currency, start/end time, active flag, global/per-user redemption limits, minimum order value, eligible product/package IDs, and stackability flag.

Rules:

- Validate date window, activation, currency, product eligibility, minimum spend, and remaining global/per-user limit on the server.
- Percentage is in a documented bounded range; fixed discount cannot exceed eligible subtotal. Final payable total cannot be negative.
- Never trust a client-supplied discount or total. Recompute and store a price quote/discount breakdown on the order.
- Reserve/redeem atomically to prevent concurrent over-redemption. Release an unpaid reservation after its expiry. A completed redemption is unique per order/user/coupon.
- Coupon combination is denied unless all relevant coupons explicitly allow stacking.

## 13. Wallet Module

**Purpose:** expose an auditable monetary balance, credits/debits, permitted spending, and statement history.

Proposed endpoints:

- `GET /api/wallet` — own available balance and currency.
- `GET /api/wallet/transactions?page=1&per_page=20` — own paginated statement.
- `GET /api/wallet/transactions/{transaction}` — own transaction detail.
- `POST /api/wallet/spend` — only for explicitly wallet-payable products, with idempotency key and server-validated order.

Rules:

- Balance is a server-side ledger calculation/cached projection, never a client-settable user field.
- Ledger entries are append-only, currency-specific, and have type, signed amount in minor units, source type/ID, status, idempotency key, and creation time.
- Every debit must be atomic and cannot exceed available funds. On a race, only one competing debit may succeed.
- Credit only from a verified completed payment or explicitly configured reward. Spending cannot turn pending/held funds into available funds.
- Refunds/reversals append compensating entries and reference the original transaction.
- Never store raw payment credentials, card details, PINs, or full provider secrets in the wallet.

Logical data: `wallets` (user, currency, optional reconciled balance) and immutable `wallet_transactions` with unique source/idempotency constraints.

## 14. Subscription/Premium Module

**Purpose:** sell time-bound packages and enforce Free/Premium access on the server.

Proposed endpoints:

- `GET /api/subscription-packages` — active package catalogue.
- `GET /api/subscriptions/me` — own active/expired subscriptions and entitlements.
- `POST /api/subscriptions/checkout` with `{ "package_id": 1, "coupon_code": "..." }` — create a pending order/payment intent.
- `GET /api/tests/{test}/access` — tell the authenticated user whether the test is available and why.

Rules:

- A package has name, price/currency, duration, active status, and explicit entitlements (for example premium test IDs or all premium tests).
- A subscription becomes active only after verified payment. Store `starts_at`/`ends_at` in UTC. Expired/cancelled/refunded subscriptions do not grant access.
- Check entitlement at every protected premium test read/start/attempt endpoint, not only in the UI.
- A pending payment is not access. Failed callbacks never activate a subscription.
- Define renewal/extension, overlapping-package, cancellation, and refund rules explicitly in package policy; never silently extend validity.

Logical data: `subscription_packages`, immutable/configured `package_entitlements`, `subscriptions`, and links to paid orders.

## 15. Payment Module

**Purpose:** collect orders via bKash, Nagad, SSLCommerz, or another configured gateway and confirm payment safely.

Proposed endpoints:

- `POST /api/payments/checkout` with `{ "order_id": 1, "provider": "sslcommerz" }` — authenticate, calculate amount server-side, create a pending provider transaction, return the provider redirect/action data.
- `GET /api/payments/{payment}` — own payment/order status; never return provider secrets.
- `POST /api/payments/callback/{provider}` — provider-to-server webhook/callback; provider-authenticated and CSRF-exempt only for this route.
- `POST /api/payments/{payment}/verify` — optional authenticated server-side verification/poll for providers that support it.

Rules:

- Keep provider credentials in environment/secret storage. Do not put secrets in the browser, source code, logs, or JSON responses.
- Verify callback signature/authentication and amount, currency, merchant/order reference, and provider status. Prefer server-to-server provider verification before marking paid.
- Treat browser redirect/success pages as untrusted; they must never mark payment successful by themselves.
- Store unique internal order reference and unique provider transaction reference. Process duplicate callbacks idempotently.
- Apply state transitions only once: `pending -> paid|failed|cancelled`; refunds are separate verified transitions/records. Reject invalid transitions.
- Activate a subscription or issue other purchased goods atomically with confirmed payment. Never trust callback amount from the client.
- Use a provider adapter/service boundary; each gateway has its own validated request/signature protocol. Do not fake success if credentials/provider verification are unavailable.

Logical data: `orders` (subtotal, coupon discount, total, currency, user, purpose, state), `payments` (provider, internal/provider refs, exact amount/currency, status, verified/paid timestamps, sanitized metadata), and append-only `payment_events` audit trail.

## 16. Challenge Module

**Purpose:** publish daily, weekly, or one-off quiz challenges with tracked completion and results.

Proposed endpoints:

- `GET /api/challenges?period=daily|weekly` — currently available challenges.
- `GET /api/challenges/{challenge}` — safe challenge details/question rules.
- `POST /api/challenges/{challenge}/attempts` — start an eligible challenge.
- Reuse the Exam Module answer-save, resume, and submit contract for challenge attempts.
- `GET /api/challenges/{challenge}/leaderboard` — challenge-only public standings.

Rules:

- Challenge definition includes period, UTC start/end timestamps, timezone used to compute local recurrence, test/question set, duration, attempts allowed, and reward rule.
- Server checks availability, eligibility, attempt limit, and premium entitlement before start.
- One completion/reward per allowed attempt/rule. Challenge submission is idempotent and uses the same authoritative scoring/answer-key protections as normal exams.
- Prevent joining after expiry; define what happens to an attempt whose timer crosses the challenge end before opening registration.
- Rewards use the Gamification ledger and must not be issued twice.

Logical data: `challenges`, `challenge_questions` or a versioned test reference, `challenge_attempts` linked to normal attempts, and reward ledger references.

## 17. Notification Module

**Purpose:** inform users about reminders, results, offers, and announcements across supported channels.

Proposed endpoints:

- `GET /api/notifications?page=1&per_page=20` — caller's notifications.
- `PATCH /api/notifications/{notification}/read` — mark own notification read.
- `POST /api/notifications/read-all` — mark caller's notifications read.
- `GET /api/notification-preferences` and `PATCH /api/notification-preferences` — manage permitted channels/categories.
- `POST /api/admin/announcements` — admin creates/publishes a validated announcement.

Rules:

- Persist user-specific notifications with type, safe payload, channel/status, creation/delivery/read times, and deduplication key.
- Enforce ownership on read/update; never let a user enumerate another user's notification IDs.
- Do not put passwords, OTP values, access tokens, full private profile, or payment credentials in notification content/logs.
- Respect opt-outs and category/channel preference where legally/product permitted. Critical account/security notices may follow separate documented policy.
- Queue transient delivery retries with bounded retry policy; persist terminal failures for observability. Retrying must not duplicate user-visible notifications.
- Admin announcements require moderation/publish permission and store author/audit data.

Logical data: `notifications` (or Laravel notifications table), `notification_preferences`, and `announcements` with target audience and publish window.

## 18. Progress/Analytics Module

**Purpose:** help a student identify performance by subject/topic, weakness, and change over time.

Proposed endpoints:

- `GET /api/analytics/overview?from={date}&to={date}`
- `GET /api/analytics/subjects?from={date}&to={date}`
- `GET /api/analytics/topics?subject_id={id}&from={date}&to={date}`
- `GET /api/analytics/progress?group_by=week|month`

Response metrics: completed test count, average percentage, accuracy, correct/wrong/skipped totals, completion rate, per-subject/topic attempts and accuracy, trend series, and weak-topic suggestions with sample size.

Rules:

- Scope student results to the authenticated user. Admin aggregate reporting must use a separate authorized endpoint and appropriate privacy/minimum-group safeguards.
- Calculate metrics from submitted result/answer records, with a documented date basis (prefer completion timestamp and configured timezone).
- Treat zero attempts separately from 0% performance; do not label a topic weak from an insufficient sample. Include sample counts and window.
- Apply consistent filters, scoring version, and deduplication so totals agree with Result and Exam modules.
- Analytics is derived data/cache only; source attempts/results remain authoritative.
- Validate date order/range and cap requested window/pagination to prevent expensive queries.

## 19. Admin Module

**Status: Partially implemented.** `GET /api/admin/dashboard` returns role-protected aggregate metrics; admins and editors can list/inspect test drafts, create/update drafts, assign questions, and publish tests. The admin Model Tests screen supports status/search filters, paginated test listing, question assignment, and publishing. Student user management is implemented for admins: paginated search/filter, student profile and recent exam activity, and account activation/blocking with session revocation. Subject catalogue management and question create/list/update/activation are implemented for admins and editors. Questions assigned to published tests are locked to preserve their content. The frontend console is at `/admin/dashboard`. Bulk question import, granular permissions, audit logs, and other operational workflows are still planned. See [API.md](./API.md#admin-dashboard-api).

**Purpose:** provide role-protected content and operations management, audit trails, and safe operational summaries.

Remaining proposed endpoint families (all `/api/admin/...`):

- Bulk question import/export and question-report operations — validate and audit all imported content.
- `GET /payments` and `POST /payments/{payment}/refund` — inspect verified payments and request an authorized refund; gateway execution and audit required.
- `GET|POST|PATCH /coupons` and disable operation — coupon lifecycle.
- `GET /referrals` and audited adjustment/review operations — referral investigation and resolution.
- `GET /audit-logs` — restricted, filtered, read-only audit review.

Rules:

- Every admin request must be authenticated and authorized by named permission/policy (e.g. `users.manage`, `questions.manage`, `tests.publish`, `payments.refund`, `coupons.manage`, `referrals.review`); do not assume every admin can perform every operation.
- Enforce authorization on the server for every route/object. Prevent horizontal privilege escalation and self-promotion via mass assignment.
- Validate all writes, preserve referential integrity, and record actor, action, target, timestamp, reason, and before/after safe diff in an append-only audit log.
- Require explicit reason/confirmation for destructive, payment, refund, role/status, and reward adjustment actions. Prefer archive/soft-delete for referenced content.
- Do not log passwords, OTPs, bearer tokens, secrets, full payment payloads, or unnecessary personal information.
- Paginate/filter all lists, rate-limit sensitive actions, and protect imports from oversized or malformed files.
- Seed development admin access only through a documented local-only mechanism; never ship a shared production password.

Logical data: roles/permissions or an equivalent policy model, `audit_logs`, and module-owned content/order tables. Existing `users.role` alone is not a full permission system.

## 20. Settings Module

**Purpose:** centrally configure product-wide, exam, scoring, reward, notification, and payment behaviour with validation and audit.

Proposed endpoints:

- `GET /api/settings/public` — only explicitly public settings required by the client (branding, support contact, feature flags, public exam rules).
- `GET /api/admin/settings` — authorized operational settings without secrets.
- `PATCH /api/admin/settings` — validate and update allowed settings with audit.

Settings groups:

- Site: display name, logo/public links, support contact, default timezone/locale, maintenance notice.
- Exam: allowed durations/question counts, attempt rules, answer navigation policy, autosave configuration, result-release policy.
- Scoring: default total marks, passing threshold/scale, negative-marking enabled flag and non-negative penalty amount; per-test snapshot overrides take precedence.
- Leaderboard/rewards: eligible activities, period/timezone, tie-break rule, point/XP amounts and thresholds.
- Commerce: supported currencies/providers, package/coupon policies, payment/refund controls; credentials remain in secret storage, never the ordinary settings table/API.

Rules:

- Every setting has a stable key, type, validation constraints, description, default, and sensitivity classification. Avoid opaque untyped JSON where a typed field is practical.
- Public clients receive an allowlist only. Never expose environment variables, provider secrets, signing keys, database credentials, or private operational settings.
- Validate cross-field invariants together. Example: passing score and score scale must agree; negative penalty must be non-negative; duration must be positive.
- Snapshot settings that affect a published test into that test/version so an admin settings change cannot alter an in-progress or historical result.
- Cache reads safely and invalidate on successful update. Updates record actor, time, changed keys, and audit reason.
- Restrict writes to the appropriate settings permission and validate HTTP method/input size.

Logical data: typed `settings` records (key, value, type, group, public flag, description, updated_by, timestamps) and audit entries. Keep secret values in deployment secret storage.

## Cross-module implementation checklist

Before calling any planned module complete:

1. Add the migrations, indexes, foreign keys, unique/idempotency constraints, models, and policies for its data.
2. Implement and register documented routes; validate access control, ownership, input, and state transitions.
3. Add feature tests for happy paths, invalid input, unauthenticated/unauthorized access, cross-user access, retries/duplicate callbacks, boundary times, and rollback/error paths.
4. Keep contracts synchronized across API docs, database docs, frontend types, and implementation. Mark a module implemented only after the route registry and tests prove it exists.
5. Do not claim external email, storage, notification, payment, or leaderboard integrations work until their real configuration and end-to-end verification are complete.
