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
        saveTimerId: null,
        submitting: false,
    };

    function init(data) {
        state.attemptId = data.attempt.id;
        state.testId = data.attempt.test_id;
        state.questions = data.questions || [];
        state.answers = data.answers || {};
        state.expiresAt = data.attempt.expires_at;
        state.remainingSeconds = data.attempt.time_remaining_seconds || 0;
        state.currentIndex = 0;
        state.flags = {};
        state.submitting = false;

        // restore current index from saved answers if possible
        const firstUnanswered = state.questions.findIndex(q => !(q.id in state.answers));
        if (firstUnanswered >= 0) state.currentIndex = firstUnanswered;

        startTimer();
    }

    function startTimer() {
        stopTimer();
        state.timerId = setInterval(() => {
            state.remainingSeconds--;
            updateTimerDisplay();
            if (state.remainingSeconds <= 0) {
                stopTimer();
                autoSubmit();
            } else if (state.remainingSeconds === 60 || state.remainingSeconds === 30) {
                // warning handled in display
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
        const s = state.remainingSeconds;
        const m = Math.floor(s / 60);
        const sec = s % 60;
        el.textContent = String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        el.classList.remove('warning', 'danger');
        if (s <= 60) el.classList.add('danger');
        else if (s <= 300) el.classList.add('warning');
    }

    function current() { return state.questions[state.currentIndex]; }

    function getAnswer(qId) { return state.answers[qId] !== undefined ? state.answers[qId] : null; }

    function answeredCount() { return Object.keys(state.answers).length; }

    async function selectOption(qId, optionIndex) {
        state.answers[qId] = optionIndex;
        // Auto-save with debounce
        if (state.saveTimerId) clearTimeout(state.saveTimerId);
        state.saveTimerId = setTimeout(async () => {
            try {
                await API.saveAnswer(state.attemptId, qId, optionIndex);
                showSaveIndicator();
            } catch (e) {
                if (e.status === 410) {
                    stopTimer();
                    alert('Time is up! Your attempt has been submitted.');
                    navigate('/result/' + state.attemptId);
                }
            }
        }, 300);
    }

    function clearAnswer(qId) {
        state.answers[qId] = null;
        API.saveAnswer(state.attemptId, qId, null).catch(() => {});
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
            const result = await API.submitAttempt(state.attemptId);
            navigate('/result/' + state.attemptId);
            return result;
        } catch (e) {
            state.submitting = false;
            startTimer();
            throw e;
        }
    }

    async function autoSubmit() {
        if (state.submitting) return;
        state.submitting = true;
        try {
            await API.submitAttempt(state.attemptId);
        } catch (_) { /* ignore */ }
        navigate('/result/' + state.attemptId);
    }

    function showSaveIndicator() {
        const el = document.getElementById('save-indicator');
        if (!el) return;
        el.textContent = '✓ Saved';
        el.style.color = 'var(--success)';
        setTimeout(() => { el.textContent = ''; }, 1500);
    }

    function destroy() {
        stopTimer();
        if (state.saveTimerId) clearTimeout(state.saveTimerId);
        state.submitting = false;
    }

    function getState() { return { ...state }; }

    return { init, current, getAnswer, answeredCount, selectOption, clearAnswer, toggleFlag, goTo, next, prev, submit, destroy, getState };
})();
