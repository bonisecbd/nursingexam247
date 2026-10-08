/* ============================================================
   NurseExam247 — Exam Engine (Timer, Navigation, Auto-save)
   ============================================================ */
const ExamEngine = (() => {
    let state = {
        attemptId: null,
        testId: null,
        questions: [],       // [{ id, sequence, question_text, options: [...], points }]
        answers: {},         // { [attemptQuestionId]: selected_option }
        flags: {},           // { [attemptQuestionId]: true }
        currentIndex: 0,
        expiresAt: null,
        remainingSeconds: 0,
        timerId: null,
        saveTimers: {},       // { [attemptQuestionId]: timeoutId }
        pendingSaves: {},     // { [attemptQuestionId]: Promise }
        submitting: false,
    };

    function init(data) {
        const attempt = data.attempt || {};
        state.attemptId = attempt.id;
        state.testId = attempt.test_id ?? (attempt.test ? attempt.test.id : null);
        state.questions = data.questions || [];
        state.answers = data.answers || {};
        state.expiresAt = attempt.expires_at;
        state.remainingSeconds = remainingFrom(attempt);
        state.currentIndex = 0;
        state.flags = {};
        state.saveTimers = {};
        state.pendingSaves = {};
        state.submitting = false;

        // restore current index from first unanswered question
        const firstUnanswered = state.questions.findIndex(q =>
            !(q.id in state.answers) || state.answers[q.id] === null || state.answers[q.id] === undefined
        );
        if (firstUnanswered >= 0) state.currentIndex = firstUnanswered;

        startTimer();
    }

    // Prefer the server-provided remaining seconds; fall back to expires_at.
    function remainingFrom(attempt) {
        const secs = Number(attempt.time_remaining_seconds);
        if (Number.isFinite(secs) && attempt.time_remaining_seconds !== null && attempt.time_remaining_seconds !== undefined) {
            return Math.max(0, Math.floor(secs));
        }
        const exp = Date.parse(attempt.expires_at || '');
        if (Number.isFinite(exp)) return Math.max(0, Math.floor((exp - Date.now()) / 1000));
        return 0;
    }

    function startTimer() {
        stopTimer();
        if (state.remainingSeconds <= 0) {
            updateTimerDisplay();
            autoSubmit();
            return;
        }
        state.timerId = setInterval(() => {
            state.remainingSeconds--;
            updateTimerDisplay();
            if (state.remainingSeconds <= 0) {
                stopTimer();
                autoSubmit();
            }
        }, 1000);
        updateTimerDisplay();
    }

    function stopTimer() {
        if (state.timerId) { clearInterval(state.timerId); state.timerId = null; }
    }

    function updateTimerDisplay() {
        const el = document.getElementById('exam-timer');
        if (!el) return;
        const s = Math.max(0, state.remainingSeconds);
        const m = Math.floor(s / 60);
        const sec = s % 60;
        el.textContent = String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        el.classList.remove('warning', 'danger');
        if (s <= 60) el.classList.add('danger');
        else if (s <= 300) el.classList.add('warning');
    }

    function current() { return state.questions[state.currentIndex]; }

    function getAnswer(qId) { return state.answers[qId] !== undefined ? state.answers[qId] : null; }

    function answeredCount() {
        return Object.values(state.answers).filter(v => v !== null && v !== undefined).length;
    }

    // Server deadline reached (410): the attempt is finalized as expired.
    function handleExpired() {
        stopTimer();
        Object.values(state.saveTimers).forEach(id => clearTimeout(id));
        state.saveTimers = {};
        state.submitting = true;
        notify('Time is up! Your attempt has been submitted.');
        navigate('/result/' + state.attemptId);
    }

    function notify(msg) {
        if (window.App && typeof App.toast === 'function') App.toast(msg, 'warning');
        else alert(msg);
    }

    function onSaved(e) {
        if (e.status === 410) { handleExpired(); return; }
        if (window.App && typeof App.toast === 'function') App.toast(e.message || 'Could not save answer', 'error');
    }

    async function persistAnswer(qId, optionIndex) {
        try {
            await API.saveAnswer(state.attemptId, qId, optionIndex);
            showSaveIndicator();
        } catch (e) {
            onSaved(e);
        }
    }

    // Debounced autosave — timers are kept per question so switching
    // questions quickly never drops a pending save.
    function scheduleSave(qId, optionIndex) {
        if (state.saveTimers[qId]) clearTimeout(state.saveTimers[qId]);
        state.saveTimers[qId] = setTimeout(() => {
            delete state.saveTimers[qId];
            state.pendingSaves[qId] = persistAnswer(qId, optionIndex)
                .finally(() => { delete state.pendingSaves[qId]; });
        }, 300);
    }

    // Flush every pending save (used before submit so no answer is lost).
    function flushSaves() {
        const jobs = [];
        Object.keys(state.saveTimers).forEach(qId => {
            clearTimeout(state.saveTimers[qId]);
            delete state.saveTimers[qId];
            jobs.push(persistAnswer(qId, state.answers[qId]).finally(() => { delete state.pendingSaves[qId]; }));
        });
        Object.keys(state.pendingSaves).forEach(qId => jobs.push(state.pendingSaves[qId]));
        return Promise.all(jobs);
    }

    async function selectOption(qId, optionIndex) {
        state.answers[qId] = optionIndex;
        scheduleSave(qId, optionIndex);
    }

    function clearAnswer(qId) {
        state.answers[qId] = null;
        if (state.saveTimers[qId]) { clearTimeout(state.saveTimers[qId]); delete state.saveTimers[qId]; }
        state.pendingSaves[qId] = persistAnswer(qId, null).finally(() => { delete state.pendingSaves[qId]; });
    }

    function toggleFlag(qId) {
        if (state.flags[qId]) delete state.flags[qId];
        else state.flags[qId] = true;
    }

    function goTo(index) {
        if (index < 0 || index >= state.questions.length) return;
        state.currentIndex = index;
    }

    function next() { goTo(state.currentIndex + 1); }
    function prev() { goTo(state.currentIndex - 1); }

    async function submit() {
        if (state.submitting) return;
        state.submitting = true;
        stopTimer();
        try {
            await flushSaves();
            const result = await API.submitAttempt(state.attemptId);
            navigate('/result/' + state.attemptId);
            return result;
        } catch (e) {
            // 410: server already finalized the attempt as expired — result is available.
            if (e.status === 410 || e.status === 409) {
                navigate('/result/' + state.attemptId);
                return;
            }
            state.submitting = false;
            startTimer();
            throw e;
        }
    }

    async function autoSubmit() {
        if (state.submitting) return;
        state.submitting = true;
        stopTimer();
        try {
            await flushSaves();
            await API.submitAttempt(state.attemptId);
        } catch (_) { /* server already finalized it (410) or retryable — result page will settle it */ }
        navigate('/result/' + state.attemptId);
    }

    function showSaveIndicator() {
        const el = document.getElementById('save-indicator');
        if (!el) return;
        el.textContent = '✓ Saved';
        el.style.color = 'var(--success)';
        setTimeout(() => { if (el.textContent === '✓ Saved') el.textContent = ''; }, 1500);
    }

    function destroy() {
        stopTimer();
        // Keep any pending autosave: flush it in the background before teardown.
        flushSaves().catch(() => {});
        Object.values(state.saveTimers).forEach(id => clearTimeout(id));
        state.saveTimers = {};
        state.submitting = false;
    }

    function getState() { return { ...state }; }

    return { init, current, getAnswer, answeredCount, selectOption, clearAnswer, toggleFlag, goTo, next, prev, submit, destroy, getState, flushSaves };
})();
