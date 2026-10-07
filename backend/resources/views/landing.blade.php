<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseExam247 — Nursing Job Preparation Model Test</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

    <!-- Header -->
    <header class="landing-header">
        <div class="container" style="display:flex;justify-content:space-between;align-items:center;height:64px">
            <a href="/" class="logo"><span class="icon">⚕</span> NurseExam247</a>
            <div class="flex gap-1">
                <a href="/login" class="btn btn-ghost">Login</a>
                <a href="/register" class="btn btn-primary">Register Free</a>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="landing-hero">
        <div class="container">
            <div>
                <div class="hero-badge">🏥 Model Test Platform</div>
                <h1 class="hero-title">Nursing Job<br><span>Preparation</span></h1>
                <p class="hero-desc">
                    Timed MCQ model tests with negative marking, subject-wise analysis,
                    weak-area detection and downloadable reports. 15 nursing subjects — fully in Bengali.
                </p>
                <div class="hero-actions">
                    <a href="/register" class="btn btn-primary btn-lg">Start Free Test →</a>
                    <a href="/login" class="btn btn-outline btn-lg">Login</a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="num">100</div>
                        <div class="label">Questions</div>
                    </div>
                    <div class="hero-stat">
                        <div class="num">15</div>
                        <div class="label">Subjects</div>
                    </div>
                    <div class="hero-stat">
                        <div class="num">60</div>
                        <div class="label">Minutes</div>
                    </div>
                    <div class="hero-stat">
                        <div class="num">-0.25</div>
                        <div class="label">Negative Mark</div>
                    </div>
                </div>
            </div>
            <div>
                <div class="mock-device">
                    <div class="mock-test-header">
                        <div>
                            <div class="text-sm text-muted">Model Test 01</div>
                            <div style="font-weight:700">Nursing Fundamentals</div>
                        </div>
                        <div class="mock-timer">⏱ 45:32</div>
                    </div>
                    <div class="mock-question">Q 12. নিচের কোনটি রোগীর ভিত্তিক যত্নের মূলনীতি?</div>
                    <div class="mock-option">A. সাধারণ যত্ন</div>
                    <div class="mock-option correct">B. রোগীর চাহিদা অনুযায়ী যত্ন ✅</div>
                    <div class="mock-option wrong">C. সময়ভিত্তিক যত্ন ✗</div>
                    <div class="mock-option">D. বিভাগভিত্তিক যত্ন</div>
                    <div class="flex-between mt-2">
                        <button class="btn btn-outline btn-sm">← Previous</button>
                        <span class="text-sm text-muted">12 / 100</span>
                        <button class="btn btn-primary btn-sm">Next →</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="landing-section">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose NurseExam247?</h2>
                <p>Everything you need to pass nursing job exams</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon blue">⏱</div>
                    <h3>Timed Tests</h3>
                    <p>60-minute exam with live timer and auto-submit</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon green">📊</div>
                    <h3>Instant Result</h3>
                    <p>Score, percentage, correct/wrong/skip analysis</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon orange">📖</div>
                    <h3>Full Solution</h3>
                    <p>Correct answer with detailed Bengali explanation</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon red">🎯</div>
                    <h3>Negative Marking</h3>
                    <p>Real exam pattern: −0.25 for each wrong answer</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Subjects -->
    <section class="landing-section" style="background:var(--surface)">
        <div class="container">
            <div class="section-header">
                <h2>📚 15 Nursing Subjects</h2>
                <p>All subjects covered from the official nursing syllabus</p>
            </div>
            <div class="subject-grid">
                <div class="subject-card"><div class="subject-emoji">🩺</div><h4>Nursing Fundamentals</h4><span class="q-count">8 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🦴</div><h4>Anatomy & Physiology</h4><span class="q-count">8 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🏥</div><h4>Med-Surg Nursing</h4><span class="q-count">8 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">💊</div><h4>Pharmacology</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🏘</div><h4>Community Health</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">👶</div><h4>Child Health</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🤰</div><h4>Maternal Health</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🧠</div><h4>Psychiatric Nursing</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🥗</div><h4>Nutrition</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">📝</div><h4>General Knowledge</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🇧🇩</div><h4>Bangladesh Affairs</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🔤</div><h4>English</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">🔢</div><h4>Mathematics</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">💻</div><h4>ICT</h4><span class="q-count">6 Qs</span></div>
                <div class="subject-card"><div class="subject-emoji">📋</div><h4>Previous Questions</h4><span class="q-count">10 Qs</span></div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="landing-section">
        <div class="container">
            <div class="landing-cta">
                <h2>Start Your Nursing Career Preparation Today</h2>
                <p>Free registration. Unlimited practice tests. Real exam experience.</p>
                <a href="/register" class="btn btn-lg">Create Free Account →</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="container">
            <div>
                <div class="logo" style="color:#fff;margin-bottom:10px"><span class="icon">⚕</span> NurseExam247</div>
                <p style="max-width:300px;font-size:.9rem">Nursing Job Preparation MCQ Model Test Software. Practice timed tests with real exam pattern.</p>
            </div>
            <div class="footer-links">
                <a href="/login">Login</a>
                <a href="/register">Register</a>
                <a href="#!">FAQ</a>
                <a href="#!">Contact</a>
            </div>
        </div>
        <div class="container footer-copy">© 2026 NurseExam247. All rights reserved.</div>
    </footer>

</body>
</html>
