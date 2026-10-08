# NurseExam247 Development Instructions

## Project and source of truth

- This is a Laravel 12 JSON API (`backend/`) with a React/Vite frontend (`frontend/`), primarily for nursing exam preparation. Follow existing architecture and naming rather than introducing another stack or unnecessary abstractions.
- Before choosing work, read `docs/MODULES.md` for product rules and implementation status, `docs/API.md` for endpoint contracts, and the relevant README/code/tests. Treat an item as implemented only when the route, backend, frontend where applicable, tests, and documentation support that claim.
- Work one module at a time. When the user says “next”, finish the next appropriate incomplete module from `docs/MODULES.md`; do not skip ahead while the current feature remains unverified or uncommitted.

## Feature development pattern

1. Inspect `git status` and recent history first. Preserve existing user changes; never revert unrelated work. Trace existing controllers, services, routes, migrations, UI, and tests before editing.
2. Confirm business rules and edge cases from the module specification. Ask one concise clarification when a decision materially changes behavior; otherwise use documented defaults and state consequential assumptions.
3. Implement the complete user-facing vertical slice where applicable: schema/migration, server-side domain logic, validated API routes, authorization/ownership checks, frontend experience, tests, and directly related docs. Keep admin mutations server-authorized; hiding a control is not access control.
4. Put domain logic in the existing service/controller patterns. Validate input at the API boundary; compute scores, permissions, rewards, and balances on the server. Use database transactions for related writes and unique constraints/idempotency keys for retryable operations. Keep audit/reward ledgers append-only; use compensating entries for reversals.
5. Keep the UI consistent with the existing React dashboard, admin sidebar, API helper, and styles. Show loading, empty, and error states; do not show sample/fabricated operational data or imply a planned feature works.
6. Add focused regression tests for success, validation, authorization/ownership, retry/idempotency, and relevant boundaries. Update API, module, database, and frontend/backend README docs as applicable.
7. Run the smallest meaningful checks, then inspect the final diff and `git diff --check`. Report what passed and any known limitation.

## Domain and API invariants

- Prefix API routes with `/api`; follow the existing JSON response and Laravel validation-error conventions.
- Protected data belongs to the authenticated user unless an explicit server-side admin authorization rule says otherwise. Never trust client-provided role, score, answer key, price, reward, or balance.
- Store timestamps in UTC and return ISO 8601. Use `Asia/Dhaka` where the product specification defines calendar periods.
- Preserve published test and answer snapshots. Do not expose correct answers before submission or another user's private profile, attempts, or results.
- Keep points/rewards separate from wallet money and XP. Apply module-specific eligibility, opt-in, and idempotency rules defined in `docs/MODULES.md`.
- Make migrations additive and reversible when practical. Never modify or delete existing user data to simplify a feature.

## Validation commands (Windows / XAMPP)

Run from the relevant directory; PHP may not be on `PATH`:

```powershell
Set-Location E:\nurseexam247\backend
& 'C:\xampp\php\php.exe' artisan test --filter=RelevantFeatureTest
& 'C:\xampp\php\php.exe' vendor\bin\pint --test

Set-Location E:\nurseexam247\frontend
npm run lint
npm run build
```

Apply local migrations only when they are part of the requested implementation:

```powershell
Set-Location E:\nurseexam247\backend
& 'C:\xampp\php\php.exe' artisan migrate
```

Existing unrelated baseline failures are not a reason to change unrelated code; identify them clearly and run focused tests for the changed behavior.

## Delivery and Git

- This project follows one finished module per delivery. The user has asked that completed work be committed before proceeding to the next module; after implementation and verification, create a Conventional Commit and confirm the working tree state. Do not push unless asked.
- Include the required trailer on commits:

  `Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>`

- Do not commit unfinished, failing, or unrelated changes. Never amend or discard existing work unless the user explicitly requests it.
