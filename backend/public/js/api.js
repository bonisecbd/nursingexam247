/* ============================================================
   NurseExam247 — API Client (Bearer token auth)
   ============================================================ */
const API = (() => {
    const BASE = '/api';

    function token() {
        return localStorage.getItem('nurse_token') || '';
    }

    function setAuth(t, user) {
        localStorage.setItem('nurse_token', t);
        localStorage.setItem('nurse_user', JSON.stringify(user));
    }

    function clearAuth() {
        localStorage.removeItem('nurse_token');
        localStorage.removeItem('nurse_user');
    }

    function user() {
        try { return JSON.parse(localStorage.getItem('nurse_user')); }
        catch { return null; }
    }

    function isLoggedIn() { return !!token(); }

    async function request(path, { method = 'GET', body = null, formData = null } = {}) {
        const headers = { 'Accept': 'application/json' };
        if (token()) headers['Authorization'] = 'Bearer ' + token();

        const opts = { method, headers };
        if (formData) {
            opts.body = formData;
        } else if (body) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }

        const res = await fetch(BASE + path, opts);
        const data = res.status === 204 ? {} : await res.json().catch(() => ({}));

        if (!res.ok) {
            if (res.status === 401) { clearAuth(); }
            const err = new Error(data.message || 'Request failed (' + res.status + ')');
            err.status = res.status;
            err.errors = data.errors || {};
            throw err;
        }
        return data;
    }

    /* ---------- Auth ---------- */
    const register = (d) => request('/auth/register', { method: 'POST', body: d });
    const login = (d) => request('/auth/login', { method: 'POST', body: d });
    const logout = () => request('/auth/logout', { method: 'POST' });
    const me = () => request('/auth/me');
    const forgotPassword = (d) => request('/auth/forgot-password', { method: 'POST', body: d });
    const resetPassword = (d) => request('/auth/reset-password', { method: 'POST', body: d });

    /* ---------- Profile ---------- */
    const getProfile = () => request('/profile');
    const updateProfile = (d) => request('/profile', { method: 'PATCH', body: d });
    const uploadPhoto = (file) => {
        const fd = new FormData();
        fd.append('photo', file);
        return request('/profile/photo', { method: 'POST', formData: fd });
    };

    /* ---------- Subjects ---------- */
    const subjects = (params = '') => request('/subjects' + params);
    const subject = (id) => request('/subjects/' + id);

    /* ---------- Tests ---------- */
    const tests = (params = '') => request('/tests' + params);
    const test = (id) => request('/tests/' + id);

    /* ---------- Attempts ---------- */
    const startAttempt = (testId) => request('/attempts', { method: 'POST', body: { test_id: testId } });
    const resumeAttempt = (id) => request('/attempts/' + id);
    const getQuestion = (attemptId, qId) => request('/attempts/' + attemptId + '/questions/' + qId);
    const saveAnswer = (attemptId, qId, opt) => request('/attempts/' + attemptId + '/answers/' + qId, { method: 'PUT', body: { selected_option: opt } });
    const submitAttempt = (id) => request('/attempts/' + id + '/submit', { method: 'POST' });
    const attemptHistory = (params = '') => request('/attempts' + params);

    /* ---------- Results ---------- */
    const result = (id) => request('/results/' + id);
    const resultSummary = (id) => request('/results/' + id + '/summary');

    /* ---------- Solutions ---------- */
    const solutions = (id, params = '') => request('/results/' + id + '/solutions' + params);
    const solution = (id, qId) => request('/results/' + id + '/solutions/' + qId);

    return {
        token, setAuth, clearAuth, user, isLoggedIn, request,
        register, login, logout, me, forgotPassword, resetPassword,
        getProfile, updateProfile, uploadPhoto,
        subjects, subject, tests, test,
        startAttempt, resumeAttempt, getQuestion, saveAnswer, submitAttempt, attemptHistory,
        result, resultSummary,
        solutions, solution,
    };
})();
