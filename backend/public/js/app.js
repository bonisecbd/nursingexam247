/* ============================================================
   NurseExam247 — Main Application (Router + Controllers)
   ============================================================ */
const App = (() => {
    const root = () => document.getElementById('app');
    const esc = Views.esc;

    /* ---------- Router ---------- */
    function navigate(path) {
        history.pushState({}, '', path);
        route();
    }
    window.navigate = navigate;

    window.addEventListener('popstate', route);

    async function route() {
        const path = location.pathname;
        const boot = window.__BOOT__ || {};

        // Destroy exam timer if leaving exam page
        ExamEngine.destroy();

        try {
            // Public pages
            if (path === '/') { return render(Views.login()); }
            if (path === '/login') { return render(Views.login()); }
            if (path === '/register') { return render(Views.register()); }
            if (path === '/forgot-password') { return render(Views.forgot()); }

            // Auth required
            if (!API.isLoggedIn()) { return navigate('/login'); }

            if (path === '/dashboard') return pageDashboard();
            if (path === '/tests') return pageTests();
            if (path === '/history') return pageHistory();
            if (path === '/leaderboard') return pageLeaderboard();
            if (path === '/profile') return pageProfile();

            const examMatch = path.match(/^\/exam\/(\d+)$/);
            if (examMatch) return pageExam(examMatch[1]);

            const resMatch = path.match(/^\/result\/(\d+)$/);
            if (resMatch) return pageResult(resMatch[1]);

            const solMatch = path.match(/^\/solution\/(\d+)$/);
            if (solMatch) return pageSolution(solMatch[1]);

            // fallback
            return navigate('/dashboard');
        } catch (e) {
            console.error(e);
            if (e.status === 401) { API.clearAuth(); return navigate('/login'); }
            render(`<div class="empty-state" style="padding-top:100px"><div class="icon">⚠️</div><h3>Something went wrong</h3><p>${esc(e.message)}</p><a href="/dashboard" class="btn btn-primary mt-2">Go Home</a></div>`);
        }
    }

    // Every render bumps this token so slow async pages cannot paint over
    // a page the user has since navigated away from.
    let renderToken = 0;

    function render(html) {
        renderToken++;
        root().innerHTML = html;
        window.scrollTo(0, 0);
        bindAuthForms();
        return renderToken;
    }

    function stale(token) { return token !== renderToken; }

    function loading(msg = 'Loading...') {
        return render(`<div class="loading-overlay"><div class="spinner"></div><p>${esc(msg)}</p></div>`);
    }

    function toast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = msg;
        document.getElementById('toast-container').appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    /* ==========================================================
       PAGE: Dashboard
       ========================================================== */
    async function pageDashboard() {
        const seq = loading('Loading dashboard...');
        const [meRes, testsRes] = await Promise.allSettled([
            API.me(), API.tests('?status=published&per_page=5')
        ]);
        if (stale(seq)) return;
        const user = (meRes.status === 'fulfilled' && (meRes.value.user || meRes.value)) || API.user() || {};
        const tests = testsRes.status === 'fulfilled'
            ? (testsRes.value.data || testsRes.value.tests || [])
            : [];
        const token = render(Views.dashboard({ user, tests }));
        // load overview (optional: the analytics module may not be deployed)
        try {
            const ov = await API.request('/analytics/overview');
            if (stale(token)) return;
            const statsEl = document.querySelectorAll('.stat-info .value');
            if (statsEl.length >= 4) {
                if (ov.total_tests != null) statsEl[0].textContent = ov.total_tests;
                if (ov.test_count != null) statsEl[1].textContent = ov.test_count;
                if (ov.average_score != null) statsEl[2].textContent = Number(ov.average_score).toFixed(1) + '%';
                if (ov.accuracy_rate != null) statsEl[3].textContent = Number(ov.accuracy_rate).toFixed(1) + '%';
            }
        } catch (_) { /* analytics optional — stats stay as em dashes */ }
    }

    /* ==========================================================
       PAGE: Tests List
       ========================================================== */
    async function pageTests() {
        const seq = loading('Loading tests...');
        const [testsRes, subjRes] = await Promise.allSettled([
            API.tests('?status=published&per_page=50'), API.subjects('?per_page=50')
        ]);
        if (stale(seq)) return;
        const tests = testsRes.status === 'fulfilled' ? (testsRes.value.data || testsRes.value.tests || []) : [];
        const subjects = subjRes.status === 'fulfilled' ? (subjRes.value.data || subjRes.value.subjects || []) : [];

        // Check lock status for each test (endpoint may be unavailable — degrade to unlocked)
        for (const t of tests) {
            try {
                const unlock = await API.request('/tests/' + t.id + '/unlock-status');
                t.is_locked = unlock.locked === true || unlock.is_locked === true;
                t.locked_reason = unlock.reason || unlock.message || '';
            } catch (_) { t.is_locked = false; }
            if (stale(seq)) return;
        }

        render(Views.testsList({ tests, subjects }));
    }

    /* ==========================================================
       PAGE: Exam (Timer + Questions + Auto-save)
       ========================================================== */
    let currentExamTest = null;

    // Attempt payloads nest the questions inside `attempt`, and each question
    // carries its saved answer as `selected_option`.
    function attemptView(data) {
        const attempt = data.attempt || data;
        const questions = attempt.questions || data.questions || [];
        const answers = {};
        if (Array.isArray(data.answers)) {
            data.answers.forEach(a => {
                const k = a.attempt_question_id ?? a.question_id ?? a.id;
                if (k != null) answers[k] = a.selected_option ?? null;
            });
        } else if (data.answers && typeof data.answers === 'object') {
            Object.assign(answers, data.answers);
        }
        questions.forEach(q => {
            if (q.selected_option !== undefined) answers[q.id] = q.selected_option;
        });
        // A null selection means unanswered — keep it out of the answer map.
        Object.keys(answers).forEach(k => {
            if (answers[k] === null || answers[k] === undefined) delete answers[k];
        });
        return { attempt, questions, answers };
    }

    function mountExam(data) {
        const { attempt, questions, answers } = attemptView(data);
        const testInfo = attempt.test || {};
        currentExamTest = attempt;

        if (!questions.length) {
            toast('No questions available for this test', 'error');
            return null;
        }

        render(Views.examBody({
            title: attempt.test_title || testInfo.title || 'Test',
            question_count: questions.length
        }, attempt));

        ExamEngine.init({
            attempt: { ...attempt, test_id: attempt.test_id ?? testInfo.id },
            questions,
            answers,
        });
        renderQuestion();
        return attempt;
    }

    async function pageExam(testId) {
        const seq = loading('Starting test...');
        try {
            const data = await API.startAttempt(parseInt(testId, 10));
            if (stale(seq)) return;
            if (!mountExam(data)) return navigate('/tests');
        } catch (e) {
            if (stale(seq)) return;
            if (e.status === 409) {
                // Attempt already in progress — resume it
                toast('Resuming previous attempt', 'warning');
                return resumeExam(parseInt(testId, 10));
            }
            toast(e.message, 'error');
            navigate('/tests');
        }
    }

    async function resumeExam(testId) {
        try {
            const history = await API.attemptHistory('?test_id=' + testId + '&per_page=20');
            const attempts = history.data || history.attempts || [];
            const inProgress = attempts.find(a => a.status === 'in_progress');
            if (!inProgress) return navigate('/tests');
            await resumeExamAttempt(inProgress.attempt_id ?? inProgress.id);
        } catch (e) {
            toast('Could not resume: ' + e.message, 'error');
            navigate('/tests');
        }
    }

    // Resume an existing attempt by its own ID (history Resume button, result 409).
    async function resumeExamAttempt(attemptId) {
        const seq = loading('Resuming test...');
        try {
            const data = await API.resumeAttempt(attemptId);
            if (stale(seq)) return;
            const attempt = data.attempt || data;
            const status = attempt.status || '';

            if (status && status !== 'in_progress') {
                return navigate('/result/' + attemptId);
            }

            const testId = (attempt.test && attempt.test.id) || attempt.test_id;
            if (testId) history.replaceState({}, '', '/exam/' + testId);

            if (!mountExam(data)) return navigate('/tests');
        } catch (e) {
            if (stale(seq)) return;
            toast(e.message || 'Could not resume the attempt', 'error');
            navigate('/history');
        }
    }

    function renderQuestion() {
        const st = ExamEngine.getState();
        const q = ExamEngine.current();
        if (!q) return;

        const area = document.getElementById('question-area');
        const grid = document.getElementById('question-grid');
        const progress = document.getElementById('progress-text');

        if (area) {
            area.innerHTML = Views.questionView(
                q,
                ExamEngine.getAnswer(q.id),
                !!st.flags[q.id],
                st.currentIndex,
                st.questions.length
            );
        }

        if (progress) {
            progress.textContent = `Q ${st.currentIndex + 1} of ${st.questions.length}`;
        }

        if (grid) {
            grid.innerHTML = st.questions.map((qq, i) => {
                const answered = qq.id in st.answers && st.answers[qq.id] !== null;
                const isCurrent = i === st.currentIndex;
                const flagged = !!st.flags[qq.id];
                let cls = 'q-num';
                if (answered) cls += ' answered';
                if (isCurrent) cls += ' current';
                else if (flagged) cls += ' flagged';
                return `<button class="${cls}" onclick="App.goToQuestion(${i})">${i + 1}</button>`;
            }).join('');
        }
    }

    /* ==========================================================
       PAGE: Result
       ========================================================== */
    async function pageResult(attemptId) {
        const seq = loading('Loading result...');
        try {
            const data = await API.result(attemptId);
            if (stale(seq)) return;
            render(Views.result(data));
        } catch (e) {
            if (stale(seq)) return;
            if (e.status === 409) {
                toast('Test still in progress', 'warning');
                return resumeExamAttempt(attemptId);
            }
            toast(e.message, 'error');
            navigate('/history');
        }
    }

    /* ==========================================================
       PAGE: Solution (paginated)
       ========================================================== */
    const SOLUTIONS_PER_PAGE = 10;
    let solState = { attemptId: null, page: 1, last: 1, loading: false };

    async function pageSolution(attemptId) {
        const seq = loading('Loading solutions...');
        try {
            const data = await API.solutions(attemptId, '?per_page=' + SOLUTIONS_PER_PAGE + '&page=1');
            if (stale(seq)) return;
            data.attempt_id = attemptId;
            solState = {
                attemptId,
                page: Number(data.current_page || 1),
                last: Number(data.last_page || 1),
                loading: false,
            };
            render(Views.solution(data));
        } catch (e) {
            if (stale(seq)) return;
            toast(e.message, 'error');
            navigate('/history');
        }
    }

    async function loadMoreSolutions() {
        if (solState.loading || !solState.attemptId) return;
        if (solState.page >= solState.last) return;
        solState.loading = true;
        const btn = document.getElementById('load-more-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Loading...'; }
        try {
            const next = solState.page + 1;
            const data = await API.solutions(solState.attemptId, '?per_page=' + SOLUTIONS_PER_PAGE + '&page=' + next);
            solState.page = Number(data.current_page || next);
            solState.last = Number(data.last_page || solState.last);
            const items = Array.isArray(data.data) ? data.data : [];
            const list = document.getElementById('solutions-list');
            if (list) {
                const empty = list.querySelector('.empty-state');
                if (empty) empty.remove();
                list.insertAdjacentHTML('beforeend', items.map(s => Views.solutionItem(s)).join(''));
            }
            const more = document.getElementById('solutions-more');
            if (more) more.hidden = solState.page >= solState.last;
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            solState.loading = false;
            const b = document.getElementById('load-more-btn');
            if (b) { b.disabled = false; b.textContent = 'Load More'; }
        }
    }

    /* ==========================================================
       PAGE: History
       ========================================================== */
    async function pageHistory() {
        const seq = loading('Loading history...');
        try {
            const data = await API.attemptHistory('?per_page=20');
            if (stale(seq)) return;
            render(Views.history(data));
        } catch (e) {
            if (stale(seq)) return;
            toast(e.message, 'error');
            render(Views.history({ data: [] }));
        }
    }

    /* ==========================================================
       PAGE: Leaderboard
       ========================================================== */
    const LB_PERIODS = ['daily', 'weekly', 'monthly', 'overall'];

    async function pageLeaderboard() {
        const qs = new URLSearchParams(location.search);
        const period = LB_PERIODS.indexOf(qs.get('period')) !== -1 ? qs.get('period') : 'daily';
        const seq = loading('Loading leaderboard...');

        const [standingsRes, positionRes] = await Promise.allSettled([
            API.request('/leaderboards?period=' + period + '&per_page=50'),
            API.request('/leaderboards/' + period + '/me'),
        ]);
        if (stale(seq)) return;

        if (standingsRes.status === 'rejected') toast(standingsRes.reason.message || 'Could not load the leaderboard', 'error');
        if (positionRes.status === 'rejected') toast(positionRes.reason.message || 'Could not load your position', 'error');

        const lb = standingsRes.status === 'fulfilled' ? standingsRes.value : { data: [], meta: { period: period } };
        const me = positionRes.status === 'fulfilled' ? positionRes.value : { leaderboard_opt_in: false, position: null };

        render(Views.leaderboard({ lb, me, period }));
    }

    // Opt in/out of the public leaderboard (PATCH /api/profile).
    async function toggleLeaderboardOptIn(optIn) {
        try {
            await API.updateProfile({ leaderboard_opt_in: !!optIn });
            toast(optIn ? 'You joined the leaderboard.' : 'You left the leaderboard.');
            route();
        } catch (e) {
            toast(e.message || 'Could not update your leaderboard preference', 'error');
        }
    }

    /* ==========================================================
       PAGE: Profile
       ========================================================== */
    async function pageProfile() {
        const seq = loading('Loading profile...');
        try {
            const data = await API.getProfile();
            if (stale(seq)) return;
            render(Views.profile(data.user ? data : { user: data }));
            bindProfileForm();
        } catch (e) {
            if (stale(seq)) return;
            // Still show the locally cached profile so the page is never blank.
            render(Views.profile({ user: API.user() || {} }));
            bindProfileForm();
        }
    }

    const PROFILE_FIELD_MAP = {
        name: 'p-name',
        phone: 'p-phone',
        date_of_birth: 'p-dob',
        gender: 'p-gender',
        address: 'p-address',
    };

    function showFieldErrors(prefix, err) {
        document.querySelectorAll('.form-error').forEach(el => { if (el.id !== prefix + '-error') el.textContent = ''; });
        const main = document.getElementById(prefix + '-error');
        if (main) main.textContent = err.message || '';
        if (err.errors) {
            for (const [field, msgs] of Object.entries(err.errors)) {
                const id = (PROFILE_FIELD_MAP[field] || (prefix === 'profile' ? 'p-' + field : field)) + '-error';
                const el = document.getElementById(id);
                if (el) el.textContent = Array.isArray(msgs) ? msgs[0] : msgs;
            }
        }
    }

    function bindProfileForm() {
        const pf = document.getElementById('profile-form');
        if (pf) pf.addEventListener('submit', async (e) => {
            e.preventDefault();
            showFieldErrors('profile', { message: '' });
            const btn = document.getElementById('profile-btn');
            btn.disabled = true; btn.textContent = 'Saving...';
            try {
                await API.updateProfile({
                    name: document.getElementById('p-name').value,
                    phone: document.getElementById('p-phone').value,
                    date_of_birth: document.getElementById('p-dob').value,
                    gender: document.getElementById('p-gender').value,
                    address: document.getElementById('p-address').value,
                });
                toast('Profile updated!');
                pageProfile();
            } catch (err) {
                showFieldErrors('profile', err);
            } finally {
                const b = document.getElementById('profile-btn');
                if (b) { b.disabled = false; b.textContent = 'Save Changes'; }
            }
        });

        // There is no authenticated change-password API — the documented flow is
        // the emailed reset code. Validate and point the user there (never reload).
        const pwf = document.getElementById('password-form');
        if (pwf) pwf.addEventListener('submit', (e) => {
            e.preventDefault();
            const cur = document.getElementById('cur-pass').value;
            const nw = document.getElementById('new-pass').value;
            const cf = document.getElementById('conf-pass').value;
            if (!cur || !nw || !cf) { toast('All password fields are required', 'error'); return; }
            if (nw !== cf) { toast('New passwords do not match', 'error'); return; }
            toast('Password changes use an emailed reset code — continue on the Forgot Password page.', 'warning');
            setTimeout(() => navigate('/forgot-password'), 1200);
        });
    }

    /* ==========================================================
       ACTIONS
       ========================================================== */

    // Auth forms
    function bindAuthForms() {
        const lf = document.getElementById('login-form');
        if (lf) lf.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            const btn = document.getElementById('login-btn');
            btn.disabled = true; btn.textContent = 'Logging in...';
            try {
                const res = await API.login({
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                });
                if (!res.token) throw new Error('Login response did not include a token');
                API.setAuth(res.token, res.user || {});
                toast('Welcome back, ' + ((res.user && res.user.name) || '') + '!');
                navigate('/dashboard');
            } catch (err) {
                showFormError(err);
                btn.disabled = false; btn.textContent = 'Login';
            }
        });

        const rf = document.getElementById('register-form');
        if (rf) rf.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            const btn = document.getElementById('register-btn');
            btn.disabled = true; btn.textContent = 'Creating...';
            try {
                const res = await API.register({
                    name: document.getElementById('name').value,
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                    password_confirmation: document.getElementById('password_confirmation').value,
                });
                if (!res.token) throw new Error('Registration response did not include a token');
                API.setAuth(res.token, res.user || {});
                toast('Account created successfully!');
                navigate('/dashboard');
            } catch (err) {
                showFormError(err);
                btn.disabled = false; btn.textContent = 'Create Account';
            }
        });

        // Forgot password (2-step: send code → reset)
        const ff = document.getElementById('forgot-form');
        if (ff) {
            let step = 1;
            ff.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearErrors();
                const btn = document.getElementById('forgot-btn');
                btn.disabled = true;

                try {
                    if (step === 1) {
                        await API.forgotPassword({ email: document.getElementById('email').value });
                        document.getElementById('otp-group').classList.remove('hidden');
                        document.getElementById('new-pass-group').classList.remove('hidden');
                        document.getElementById('confirm-pass-group').classList.remove('hidden');
                        document.getElementById('forgot-sub').textContent = 'Enter the code sent to your email and new password';
                        btn.textContent = 'Reset Password';
                        step = 2;
                        toast('Reset code sent to your email!', 'success');
                    } else {
                        await API.resetPassword({
                            email: document.getElementById('email').value,
                            otp: document.getElementById('otp').value,
                            password: document.getElementById('password').value,
                            password_confirmation: document.getElementById('password_confirmation').value,
                        });
                        toast('Password reset! Please login.');
                        navigate('/login');
                    }
                    btn.disabled = false;
                } catch (err) {
                    showFormError(err);
                    btn.disabled = false;
                }
            });
        }
    }

    function clearErrors() {
        document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
    }

    function showFormError(err) {
        const fe = document.getElementById('form-error');
        if (fe) fe.textContent = err.message;
        if (err.errors) {
            for (const [field, msgs] of Object.entries(err.errors)) {
                const el = document.getElementById(field + '-error');
                if (el) el.textContent = Array.isArray(msgs) ? msgs[0] : msgs;
            }
        }
    }

    // Start test
    function startTest(testId) { navigate('/exam/' + testId); }

    // Resume an in-progress attempt (history list)
    function resumeTest(attemptId) {
        if (attemptId == null) return;
        resumeExamAttempt(attemptId);
    }

    // Exam controls
    function selectOption(qId, optIdx) {
        ExamEngine.selectOption(qId, optIdx);
        renderQuestion();
    }
    function clearAnswer(qId) {
        ExamEngine.clearAnswer(qId);
        renderQuestion();
    }
    function toggleFlag(qId) {
        ExamEngine.toggleFlag(qId);
        renderQuestion();
    }
    function nextQuestion() { ExamEngine.next(); renderQuestion(); }
    function prevQuestion() { ExamEngine.prev(); renderQuestion(); }
    function goToQuestion(i) { ExamEngine.goTo(i); renderQuestion(); }

    function confirmSubmit() {
        const st = ExamEngine.getState();
        const answered = ExamEngine.answeredCount();
        const total = st.questions.length;
        const skipped = total - answered;

        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-title">Submit Test?</div>
                <div class="modal-body">
                    <p><strong>Answered:</strong> ${answered}/${total}</p>
                    <p><strong>Skipped:</strong> ${skipped}</p>
                    <p class="mt-1">Once submitted, you cannot change your answers.</p>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                    <button class="btn btn-danger" id="confirm-submit-btn">Yes, Submit</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);

        document.getElementById('confirm-submit-btn').addEventListener('click', async () => {
            overlay.remove();
            try {
                await ExamEngine.submit();
            } catch (e) {
                toast(e.message, 'error');
            }
        });
    }

    // Filter tests
    function filterTests() {
        const subject = document.getElementById('filter-subject')?.value || '';
        const search = (document.getElementById('filter-search')?.value || '').toLowerCase();
        document.querySelectorAll('#tests-list .card').forEach(card => {
            const matchSubject = !subject || (card.dataset.subject || '') === subject;
            const matchSearch = !search || (card.dataset.title || '').includes(search);
            card.style.display = (matchSubject && matchSearch) ? '' : 'none';
        });
    }

    // Profile photo
    async function uploadPhoto(input) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { toast('Photo must be 2MB or smaller', 'error'); input.value = ''; return; }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { toast('Only JPEG, PNG or WebP images are allowed', 'error'); input.value = ''; return; }
        try {
            await API.uploadPhoto(file);
            toast('Photo updated!');
            pageProfile();
        } catch (e) {
            toast(e.message, 'error');
            input.value = '';
        }
    }

    // Logout
    async function logout() {
        try { await API.logout(); } catch (_) {}
        API.clearAuth();
        toast('Logged out');
        navigate('/login');
    }

    /* ==========================================================
       BOOT
       ========================================================== */
    function boot() {
        const cfg = window.__BOOT__ || {};
        const page = cfg.page || 'dashboard';

        if (page === 'login') return render(Views.login());
        if (page === 'register') return render(Views.register());
        if (page === 'forgot') return render(Views.forgot());

        if (!API.isLoggedIn()) { navigate('/login'); return; }

        if (page === 'dashboard') return pageDashboard();
        if (page === 'tests') return pageTests();
        if (page === 'exam' && cfg.param) return pageExam(cfg.param);
        if (page === 'result' && cfg.param) return pageResult(cfg.param);
        if (page === 'solution' && cfg.param) return pageSolution(cfg.param);
        if (page === 'history') return pageHistory();
        if (page === 'leaderboard') return pageLeaderboard();
        if (page === 'profile') return pageProfile();

        route();
    }

    document.addEventListener('DOMContentLoaded', boot);

    return {
        navigate, logout, toast,
        startTest, resumeTest,
        selectOption, clearAnswer, toggleFlag,
        nextQuestion, prevQuestion, goToQuestion,
        confirmSubmit, filterTests, uploadPhoto,
        loadMoreSolutions,
        toggleLeaderboardOptIn,
        route,
    };
})();

// Inline handlers (sidebar, exam toasts) and exam.js look the app up on window.
window.App = App;
