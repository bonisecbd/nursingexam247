# Dashboard UI Blueprint — Enterprise Grade

## 1. Overview

This document defines the **Enterprise Grade Dashboard User Interface** for the Nursing Job Preparation MCQ Model Test Software. It covers both **Administrator** and **Student/User** dashboards, ensuring consistent design, accessibility, and operational clarity.

The dashboards follow a **clean, professional layout** with responsive design, dark/light theme support, and enterprise-grade security indicators. All UI components are built to work with the Laravel REST API and the JavaScript frontend described in `FRONTEND.md`.

---

## 2. Admin Dashboard UI

### 2.1 Layout Structure

```text
+------------------------------------------------------+
| Header: Logo | Notifications | User Profile Dropdown |
+------------------------------------------------------+
| Sidebar: Navigation Links (Collapsible)             |
+------------------------------------------------------+
| Main Content Area: Grid of Widgets / Pages          |
+------------------------------------------------------+
| Footer: Version | Support | Documentation Links     |
+------------------------------------------------------+
```

### 2.2 Sidebar Navigation (Collapsible)

| Icon / Label | Route | Description |
|---|---|---|
| 📊 Dashboard | `/admin` | Overview statistics and quick actions |
| 👥 Users | `/admin/users` | Manage student and administrator accounts |
| 📚 Subjects | `/admin/subjects` | Add / edit / deactivate nursing subjects |
| 🗂️ Topics | `/admin/topics` | Manage topics per subject |
| ❓ Questions | `/admin/questions` | Question bank management |
| 📝 Tests | `/admin/tests` | Create, edit, publish, unlock tests |
| 📈 Analytics | `/admin/analytics` | Comprehensive reports and charts |
| ⚙️ Settings | `/admin/settings` | System configuration |

### 2.3 Dashboard Widgets (Home Page)

| Widget | Description | Data Source |
|---|---|---|
| **Total Students** | Count of registered users | `GET /api/users?role=student` |
| **Total Tests Published** | Number of active tests | `GET /api/tests?status=published` |
| **Total Attempts** | Total test submissions | `GET /api/attempts` |
| **Average Score** | Mean percentage across all attempts | `GET /api/analytics/overview` |
| **Recent Activity** | Last 5 test submissions | `GET /api/attempts?limit=5` |
| **Low Performer Alert** | Students < 50% pass rate | Custom query |

### 2.4 User Management Table

| Column | Filter | Action |
|---|---|---|
| Name | Text search | View / Edit / Delete |
| Email | Text search | Resend verification |
| Role | Dropdown (student / editor / admin) | Toggle role |
| Status | Active / Inactive | Toggle status |
| Last Login | Timestamp | View profile |
| Actions | — | Delete user |

### 2.5 Test Creation Form (Enterprise Grade)

- **Stepper navigation** with progress indicator (1–10 steps)
- **Live validation** on each field (real-time API checks)
- **Preview mode** before publishing
- **Undo/Redo** history for form changes
- **Auto-save** every 30 seconds to prevent data loss
- **Keyboard shortcuts** (Ctrl+S, Esc to close, Tab navigation)
- **Drag-and-drop question reordering** with sequence numbers

### 2.6 Security & Compliance

- **CSRF tokens** auto-injected on all forms
- **Role-based UI** — admin-only routes hidden from students
- **Confirm modals** for destructive actions (Delete, Deactivate)
- **Session timeout** warning (5 minutes before auto-logout)
- **Audit log** entry on every significant action (create, update, delete)

---

## 3. User / Student Dashboard UI

### 3.1 Layout Structure

```text
+------------------------------------------------------+
| Header: App Logo | Notifications | User Avatar / Name |
+------------------------------------------------------+
| Sidebar: Navigation (Condensed for students)        |
+------------------------------------------------------+
| Main Content: Available Tests / Recently Completed   |
+------------------------------------------------------+
| Footer: Version | Help | Logout                         |
+------------------------------------------------------+
```

### 3.2 Student Navigation

| Icon / Label | Route | Description |
|---|---|---|
| 🏠 Dashboard | `/` | Overview of progress and unlocked tests |
| 📚 Tests | `/tests` | List of unlocked model tests |
| 📊 My Results | `/my-attempts` | History of all attempts |
| 📈 My Analysis | `/my-analytics` | Subject/topic wise performance |
| ⚙️ Profile | `/profile` | Edit personal information |
| ❓ Help | `/help` | FAQ and test rules |

### 3.3 Dashboard (Home) — Student View

| Section | Content |
|---|---|
| **Welcome** | `"Assalamu Alaikum, [Name]!"` with role-based greeting |
| **Unlocked Tests** | Cards showing test code, subject, duration, "Start Test" button |
| **Progress Bar** | Overall completion percentage across all tests |
| **Quick Start** | "Start Test 01" — primary CTA button |
| **Recent Attempts** | Last 3 attempts with scores (click to view details) |
| **Next Test Unlock** | Indicator: "Test 02 unlocks after Test 01 completion" |

### 3.4 Test Screen UI (During Attempt)

| Component | Description |
|---|---|
| **Header** | Test title, subject, timer (countdown: MM:SS), progress (Q1 of 100) |
| **Question Area** | Question text, 4 option radio buttons, "Flag for review" toggle |
| **Navigation** | Previous →, Next → buttons, "Save Answer" auto-save indicator |
| **Footer** | Negative marking notice, time warning (60s / 30s remaining), Submit |
| **State** | Selected answer saved locally & synced to API every 10 seconds |

### 3.5 Result Screen UI (After Submission)

| Section | Content |
|---|---|
| **Score Ring** | Large circular progress bar showing percentage |
| **Summary Cards** | Correct / Incorrect / Unanswered counts with colors |
| **Subject Analysis** | Bar chart: score per subject (15 subjects) |
| **Topic Analysis** | Heatmap: weak topics needing review |
| **Question Review** | List of all questions with student answer, correct answer, explanation |
| **Actions** | Download PDF report, Retry test, Share results |

### 3.6 Profile Page

| Section | Fields |
|---|---|
| **Personal Information** | Name, Email, Profile picture upload, Role (read-only for students) |
| **Account Security** | Password change form, Last password change timestamp, Login history (last 5 IPs) |
| **Notification Preferences** | Email alerts for test unlock, result ready, system announcements |
| **Data Export** | Button to download all attempt reports as ZIP |

### 3.7 Accessibility (Enterprise Grade)

- **WCAG AA** contrast ratios for all text/background combinations
- **Keyboard navigation** — full UI operable without mouse (Tab, Shift+Tab, Enter, Space)
- **ARIA labels** on all interactive elements, modals, and live regions
- **Screen reader friendly** — logical heading order, descriptive link text
- **Focus visible** — 3px outline on focused elements, not removed on `:active`
- **Responsive breakpoints** — mobile (375px), tablet (768px), desktop (1440px)

### 3.8 Dark / Light Theme

| Mode | Primary Colors | Background |
|---|---|---|
| **Light** | `#18181b` (dark cards), `#fff` (surface) | `#f8f9fa` |
| **Dark** | `#f8f9fa` (light cards), `#111` (text) | `#121212` |
| **System** | Follows OS preference via `prefers-color-scheme` meta |

---

## 4. Component Library (Shared)

| Component | Props / Attributes | Usage |
|---|---|---|
| **Card** | `title`, `subtitle`, `bordered`, `shadowed` | Wraps content sections |
| **Table** | `columns`, `data`, `selectable`, `pagination` | Data display with sorting/filtering |
| **ProgressBar** | `percentage`, `size`, `color` | Timer, score, completion |
| **Alert** | `type` (success/warning/error), `message`, `dismissible` | Feedback messages |
| **Modal** | `title`, `width`, `confirmText`, `cancelText` | Confirm actions, forms |
| **Badge** | `text`, `variant` (primary/secondary/warning/danger) | Status labels (Active, Locked, Published) |
| **Avatar** | `name`, `initials`, `size`, `fallbackColor` | User profile headers |

---

## 5. API Integration Standards

All dashboard UI components must follow these integration rules:

| Rule | Description |
|---|---|
| **Credentials** | `credentials: 'same-origin'` on all fetch requests |
| **Error Handling** | Show `toast` error if `response.status >= 400` |
| **Loading State** | Show `spinner` while `fetch` pending, disable button |
| **Success Toast** | Auto-dismiss after 3 seconds on successful API call |
| **CSRF Token** | Meta tag `<meta name="csrf-token" content="...">` read by axios/fetch |
| **Pagination** | `page` and `per_page` query params, consistent page size (10/20/50) |
| **Search Debounce** | 300ms delay on input keyup before API request |

---

## 6. Visual Design Tokens

| Token | Value (Light) | Value (Dark) |
|---|---|---|
| `--color-primary` | `#3b82f6` (blue-500) | `#60a5fa` (blue-400) |
| `--color-primary-hover` | `#2563eb` | `#93c5fd` |
| `--color-success` | `#10b981` | `#34d399` |
| `--color-warning` | `#f59e0b` | `#fbbf24` |
| `--color-danger` | `#ef4444` | `#f87171` |
| `--color-background` | `#f8f9fa` | `#121212` |
| `--color-surface` | `#fff` | `#1e1e1e` |
| `--color-text` | `#111111` | `#fafafa` |
| `--border-radius` | `8px` | `8px` |
| `--box-shadow` | `0 1px 2px rgba(0,0,0,0.05)` | `0 1px 2px rgba(0,0,0,0.3)` |

---

## 7. Enterprise Checklist

- [ ] All API endpoints protected with `auth:sanctum` or bearer token
- [ ] Role-based access control (RBAC) enforced on UI routes
- [ ] CSRF protection implemented on all forms
- [ ] Input validation on client + server (Laravel Request classes)
- [ ] Audit logs written for admin actions (create / update / delete)
- [ ] Session management with 15-min timeout + warning modal
- [ ] Data export (CSV/PDF) encrypted and signed
- [ ] SEO metadata on all public pages (OG tags, meta description)
- [ ] Sitemap generation for admin routes
- [ ] Performance budget: dashboard loads < 2s on 3G

---
*File generated in accordance with the Nursing Job Preparation Software documentation standards.*
*Refer to: `PROJECT_BLUEPRINT.md`, `API.md`, `FRONTEND.md`, `ARCHITECTURE.md`*