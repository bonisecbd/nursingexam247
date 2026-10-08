# NurseExam247 frontend

React and Vite frontend for the NurseExam247 Laravel API.

## Run locally

1. Start Laravel from `backend`:

   ```powershell
   C:\xampp\php\php.exe artisan serve
   ```

2. Start the frontend from `frontend`:

   ```powershell
   npm install
   npm run dev
   ```

Vite proxies `/api` requests to `http://127.0.0.1:8000` by default. Set `VITE_API_PROXY_TARGET` in the shell environment before starting Vite if Laravel is running elsewhere.

For a deployed frontend, set `VITE_API_BASE_URL` to the API base URL ending in `/api` at build time. When unset, the app uses the same-origin `/api` path.

## Accounts and roles

The registration form creates student accounts, matching the API's server-enforced role. Users cannot promote themselves through the frontend; admins and editors must be assigned by the site team. Login responses provide the user's actual role, which is shown in the signed-in study dashboard.

The frontend supports login, registration, logout, OTP password reset, profile editing (name, phone, date of birth, gender, and address), profile-photo upload, and loading published subjects and tests for signed-in users.

### Local demo login

The backend database seeder creates this development-only account:

| Email | Password | Role |
| --- | --- | --- |
| `test@example.com` | `password` | Student |
| `admin@nurseexam247.test` | `Admin@12345` | Admin |

To create the account and sample subjects in a local database, run this from `backend`:

```powershell
C:\xampp\php\php.exe artisan db:seed
```

These known passwords are only for local development. Never use them in production or expose the seeded demo accounts on a public deployment. Register a personal account for normal use.

The demo admin account is created only when it does not already exist, so running the seeder again will not change an existing account's password or role.

### Admin dashboard

Admins and editors are sent to `/admin/dashboard` after login. Opening that URL directly checks the saved API session and role; unauthenticated users are asked to sign in, while non-staff accounts are denied access.

The admin console includes live student/question/test/exam metrics, a seven-day exam-activity chart, catalogue summaries, and a collapsible management sidebar. Dashboard metrics are served by `GET /api/admin/dashboard` for admins and editors. The Users section is restricted to admins and supports student search, status filters, profile details, exam activity, and account activation/blocking; blocking revokes active sessions. Admins and editors can search, create, edit, and activate/deactivate subjects in the Subjects section; subjects cannot be deleted because questions and tests may reference them. The Question Bank supports subject/status/difficulty filters, paginated question listing, MCQ authoring/editing, and activation; questions assigned to published tests are locked to preserve published content. Model Tests supports a paginated draft/published directory, filtering, assigning active questions from the matching subject, and publishing drafts after server-side validation. Exams supports live, completed, and all-attempt views with safe progress/result summaries. Results & Analytics includes a paginated finalized-results directory and aggregate subject/test performance. Student-level trend reports, suspicious-attempt detection, bulk import, revenue, and other planned modules remain unavailable until implemented.

Run checks with `npm run lint` and `npm run build`.
