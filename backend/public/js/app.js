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

    function render(html) {
        root().innerHTML = html;
        window.scrollTo(0, 0);
        bindAuthForms();
    }

    function loading(msg = 'Loading...') {
        render(`<div class="loading-overlay"><div class="spinner"></div><p>${msg}</p></div>`);
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
        loading('Loading dashboard...');
        const [meRes, testsRes] = await Promise.allSettled([
            API.me(), API.tests('?status=published&per_page=5')
        ]);
        const user = meRes.status === 'fulfilled' ? meRes.value.user || meRes.value : API.user();
        const tests = testsRes.status === 'fulfilled' ? (testsRes.value.data || testsRes.value.tests || []) : [];
        render(Views.dashboard({ user, tests }));
        // load overview
        try {
            const ov = await API.request('/analytics/overview');
            const statsEl = document.querySelectorAll('.stat-info .value');
            if (statsEl.length >= 4) {
                statsEl[0].textContent = ov.total_tests ?? 0;
                statsEl[1].textContent = ov.test_count ?? 0;
                statsEl[2].textContent = ov.average_score != null ? Number(ov.average_score).toFixed(1) + '%' : '—';
                statsEl[3].textContent = ov.accuracy_rate != null ? Number(ov.accuracy_rate).toFixed(1) + '%' : '—';
            }
        } catch (_) { /* analytics optional */ }
    }

    /* ==========================================================
       PAGE: Tests List
       ========================================================== */
    async function pageTests() {
        loading('Loading tests...');
        const [testsRes, subjRes] = await Promise.allSettled([
            API.tests('?status=published&per_page=50'), API.subjects('?per_page=50')
        ]);
        const tests = testsRes.status === 'fulfilled' ? (testsRes.value.data || testsRes.value.tests || []) : [];
        const subjects = subjRes.status === 'fulfilled' ? (subjRes.value.data || subjRes.value.subjects || []) : [];

        // Check lock status for each test
        for (const t of tests) {
            try {
                const unlock = await API.request('/tests/' + t.id + '/unlock-status');
                t.is_locked = unlock.locked === true || unlock.is_locked === true;
                t.locked_reason = unlock.reason || unlock.message || '';
            } catch (_) { t.is_locked = false; }
        }

        render(Views.testsList({ tests, subjects }));
    }

    /* ==========================================================
       PAGE: Exam (Timer + Questions + Auto-save)
       ========================================================== */
    let currentExamTest = null;

    async function pageExam(testId) {
        loading('Starting test...');
        try {
            const data = await API.startAttempt(parseInt(testId));
            const attempt = data.attempt || data;
            const questions = data.questions || [];
            currentExamTest = attempt;

            if (!questions.length) {
                toast('No questions available for this test', 'error');
                return navigate('/tests');
            }

            // Render shell
            render(Views.examBody({
                title: attempt.test_title || 'Test',
                question_count: questions.length
            }, attempt));

            ExamEngine.init({ attempt: { ...attempt, test_id: testId }, questions, answers: data.answers || {} });
            renderQuestion();
        } catch (e) {
            if (e.status === 403) { toast(e.message, 'error'); return navigate('/tests'); }
            if (e.status === 409) {
                // Attempt already in progress — resume
                toast('Resuming previous attempt', 'warning');
                return resumeExam(parseInt(testId));
            }
            toast(e.message, 'error');
            navigate('/tests');
        }
    }

    async function resumeExam(testId) {
        try {
            const history = await API.attemptHistory('?test_id=' + testId + '&per_page=1');
            const attempts = history.data || history.attempts || [];
            const inProgress = attempts.find(a => a.status === 'in_progress');
            if (!inProgress) return navigate('/tests');

            const data = await API.resumeAttempt(inProgress.id);
            const attempt = data.attempt || data;
            const questions = data.questions || [];

            render(Views.examBody({ title: attempt.test_title || 'Test', question_count: questions.length }, attempt));
            ExamEngine.init({ attempt, questions, answers: data.answers || {} });
            renderQuestion();
        } catch (e) {
            toast('Could not resume: ' + e.message, 'error');
            navigate('/tests');
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
        loading('Loading result...');
        try {
            const data = await API.result(attemptId);
            const r = data.attempt || data;
            r.attempt_id = attemptId;
            render(Views.result({ ...r, ...data }));
        } catch (e) {
            if (e.status === 409) {
                toast('Test still in progress', 'warning');
                return navigate('/exam/' + attemptId);
            }
            toast(e.message, 'error');
            navigate('/history');
        }
    }

    /* ==========================================================
       PAGE: Solution
       ========================================================== */
    async function pageSolution(attemptId) {
        loading('Loading solutions...');
        try {
            const data = await API.solutions(attemptId, '?per_page=50');
            data.attempt_id = attemptId;
            render(Views.solution(data));
        } catch (e) {
            toast(e.message, 'error');
            navigate('/history');
        }
    }

    /* ==========================================================
       PAGE: History
       ========================================================== */
    async function pageHistory() {
        loading('Loading history...');
        try {
            const data = await API.attemptHistory('?per_page=20');
            render(Views.history(data));
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    /* ==========================================================
       PAGE: Profile
       ========================================================== */
    async function pageProfile() {
        loading('Loading profile...');
        try {
            const data = await API.getProfile();
            render(Views.profile(data.user ? data : { user: data }));
            bindProfileForm();
        } catch (e) {
            render(Views.profile({ user: API.user() }));
            bindProfileForm();
        }
    }

    function bindProfileForm() {
        const pf = document.getElementById('profile-form');
        if (pf) pf.addEventListener('submit', async (e) => {
            e.preventDefault();
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
            } catch (err) {
                const el = document.getElementById('profile-error');
                if (el) el.textContent = err.message;
            } finally {
                btn.disabled = false; btn.textContent = 'Save Changes';
            }
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
                API.setAuth(res.token, res.user);
                toast('Welcome back, ' + res.user.name + '!');
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
                API.setAuth(res.token, res.user);
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

    // Resume test
    function resumeTest(attemptId) { navigate('/exam/' + attemptId); }

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
            const matchSubject = !subject || card.dataset.subject === subject;
            const matchSearch = !search || card.dataset.title.includes(search);
            card.style.display = (matchSubject && matchSearch) ? '' : 'none';
        });
    }

    // Profile photo
    async function uploadPhoto(input) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { toast('Max 2MB', 'error'); return; }
        try {
            await API.uploadPhoto(file);
            toast('Photo updated!');
            pageProfile();
        } catch (e) { toast(e.message, 'error'); }
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
        if (page === 'profile') return pageProfile();

        route();
    }

    document.addEventListener('DOMContentLoaded', boot);

    return {
        navigate, logout,
        startTest, resumeTest,
        selectOption, clearAnswer, toggleFlag,
        nextQuestion, prevQuestion, goToQuestion,
        confirmSubmit, filterTests, uploadPhoto,
        loadMoreSolutions: () => {},
        route,
    };
})();
