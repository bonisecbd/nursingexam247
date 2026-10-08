import '../styles/landing.css'

import { useAuth } from '../lib/auth'
import { Link } from '../lib/router'
import Icon from '../components/Icon'

const SUBJECTS = [
  { name: 'Nursing Fundamentals', bn: 'নার্সিং ফান্ডামেন্টালস', short: 'NF' },
  { name: 'Anatomy & Physiology', bn: 'শারীরস্থান ও শারীরক্রিয়া', short: 'AP' },
  { name: 'Medical-Surgical Nursing', bn: 'মেডিকেল-সার্জিক্যাল নার্সিং', short: 'MS' },
  { name: 'Pharmacology', bn: 'ফার্মাকোলজি', short: 'PH' },
  { name: 'Community Health Nursing', bn: 'কমিউনিটি হেলথ নার্সিং', short: 'CH' },
  { name: 'Child Health Nursing', bn: 'শিশু স্বাস্থ্য নার্সিং', short: 'SH' },
  { name: 'Maternal & Child Health', bn: 'মাতৃ ও শিশু স্বাস্থ্য', short: 'MC' },
  { name: 'Psychiatric Nursing', bn: 'সাইকিয়াট্রিক নার্সিং', short: 'PS' },
  { name: 'Nutrition', bn: 'পুষ্টিবিদ্যা', short: 'NU' },
  { name: 'General Knowledge', bn: 'সাধারণ জ্ঞান', short: 'GK' },
  { name: 'Bangladesh Affairs', bn: 'বাংলাদেশ বিষয়াবলি', short: 'BD' },
  { name: 'English', bn: 'ইংরেজি', short: 'EN' },
  { name: 'Mathematics', bn: 'গণিত', short: 'MA' },
  { name: 'ICT', bn: 'তথ্য ও যোগাযোগ প্রযুক্তি', short: 'IC' },
  { name: 'Previous Nursing Questions', bn: 'পূর্ববর্তী নার্সিং প্রশ্ন', short: 'PQ' },
]

const FEATURES = [
  {
    icon: 'clock',
    title: 'Timed model tests',
    text: 'A live 60-minute countdown with warnings at 5 minutes and 1 minute, then automatic submission at 0:00.',
  },
  {
    icon: 'target',
    title: '−0.25 negative marking',
    text: 'Every wrong answer costs a quarter mark, exactly like the real nursing admission test. Skipped questions cost nothing.',
  },
  {
    icon: 'chart',
    title: 'Instant result',
    text: 'Your score ring, pass/fail status, correct, wrong and skipped counts appear the moment you submit.',
  },
  {
    icon: 'book',
    title: 'Full solution',
    text: 'Review every question with the correct option highlighted and a clear Bengali explanation.',
  },
]

const STEPS = [
  {
    number: '01',
    title: 'Create your free account',
    text: 'Register in under a minute and get your personal dashboard with progress stats.',
  },
  {
    number: '02',
    title: 'Attempt a 100-question test',
    text: 'Answer with a question palette, flag doubts, and let your answers autosave as you go.',
  },
  {
    number: '03',
    title: 'Review and improve',
    text: 'Check your score, read the solutions, and unlock the next test once you finish this one.',
  },
]

export default function Landing() {
  const { user } = useAuth()

  return (
    <main className="landing">
      <section className="landing-hero">
        <div className="landing-hero-inner">
          <div>
            <span className="landing-eyebrow">
              <Icon name="graduation" size={14} /> 15 subjects · 100 MCQ · 60 minutes
            </span>
            <h1 className="landing-title">
              Your nursing exam prep, <span>fully covered.</span>
              <span className="bengali">নার্সিং পরীক্ষার পূর্ণাঙ্গ প্রস্তুতি — এক জায়গায়।</span>
            </h1>
            <p className="landing-lead">
              Practice with realistic Bengali model tests, honest negative marking, instant scoring and
              question-by-question explanations — built for nursing candidates in Bangladesh.
            </p>
            <div className="landing-actions">
              <Link to={user ? '/tests' : '/register'} className="btn btn-primary btn-lg">
                {user ? 'Start a model test' : 'Start learning for free'} <Icon name="arrow" size={18} />
              </Link>
              <a href="#features" className="btn btn-outline btn-lg">
                See how it works
              </a>
            </div>
            <div className="landing-stats">
              <div>
                <strong>15</strong>
                <span>Nursing &amp; general subjects</span>
              </div>
              <div>
                <strong>100</strong>
                <span>MCQs per model test</span>
              </div>
              <div>
                <strong>60 min</strong>
                <span>Exam timer with auto-submit</span>
              </div>
              <div>
                <strong>−0.25</strong>
                <span>Negative mark per wrong answer</span>
              </div>
            </div>
          </div>

          <div className="exam-preview" aria-hidden="true">
            <div className="exam-preview-top">
              <span>
                <Icon name="clock" size={14} /> Model Test 01
              </span>
              <span className="preview-timer">42:18</span>
            </div>
            <p className="preview-question">
              রক্ত পরিসঞ্চালনে অক্সিজেন বহন করে কোন উপাদান?
            </p>
            <div className="preview-options">
              <div className="preview-option">
                <b>A</b> অণুচক্রিকা (Platelets)
              </div>
              <div className="preview-option is-correct">
                <b>B</b> হিমোগ্লোবিন (Haemoglobin)
              </div>
              <div className="preview-option">
                <b>C</b> প্লাজমা (Plasma)
              </div>
              <div className="preview-option">
                <b>D</b> শ্বেত রক্তকণিকা (WBC)
              </div>
            </div>
            <div className="preview-meta">
              <span>Question 12 of 100</span>
              <span>Answer saved ✓</span>
            </div>
          </div>
        </div>
      </section>

      <section className="landing-section" id="features">
        <div className="section-head">
          <div>
            <span className="kicker">Exam rules built in</span>
            <h2>Everything a real test expects</h2>
          </div>
          <p>The same rules you will face on exam day, practised in a calm, distraction-free interface.</p>
        </div>
        <div className="feature-grid">
          {FEATURES.map((feature) => (
            <article className="feature-card" key={feature.title}>
              <span className="feature-icon">
                <Icon name={feature.icon} size={20} />
              </span>
              <h3>{feature.title}</h3>
              <p>{feature.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="landing-section landing-section-alt" id="subjects">
        <div className="landing-section" style={{ padding: 0 }}>
          <div className="section-head">
            <div>
              <span className="kicker">Syllabus coverage</span>
              <h2>15 subjects, one question bank</h2>
            </div>
            <p>Every model test distributes its 100 questions across these subjects in Bengali.</p>
          </div>
          <div className="subject-tiles">
            {SUBJECTS.map((subject, index) => (
              <article className="subject-tile" key={subject.name}>
                <div className="subject-tile-top">
                  <span className="subject-tile-number">{String(index + 1).padStart(2, '0')}</span>
                  <span className="subject-tile-badge">{subject.short}</span>
                </div>
                <h3>{subject.name}</h3>
                <p>{subject.bn}</p>
              </article>
            ))}
          </div>
          <div className="rules-strip" style={{ marginTop: 24 }}>
            <span className="rule-chip">
              <Icon name="check" size={15} /> Passing score 50%
            </span>
            <span className="rule-chip">
              <Icon name="check" size={15} /> +1 correct · −0.25 wrong · 0 skipped
            </span>
            <span className="rule-chip">
              <Icon name="lock" size={15} /> Test 02 unlocks after test 01
            </span>
            <span className="rule-chip">
              <Icon name="shield" size={15} /> Answers hidden until you submit
            </span>
          </div>
        </div>
      </section>

      <section className="landing-section" id="how-it-works">
        <div className="section-head">
          <div>
            <span className="kicker">How it works</span>
            <h2>Three steps from signup to score</h2>
          </div>
        </div>
        <div className="how-steps">
          {STEPS.map((step) => (
            <article className="how-step" key={step.number}>
              <span className="how-step-number">{step.number}</span>
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="landing-cta">
        <div className="landing-cta-card">
          <div>
            <h2>Ready when you are.</h2>
            <p>Create a free account and start your first 100-question model test today.</p>
          </div>
          <Link to="/register" className="btn btn-lg">
            Create free account <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
    </main>
  )
}
