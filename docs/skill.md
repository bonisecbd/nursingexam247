# Project Skills & Competency Matrix

## 1. Overview

This document defines the **skills, competencies, and expertise requirements** for the Nursing Job Preparation MCQ Model Test Software project. It serves as a reference for role assignment, recruitment, and team skill-gap analysis.

The project requires a **full-stack Laravel team** with expertise in PHP, MySQL, JavaScript, and nursing domain knowledge. Each role has specific required and preferred skills.

---

## 2. Role-Based Skill Matrix

### 2.1 Laravel Backend Developer

| Skill Category | Required | Preferred |
|---|---|---|
| **PHP** | PHP 8.3, OOP, Namespaces, Traits | Laravel Collections, Macros, Events |
| **Laravel** | Laravel 12 or compatible, Routing, Middleware | Laravel Sanctum, Eloquent ORM, Resource Classes |
| **Database** | MySQL 8.0+ / MariaDB 10.11+, SQL queries | Index optimization, Stored procedures, Migrations |
| **API Design** | RESTful API, JSON responses, Resource endpoints | API rate limiting, OpenAPI/Swagger docs |
| **Authentication** | Laravel Sanctum, Breeze/Starter kits, Passport (optional) | JWT, Socialite, Two-factor authentication |
| **Testing** | PHPUnit, Pest, Mocking | Feature tests, Browser tests (Pest browser) |
| **Queueing** | Laravel Queue system, database/redis driver | Job batching, retry logic, failed job handling |
| **Cache** | Redis or Memcached, `Cache::` facade | Cache tags, response caching, tag invalidation |

### 2.2 Frontend JavaScript Developer

| Skill Category | Required | Preferred |
|---|---|---|
| **JavaScript** | ES6+, DOM manipulation, Fetch API | Vue 3 / React / Svelte |
| **CSS** | Flexbox, CSS Grid, responsive design | Tailwind CSS, Bootstrap 5, SCSS |
| **State Management** | Local storage, Context API, or Vuex | Redux, Pinia, Zustand |
| **API Client** | Native `fetch()`, Axios | Interceptors, request/response transforms |
| **Build Tools** | npm, Vite, Laravel Mix | Webpack, Parcel |
| **UI Libraries** | Plain HTML/CSS (per docs) | Headless UI, Radix Vue/React components |
| **Accessibility** | WCAG AA, ARIA labels, keyboard nav | a11y testing tools (axe, jest-axe) |

### 2.3 Database Administrator / Architect

| Skill Category | Required | Preferred |
|---|---|---|
| **MySQL/MariaDB** | Schema design, Normalization (3NF), Indexes | Performance tuning, Partitioning |
| **Data Modeling** | ER diagrams, Relationships (1:n, n:m) | Data seeding, Factories |
| **Optimization** | EXPLAIN queries, Slow query log | Read replicas, Caching strategies |
| **Migration Tools** | Laravel Schema, Migration best practices | Down migrations, Force attributes |

### 2.4 Administrator / Content Manager

| Skill Category | Required | Preferred |
|---|---|---|
| **User Management** | Role-based access, CRUD operations | Permission middleware, Audit logging |
| **Test Management** | Create tests, assign questions, publish/unpublish | Test unlock rules, sequence configuration |
| **Question Bank** | Add/edit MCQs, tagging, difficulty levels | Bulk import/export, CSV/JSON handling |
| **Report Generation** | Download results, subject analysis | PDF export, data visualization |

### 2.5 Nursing Domain Expert (Content)

| Skill Category | Required | Preferred |
|---|---|---|
| **Nursing Knowledge** | 15 required subjects, MCQ formulation | Bengali language proficiency, Medical terminology |
| **Content Quality** | Correct answers, explanations, difficulty balance | Curriculum alignment, Bloom's taxonomy |
| **Language** | Question text in Bengali, English terms as needed | Localization, dialect variations |

---

## 3. Cross-Functional Skills

| Skill | Description | Used By |
|---|---|---|
| **Git Version Control** | Git flow, branch strategy, PR reviews | All roles |
| **Docker / Containerization** | Laravel + MySQL + Redis stacks | Backend developers |
| **CI/CD** | GitHub Actions, GitLab CI, deployment pipelines | Team lead / DevOps |
| **API Documentation** | OpenAPI/Swagger, Postman collections | Backend developers |
| **Security Best Practices** | OWASP Top 10, CSRF, XSS, SQL injection prevention | All roles |
| **Performance Optimization** | Query profiling, Lighthouse, Core Web Vitals | Backend & Frontend |
| **Responsive Design** | Mobile-first, tablet, desktop breakpoints | Frontend developer |

---

## 4. Skill Assessment Checklist

### For New Team Members

- [ ] Can set up Laravel project locally (`composer create-project`, `php artisan serve`)
- [ ] Can create database migrations and seeders
- [ ] Can implement REST API endpoints with resource controllers
- [ ] Can authenticate users via Sanctum cookies/tokens
- [ ] Can build JavaScript fetch calls with `credentials: 'same-origin'`
- [ ] Can design ER diagrams for subject/question/test relationships
- [ ] Can calculate scores with negative marking (`+1`, `-0.25`)
- [ ] Can implement sequential test unlock logic (test 01 → 02 → 03)
- [ ] Can write PHPUnit/Pest unit and feature tests
- [ ] Can deploy to shared hosting (Apache/Nginx + PHP-FPM)

### Code Quality Standards

- [ ] PSR-12 coding standard compliance
- [ ] Meaningful commit messages (`feat:`, `fix:`, `docs:`, `style:`, `refactor:`, `test:`, `chore:`)
- [ ] `.env` never committed to version control
- [ ] All API responses validated with Form Request classes
- [ ] Error responses follow standard format (`message`, `errors`, `status`)
- [ ] TDD: At least 80% unit test coverage for scoring logic

---

## 5. Learning Resources (Onboarding)

| Resource | Purpose |
|---|---|
| `docs/SETUP.md` | Local development environment setup |
| `docs/PROJECT_BLUEPRINT.md` | Full product functionality overview |
| `docs/ARCHITECTURE.md` | System architecture and boundaries |
| `docs/API.md` | Complete API endpoint specifications |
| `docs/DATABASE.md` | Database schema and relationships |
| `docs/FRONTEND.md` | JavaScript frontend blueprint |
| `docs/CONTENT_AND_TEST_SPEC.md` | Nursing content rules and test format |
| `docs/dashboard ui.md` | Dashboard UI design specifications |

---

## 6. Competency Levels

| Level | Description | Examples |
|---|---|---|
| **Junior** | Can implement assigned tasks under supervision | Migrations, basic controllers, simple API endpoints |
| **Mid-level** | Can design and implement entire features | Authentication, test engine, scoring service |
| **Senior** | Can architect solutions, mentor others | System design, performance optimization, team leadership |
| **Principal** | Can solve complex cross-domain problems | Scalability, architecture migration, tech strategy |

---

## 7. Required Tool Proficiency

| Tool | Minimum Version | Purpose |
|---|---|---|
| PHP | 8.3+ | Backend runtime |
| Composer | 2.6+ | Dependency management |
| Node.js | 20+ | Frontend build tools |
| npm | 10+ | Package manager |
| MySQL / MariaDB | 8.0 / 10.11+ | Database server |
| Git | 2.40+ | Version control |
| PHPUnit / Pest | 11+ | Testing framework |
| Laravel | 12+ or compatible | Application framework |

---

*Document maintained as part of the Nursing Job Preparation MCQ Model Test Software project.*
*Last reviewed: [Current Date]*
*Cross-referenced with: `docs/SETUP.md`, `docs/PROJECT_BLUEPRINT.md`, `docs/API.md`, `docs/ARCHITECTURE.md`*