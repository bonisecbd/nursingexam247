/* ============================================================
   NurseExam247 — Views (Render HTML for each page)
   ============================================================ */
const Views = (() => {

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // Options may arrive as a JSON string or an array; never let a bad payload break rendering.
    function parseOptions(opts) {
        if (typeof opts === 'string') {
            try { opts = JSON.parse(opts); } catch (_) { return []; }
        }
        return Array.isArray(opts) ? opts : [];
    }

    function num(v, fallback = 0) {
        const n = Number(v);
        return Number.isFinite(n) ? n : fallback;
    }

    function header(active) {
        const u = API.user();
        const initial = u ? u.name.charAt(0).toUpperCase() : '?';
        return `
        <header class="header">
            <div class="container">
                <a href="/dashboard" class="logo"><span class="icon">⚕</span> NurseExam247</a>
                <div class="nav-links">
                    <a href="/dashboard" class="${active === 'dashboard' ? 'active' : ''}">Dashboard</a>
                    <a href="/tests" class="${active === 'tests' ? 'active' : ''}">Tests</a>
                    <a href="/history" class="${active === 'history' ? 'active' : ''}">History</a>
                    <a href="/leaderboard" class="${active === 'leaderboard' ? 'active' : ''}">Leaderboard</a>
                </div>
                <div class="nav-user" onclick="navigate('/profile')">
                    <div class="avatar">${initial}</div>
                    <span style="font-weight:600;font-size:.9rem">${esc(u ? u.name : '')}</span>
                </div>
            </div>
        </header>`;
    }

    function sidebar(active) {
        return `
        <aside class="sidebar">
            <div class="section-title">Main</div>
            <a href="/dashboard" class="${active === 'dashboard' ? 'active' : ''}">📊 Dashboard</a>
            <a href="/tests" class="${active === 'tests' ? 'active' : ''}">📝 Model Tests</a>
            <a href="/history" class="${active === 'history' ? 'active' : ''}">📜 Attempt History</a>
            <a href="/leaderboard" class="${active === 'leaderboard' ? 'active' : ''}">🏆 Leaderboard</a>
            <div class="section-title">Account</div>
            <a href="/profile" class="${active === 'profile' ? 'active' : ''}">👤 Profile</a>
            <a href="javascript:void(0)" onclick="App.logout()">🚪 Logout</a>
        </aside>`;
    }

    /* ---------- LOGIN ---------- */
    function login() {
        return `
        <div class="auth-page">
            <div class="auth-card">
                <div class="auth-logo"><span class="icon" style="width:40px;height:40px;background:var(--primary);color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem">⚕</span> NurseExam247</div>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Login to continue your preparation</p>
                <form id="login-form" autocomplete="on">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                        <div class="form-error" id="email-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                        <div class="form-error" id="password-error"></div>
                    </div>
                    <div class="form-error" id="form-error" style="text-align:center;margin-bottom:12px"></div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">Login</button>
                </form>
                <div class="auth-divider">OR</div>
                <a href="/forgot-password" class="btn btn-outline btn-block">Forgot Password?</a>
                <p class="auth-footer">Don't have an account? <a href="/register"><strong>Register</strong></a></p>
            </div>
        </div>`;
    }

    /* ---------- REGISTER ---------- */
    function register() {
        return `
        <div class="auth-page">
            <div class="auth-card">
                <div class="auth-logo"><span class="icon" style="width:40px;height:40px;background:var(--primary);color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem">⚕</span> NurseExam247</div>
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-subtitle">Start your nursing exam preparation today</p>
                <form id="register-form" autocomplete="on">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" class="form-control" placeholder="Your full name" required autofocus>
                        <div class="form-error" id="name-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-control" placeholder="you@example.com" required>
                        <div class="form-error" id="email-error"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" class="form-control" placeholder="Min 8 characters" required>
                            <div class="form-error" id="password-error"></div>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm Password</label>
                            <input type="password" id="password_confirmation" class="form-control" placeholder="Repeat password" required>
                            <div class="form-error" id="password_confirmation-error"></div>
                        </div>
                    </div>
                    <div class="form-error" id="form-error" style="text-align:center;margin-bottom:12px"></div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="register-btn">Create Account</button>
                </form>
                <p class="auth-footer">Already have an account? <a href="/login"><strong>Login</strong></a></p>
            </div>
        </div>`;
    }

    /* ---------- FORGOT PASSWORD ---------- */
    function forgot() {
        return `
        <div class="auth-page">
            <div class="auth-card">
                <div class="auth-logo"><span class="icon" style="width:40px;height:40px;background:var(--primary);color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem">⚕</span></div>
                <h1 class="auth-title">Reset Password</h1>
                <p class="auth-subtitle" id="forgot-sub">Enter your email to receive a reset code</p>
                <form id="forgot-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-control" placeholder="you@example.com" required autofocus>
                    </div>
                    <div class="form-group hidden" id="otp-group">
                        <label for="otp">Reset Code (6 digits)</label>
                        <input type="text" id="otp" class="form-control" placeholder="123456" maxlength="6" pattern="[0-9]{6}">
                    </div>
                    <div class="form-group hidden" id="new-pass-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" class="form-control" placeholder="Min 8 characters">
                        <div class="form-hint">Must contain uppercase, lowercase, number, and symbol</div>
                    </div>
                    <div class="form-group hidden" id="confirm-pass-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <input type="password" id="password_confirmation" class="form-control" placeholder="Repeat password">
                    </div>
                    <div class="form-error" id="form-error" style="text-align:center;margin-bottom:12px"></div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="forgot-btn">Send Reset Code</button>
                </form>
                <p class="auth-footer"><a href="/login">← Back to Login</a></p>
            </div>
        </div>`;
    }

    /* ---------- DASHBOARD ---------- */
    function dashboard(data) {
        const { overview, tests } = data;
        const user = data.user || {};
        const stats = overview || {};
        const pct = (v) => (v != null && v !== '' ? Number(v).toFixed(1) + '%' : '—');
        const num = (v) => (v != null && v !== '' ? v : '—');
        return `
        ${header('dashboard')}
        <div class="layout">
            ${sidebar('dashboard')}
            <main class="main">
                <div class="flex-between mb-3">
                    <div>
                        <h1 style="font-size:1.6rem;font-weight:800">Welcome, ${esc(user.name)}! 👋</h1>
                        <p class="text-muted">Ready for today's practice?</p>
                    </div>
                    <a href="/tests" class="btn btn-primary">📝 Start New Test</a>
                </div>

                <div class="grid-4 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:var(--primary-light);color:var(--primary)">📝</div>
                        <div class="stat-info">
                            <div class="value">${num(stats.total_tests)}</div>
                            <div class="label">Total Tests</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:var(--success-light);color:var(--success)">✅</div>
                        <div class="stat-info">
                            <div class="value">${num(stats.test_count)}</div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:var(--warning-light);color:var(--warning)">📊</div>
                        <div class="stat-info">
                            <div class="value">${stats.average_score != null ? pct(stats.average_score) : '—'}</div>
                            <div class="label">Avg Score</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:var(--info-light);color:var(--info)">🎯</div>
                        <div class="stat-info">
                            <div class="value">${stats.accuracy_rate != null ? pct(stats.accuracy_rate) : '—'}</div>
                            <div class="label">Accuracy</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">📝 Available Model Tests</span>
                        <a href="/tests" class="btn btn-ghost btn-sm">View All →</a>
                    </div>
                    ${(tests && tests.length) ? tests.map(t => testCard(t)).join('') : `
                        <div class="empty-state">
                            <div class="icon">📝</div>
                            <h3>No tests available yet</h3>
                            <p>Tests will appear here when published</p>
                        </div>`}
                </div>
            </main>
        </div>`;
    }

    function subjectName(t) {
        const s = t.subject;
        if (!s) return '';
        return typeof s === 'object' ? (s.name || '') : String(s);
    }

    function testCard(t) {
        const locked = t.is_locked;
        const subject = subjectName(t);
        return `
        <div class="flex-between mb-2" style="padding:16px;border:1px solid var(--border);border-radius:var(--radius)">
            <div>
                <div style="font-weight:700;margin-bottom:4px">${esc(t.title)}</div>
                <div class="text-sm text-muted">
                    📋 ${t.question_count} Qs &nbsp;|&nbsp; ⏱ ${t.duration_minutes} min &nbsp;|&nbsp; 🎯 ${t.total_marks} marks
                    ${subject ? ' &nbsp;|&nbsp; 📚 ' + esc(subject) : ''}
                </div>
            </div>
            <div class="flex gap-1">
                ${locked ? `<span class="badge badge-warning">🔒 Locked</span>` : ''}
                ${t.status === 'published' && !locked ? `
                <button class="btn btn-primary btn-sm" onclick="App.startTest(${t.id})">Start Test</button>` : ''}
            </div>
        </div>`;
    }

    /* ---------- TESTS LIST ---------- */
    function testsList(data) {
        const { tests, subjects } = data;
        return `
        ${header('tests')}
        <div class="layout">
            ${sidebar('tests')}
            <main class="main">
                <div class="flex-between mb-3">
                    <div>
                        <h1 style="font-size:1.6rem;font-weight:800">📝 Model Tests</h1>
                        <p class="text-muted">Choose a test to begin</p>
                    </div>
                    <div class="flex gap-1">
                        <select id="filter-subject" class="form-control" style="width:auto" onchange="App.filterTests()">
                            <option value="">All Subjects</option>
                            ${(subjects || []).map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('')}
                        </select>
                        <input type="text" id="filter-search" class="form-control" placeholder="Search tests..." style="width:200px" oninput="App.filterTests()">
                    </div>
                </div>

                <div id="tests-list">
                    ${(tests && tests.length) ? tests.map(t => `
                        <div class="card card-hover mb-2" data-subject="${t.subject_id || ''}" data-title="${esc(t.title.toLowerCase())}">
                            <div class="flex-between">
                                <div>
                                    <div class="flex gap-1 mb-1" style="align-items:center">
                                        <span class="badge badge-primary">${esc(t.code || 'TEST')}</span>
                                        <span class="badge badge-success">${t.status}</span>
                                        ${t.is_premium ? '<span class="badge badge-warning">⭐ Premium</span>' : ''}
                                    </div>
                                    <h3 style="font-size:1.1rem">${esc(t.title)}</h3>
                                    ${t.description ? `<p class="text-sm text-muted mt-1">${esc(t.description)}</p>` : ''}
                                    <div class="flex gap-2 mt-1 text-sm text-muted">
                                        <span>📋 ${t.question_count} Questions</span>
                                        <span>⏱ ${t.duration_minutes} min</span>
                                        <span>🎯 ${t.total_marks} marks</span>
                                        <span>✅ Pass: ${num(t.passing_score)}/${num(t.total_marks)}</span>
                                        ${t.is_negative_marking_enabled ? '<span style="color:var(--danger)">−0.25 negative</span>' : ''}
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button class="btn btn-primary" onclick="App.startTest(${t.id})" ${t.is_locked ? 'disabled' : ''}>
                                        ${t.is_locked ? '🔒 Locked' : '▶ Start Test'}
                                    </button>
                                    ${t.is_locked && t.locked_reason ? `<div class="text-sm text-muted mt-1" style="max-width:160px">${esc(t.locked_reason)}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `).join('') : `
                        <div class="card empty-state">
                            <div class="icon">📝</div>
                            <h3>No tests found</h3>
                            <p>Published tests will appear here</p>
                        </div>`}
                </div>
            </main>
        </div>`;
    }

    /* ---------- EXAM ---------- */
    function examHeader(test) {
        return `
        <div class="exam-header">
            <div class="exam-info">
                <span class="exam-title">${esc(test.title)}</span>
                <span class="badge badge-primary">${test.question_count} Qs</span>
                <span class="exam-progress-text" id="progress-text">Q 1 of ${test.question_count}</span>
                <span id="save-indicator" style="font-size:.82rem;color:var(--success);font-weight:600"></span>
            </div>
            <div class="flex gap-2" style="align-items:center">
                <div class="exam-timer" id="exam-timer">--:--</div>
                <button class="btn btn-danger btn-sm" onclick="App.confirmSubmit()">Submit Test</button>
            </div>
        </div>`;
    }

    function examBody(test, attempt) {
        return `
        ${examHeader(test)}
        <div class="exam-body">
            <aside class="question-nav">
                <div class="question-nav-title">Question Palette</div>
                <div class="question-grid" id="question-grid"></div>
                <div class="mt-2 text-sm" style="display:flex;flex-direction:column;gap:6px">
                    <span><span style="display:inline-block;width:12px;height:12px;background:var(--success);border-radius:3px"></span> Answered</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:var(--surface);border:2px solid var(--border);border-radius:3px"></span> Not Answered</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:var(--primary);border-radius:3px"></span> Current</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:var(--warning);border-radius:3px"></span> Flagged</span>
                </div>
            </aside>
            <div class="exam-question-area" id="question-area"></div>
        </div>`;
    }

    function questionView(q, answer, flags, index, total) {
        const opts = parseOptions(q.options);
        const keys = ['A', 'B', 'C', 'D', 'E', 'F'];
        return `
        <div class="question-card">
            <div class="question-meta">
                <div class="flex gap-1">
                    <span class="badge badge-primary">Question ${index + 1}/${total}</span>
                    ${num(q.points, 0) > 0 ? `<span class="badge badge-info">${q.points} mark${num(q.points, 1) === 1 ? '' : 's'}</span>` : ''}
                </div>
                <button class="btn btn-sm ${flags ? 'btn-warning' : 'btn-outline'}" onclick="App.toggleFlag(${q.id})">
                    ${flags ? '🚩 Flagged' : '🏳 Flag'}
                </button>
            </div>
            <div class="question-text">${esc(q.question_text)}</div>
            <div class="question-options">
                ${(opts || []).map((opt, i) => {
                    const idx = i + 1;
                    const selected = answer === idx;
                    return `
                    <label class="option-label ${selected ? 'selected' : ''}" onclick="App.selectOption(${q.id}, ${idx})">
                        <input type="radio" name="option" value="${idx}" ${selected ? 'checked' : ''}>
                        <span class="option-key">${keys[i] || (i + 1)}</span>
                        <span>${esc(opt)}</span>
                    </label>`;
                }).join('')}
            </div>
        </div>
        <div class="exam-actions">
            <button class="btn btn-outline" onclick="App.prevQuestion()" ${index === 0 ? 'disabled' : ''}>← Previous</button>
            <div class="flex gap-1">
                <button class="btn btn-outline" onclick="App.clearAnswer(${q.id})">Clear</button>
                ${index < total - 1
                    ? `<button class="btn btn-primary" onclick="App.nextQuestion()">Next →</button>`
                    : `<button class="btn btn-success" onclick="App.confirmSubmit()">Submit Test</button>`}
            </div>
        </div>`;
    }

    /* ---------- RESULT ---------- */
    function result(payload) {
        // Live API wraps the summary as { result: {...} }; older shapes used { attempt } or a flat body.
        const r = payload.result || payload.attempt || payload;
        const test = r.test || payload.test || {};
        const attemptId = r.attempt_id || payload.attempt_id || r.id;
        const pctRaw = num(r.percentage, 0);
        const pct = Math.min(100, Math.max(0, pctRaw));
        const passing = num(test.passing_score, 50);
        const totalMarks = num(r.total_marks, 0);
        const passed = r.passed ?? r.is_passed ?? (pctRaw >= (totalMarks > 0 ? (passing / totalMarks) * 100 : passing));
        const circumference = 2 * Math.PI * 70;
        const offset = circumference - (pct / 100) * circumference;
        const ringColor = passed ? 'var(--success)' : 'var(--danger)';
        const correct = num(r.correct_count, 0);
        const wrong = num(r.wrong_count ?? r.incorrect_count, 0);
        const skipped = num(r.skipped_count ?? r.unanswered_count, 0);
        const duration = r.duration_seconds != null ? Math.round(num(r.duration_seconds, 0)) : null;

        return `
        ${header('')}
        <div class="layout">
            ${sidebar('')}
            <main class="main">
                <div class="card mb-3">
                    <div class="result-header">
                        <h1 style="font-size:1.8rem;font-weight:800;margin-bottom:6px">Test Result</h1>
                        <p class="text-muted mb-2">${esc(r.test_title || test.title || '')}</p>
                        <div class="flex gap-1" style="justify-content:center;flex-wrap:wrap">
                            <span class="pass-badge ${passed ? 'pass' : 'fail'}">
                                ${passed ? '✅ PASS' : '❌ FAIL'}
                            </span>
                            ${r.status ? `<span class="badge badge-${r.status === 'submitted' ? 'success' : 'warning'}">${esc(r.status)}</span>` : ''}
                            ${duration != null ? `<span class="badge badge-muted">⏱ ${Math.floor(duration / 60)}m ${duration % 60}s</span>` : ''}
                        </div>
                    </div>

                    <div class="score-ring" style="margin:30px auto">
                        <svg width="160" height="160">
                            <circle cx="80" cy="80" r="70" fill="none" stroke="var(--border)" stroke-width="12"/>
                            <circle cx="80" cy="80" r="70" fill="none" stroke="${ringColor}" stroke-width="12"
                                stroke-dasharray="${circumference}" stroke-dashoffset="${offset}"
                                stroke-linecap="round" style="transition:stroke-dashoffset 1s ease"/>
                        </svg>
                        <div class="value">
                            <div class="pct" style="color:${ringColor}">${pct.toFixed(1)}%</div>
                            <div class="lbl">Score</div>
                        </div>
                    </div>

                    <div class="result-stats">
                        <div class="result-stat score">
                            <div class="value">${num(r.score, 0)}</div>
                            <div class="label">Score (${totalMarks} marks)</div>
                        </div>
                        <div class="result-stat correct">
                            <div class="value">${correct}</div>
                            <div class="label">✅ Correct</div>
                        </div>
                        <div class="result-stat wrong">
                            <div class="value">${wrong}</div>
                            <div class="label">❌ Wrong</div>
                        </div>
                        <div class="result-stat skipped">
                            <div class="value">${skipped}</div>
                            <div class="label">⏭ Skipped</div>
                        </div>
                    </div>

                    <div class="flex-center gap-2 mt-3">
                        ${attemptId ? `<a href="/solution/${attemptId}" class="btn btn-primary">📖 View Solutions</a>` : ''}
                        <a href="/tests" class="btn btn-outline">📝 Take Another Test</a>
                        <a href="/history" class="btn btn-ghost">📜 History</a>
                    </div>
                </div>

                ${payload.subject_scores && payload.subject_scores.length ? `
                <div class="card">
                    <div class="card-header"><span class="card-title">📚 Subject-wise Performance</span></div>
                    ${payload.subject_scores.map(s => `
                        <div class="mb-2">
                            <div class="flex-between text-sm mb-1">
                                <span>${esc(s.subject_name)}</span>
                                <span>${s.correct}/${s.total} (${s.percentage}%)</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill ${s.percentage >= 50 ? 'success' : 'danger'}" style="width:${s.percentage}%"></div>
                            </div>
                        </div>
                    `).join('')}
                </div>` : ''}
            </main>
        </div>`;
    }

    /* ---------- SOLUTION ---------- */
    function solution(data) {
        const items = Array.isArray(data.data) ? data.data : (data.solutions || []);
        const meta = data;
        const attemptId = meta.attempt_id || meta.id;
        const page = num(meta.current_page, 1);
        const lastPage = num(meta.last_page, 1);
        const hasNext = page < lastPage || !!meta.next_page_url;
        return `
        ${header('')}
        <div class="layout">
            ${sidebar('')}
            <main class="main">
                <div class="flex-between mb-3">
                    <div>
                        <h1 style="font-size:1.6rem;font-weight:800">📖 Solutions & Review</h1>
                        <p class="text-muted">Question-by-question answer review${lastPage > 1 ? ` — page ${page} of ${lastPage}` : ''}</p>
                    </div>
                    ${attemptId ? `<a href="/result/${attemptId}" class="btn btn-outline">← Back to Result</a>` : ''}
                </div>

                <div id="solutions-list">
                    ${items.length ? items.map(s => solutionItem(s)).join('') : `
                        <div class="card empty-state">
                            <div class="icon">📖</div>
                            <h3>No solutions found</h3>
                            <p>Answer reviews will appear here for submitted attempts</p>
                        </div>`}
                </div>

                <div id="solutions-more" class="flex-center mt-3" ${hasNext ? '' : 'hidden'}>
                    <button class="btn btn-primary" id="load-more-btn" onclick="App.loadMoreSolutions()">Load More</button>
                </div>
            </main>
        </div>`;
    }

    function solutionItem(s) {
        const opts = parseOptions(s.options);
        const keys = ['A', 'B', 'C', 'D', 'E', 'F'];
        const status = s.is_correct === null || s.is_correct === undefined ? 'skipped' : (s.is_correct ? 'correct' : 'wrong');
        return `
        <div class="solution-item ${status}">
            <div class="flex-between mb-1">
                <span class="badge badge-${status === 'correct' ? 'success' : status === 'wrong' ? 'danger' : 'muted'}">
                    Q${s.sequence} — ${status === 'correct' ? '✅ Correct' : status === 'wrong' ? '❌ Wrong' : '⏭ Skipped'}
                </span>
                <span class="text-sm text-muted">${s.points} mark${num(s.points, 1) === 1 ? '' : 's'}</span>
            </div>
            <div class="solution-question">${esc(s.question_text)}</div>
            <div class="solution-options">
                ${(opts || []).map((opt, i) => {
                    const idx = i + 1;
                    const isCorrect = s.correct_option === idx;
                    const isSelected = s.selected_option === idx;
                    let cls = '';
                    if (isCorrect) cls = 'is-correct';
                    else if (isSelected && !isCorrect) cls = 'is-selected-wrong';
                    return `<div class="solution-option ${cls}">
                        <strong>${keys[i] || idx}.</strong> ${esc(opt)}
                        ${isCorrect ? ' ✅' : ''}
                        ${isSelected && !isCorrect ? ' ← Your answer' : ''}
                    </div>`;
                }).join('')}
            </div>
            ${s.explanation ? `
            <div class="explanation">
                <strong>💡 Explanation:</strong> ${esc(s.explanation)}
            </div>` : ''}
        </div>`;
    }

    /* ---------- HISTORY ---------- */
    function history(data) {
        const attempts = data.data || data.attempts || [];
        return `
        ${header('history')}
        <div class="layout">
            ${sidebar('history')}
            <main class="main">
                <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:4px">📜 Attempt History</h1>
                <p class="text-muted mb-3">Your previous test attempts</p>

                ${attempts.length ? `
                <div class="card" style="padding:0">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Test</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${attempts.map(a => {
                                    const aid = a.attempt_id ?? a.id;
                                    const status = a.status || '';
                                    const badge = status === 'submitted' ? 'success'
                                        : status === 'expired' ? 'warning'
                                        : status === 'in_progress' ? 'info' : 'muted';
                                    const when = a.finished_at || a.started_at || a.created_at;
                                    const testId = (a.test && a.test.id) || a.test_id;
                                    return `
                                <tr>
                                    <td><strong>${esc(a.test_title || (a.test && a.test.title) || (testId ? 'Test #' + testId : 'Test'))}</strong></td>
                                    <td><span class="badge badge-${badge}">${esc(status)}</span></td>
                                    <td>${a.score != null ? esc(a.score) + '/' + esc(a.total_marks ?? '—') : '—'}</td>
                                    <td>${a.percentage != null ? esc(a.percentage) + '%' : '—'}</td>
                                    <td>${when ? new Date(when).toLocaleString() : '—'}</td>
                                    <td>${aid == null ? '—' : `
                                        ${status === 'submitted' || status === 'expired' ? `
                                            <a href="/result/${aid}" class="btn btn-ghost btn-sm">Result</a>
                                            <a href="/solution/${aid}" class="btn btn-ghost btn-sm">Solutions</a>
                                        ` : status === 'in_progress' ? `
                                            <button class="btn btn-primary btn-sm" onclick="App.resumeTest(${aid})">Resume</button>
                                        ` : ''}`}
                                    </td>
                                </tr>`; }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>` : `
                <div class="card empty-state">
                    <div class="icon">📜</div>
                    <h3>No attempts yet</h3>
                    <p>Start your first test to see history here</p>
                    <a href="/tests" class="btn btn-primary mt-2">Browse Tests</a>
                </div>`}
            </main>
        </div>`;
    }

    /* ---------- LEADERBOARD ---------- */
    function leaderboard(data) {
        const lb = data.lb || {};
        const me = data.me || {};
        const period = data.period || 'daily';
        const rows = Array.isArray(lb.data) ? lb.data : [];
        const meta = lb.meta || {};
        const pos = me.position || null;
        const optedIn = !!me.leaderboard_opt_in;
        const user = API.user() || {};
        const timezone = meta.timezone || 'Asia/Dhaka';

        const tabs = [
            ['daily', 'Daily'],
            ['weekly', 'Weekly'],
            ['monthly', 'Monthly'],
            ['overall', 'All Time'],
        ];
        const periodLabels = { daily: 'today', weekly: 'this week', monthly: 'this month', overall: 'overall' };

        function rankBadge(rank) {
            if (rank === 1) return '<span class="lb-medal">🥇</span>';
            if (rank === 2) return '<span class="lb-medal">🥈</span>';
            if (rank === 3) return '<span class="lb-medal">🥉</span>';
            return '<span class="lb-rank">#' + num(rank) + '</span>';
        }

        function avatar(name, url) {
            const initial = esc((name || '?').charAt(0).toUpperCase());
            return url
                ? `<img class="lb-avatar" src="${esc(url)}" alt="">`
                : `<span class="lb-avatar lb-avatar-initial">${initial}</span>`;
        }

        const positionCard = !optedIn ? `
            <div class="card lb-join">
                <div>
                    <h3 style="margin:0 0 4px;font-size:1rem">You are not on the board yet</h3>
                    <p class="text-muted" style="margin:0">Join to show your name, avatar, and best test scores to other students. You can leave at any time.</p>
                </div>
                <button class="btn btn-primary" onclick="App.toggleLeaderboardOptIn(true)">Join Leaderboard</button>
            </div>` : `
            <div class="card lb-me">
                <div class="lb-me-rank">${pos && pos.rank ? rankBadge(pos.rank) : '<span class="lb-medal lb-medal-none">#–</span>'}</div>
                <div class="lb-me-info">
                    <div class="lb-me-label">Your position · ${esc(periodLabels[period] || period)}</div>
                    <div class="lb-me-stats">
                        <span><strong>${pos && pos.rank ? '#' + num(pos.rank) : 'Unranked'}</strong></span>
                        <span>${num(pos ? pos.score : 0).toFixed(1)} pts</span>
                        <span>${num(pos ? pos.eligible_test_count : 0)} test${num(pos ? pos.eligible_test_count : 0) === 1 ? '' : 's'}</span>
                    </div>
                    <p class="text-muted" style="margin:4px 0 0;font-size:.82rem">${pos && pos.rank
                        ? 'Ranked from your best score on each completed test ' + esc(periodLabels[period] || period) + '.'
                        : 'Complete a test ' + esc(periodLabels[period] || period) + ' to appear on the board.'}</p>
                </div>
                <button class="btn btn-ghost btn-sm" onclick="App.toggleLeaderboardOptIn(false)">Leave</button>
            </div>`;

        const standings = rows.length ? `
            <div class="card" style="padding:0">
                <div class="table-wrapper">
                    <table class="table lb-table">
                        <thead>
                            <tr>
                                <th style="width:90px">Rank</th>
                                <th>Student</th>
                                <th>Score</th>
                                <th>Tests Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(r => {
                                const rank = num(r.rank);
                                const isMe = !!r.display_name && !!user.name && r.display_name === user.name;
                                return `
                            <tr class="${isMe ? 'lb-row-me' : ''}">
                                <td>${rankBadge(rank)}</td>
                                <td>
                                    <div class="lb-student">
                                        ${avatar(r.display_name, r.avatar_url)}
                                        <span>${esc(r.display_name || 'Student')}${isMe ? ' <span class="badge badge-info">You</span>' : ''}</span>
                                    </div>
                                </td>
                                <td><strong>${num(r.score).toFixed(1)}</strong> <span class="text-muted">pts</span></td>
                                <td>${num(r.eligible_test_count)}</td>
                            </tr>`; }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>` : `
            <div class="card empty-state">
                <div class="icon">🏆</div>
                <h3>No ranked students yet</h3>
                <p>Complete a model test and join the leaderboard to be the first name here</p>
                <a href="/tests" class="btn btn-primary mt-2">Browse Tests</a>
            </div>`;

        return `
        ${header('leaderboard')}
        <div class="layout">
            ${sidebar('leaderboard')}
            <main class="main">
                <div class="lb-hero">
                    <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:4px">🏆 Leaderboard</h1>
                    <p class="text-muted mb-3">Top students by best score per test · rankings reset at midnight (${esc(timezone)})</p>
                </div>

                <div class="lb-tabs">
                    ${tabs.map(([key, label]) => `
                        <a class="lb-tab ${period === key ? 'active' : ''}" href="/leaderboard?period=${key}">${label}</a>`).join('')}
                </div>

                ${positionCard}
                ${standings}

                ${rows.length ? `<p class="text-muted lb-foot">Showing ${rows.length} of ${num(meta.total)} ranked student${num(meta.total) === 1 ? '' : 's'}</p>` : ''}
            </main>
        </div>`;
    }

    /* ---------- PROFILE ---------- */
    function profile(data) {
        const u = data.user || API.user() || {};
        const avatarUrl = data.avatar_url || u.avatar_url || '';
        const initial = (u.name || '?').charAt(0).toUpperCase();
        return `
        ${header('profile')}
        <div class="layout">
            ${sidebar('profile')}
            <main class="main" style="max-width:700px">
                <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:20px">👤 Profile</h1>

                <div class="profile-header">
                    <div class="profile-avatar">
                        ${avatarUrl
                            ? `<img src="${esc(avatarUrl)}" class="avatar avatar-lg" style="object-fit:cover" alt="Avatar">`
                            : `<div class="avatar avatar-lg">${esc(initial)}</div>`}
                        <label class="edit-btn" for="photo-input" title="Change photo">📷</label>
                        <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp" onchange="App.uploadPhoto(this)">
                    </div>
                    <div>
                        <h2 style="font-size:1.3rem;font-weight:700">${esc(u.name)}</h2>
                        <p class="text-muted">${esc(u.email)}</p>
                        <span class="badge badge-primary mt-1">${esc(u.role || 'student')}</span>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><span class="card-title">Edit Information</span></div>
                    <form id="profile-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" id="p-name" class="form-control" value="${esc(u.name || '')}">
                                <div class="form-error" id="p-name-error"></div>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" id="p-phone" class="form-control" value="${esc(u.phone || '')}" placeholder="+8801XXXXXXXXX">
                                <div class="form-error" id="p-phone-error"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" id="p-dob" class="form-control" value="${esc(u.date_of_birth || '')}">
                                <div class="form-error" id="p-dob-error"></div>
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select id="p-gender" class="form-control">
                                    <option value="">Select</option>
                                    <option value="male" ${u.gender === 'male' ? 'selected' : ''}>Male</option>
                                    <option value="female" ${u.gender === 'female' ? 'selected' : ''}>Female</option>
                                    <option value="other" ${u.gender === 'other' ? 'selected' : ''}>Other</option>
                                </select>
                                <div class="form-error" id="p-gender-error"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" id="p-address" class="form-control" value="${esc(u.address || '')}" placeholder="Your address">
                            <div class="form-error" id="p-address-error"></div>
                        </div>
                        <div class="form-error" id="profile-error"></div>
                        <button type="submit" class="btn btn-primary" id="profile-btn">Save Changes</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-title">🔐 Change Password</span></div>
                    <form id="password-form">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" id="cur-pass" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" id="new-pass" class="form-control" placeholder="Min 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" id="conf-pass" class="form-control" placeholder="Repeat password">
                        </div>
                        <button type="submit" class="btn btn-outline">Update Password</button>
                    </form>
                </div>
            </main>
        </div>`;
    }

    return { header, sidebar, login, register, forgot, dashboard, testsList, examHeader, examBody, questionView, result, solution, solutionItem, history, leaderboard, profile, esc };
})();
