# CSV Import and Automatic Model Test Creation

## 1. Purpose

Admin can import nursing questions from CSV and create a ready-to-use model test in a few steps. The system will validate CSV data, create or update questions, and automatically build the test using the configured distribution.

## 2. Required CSV columns

| Column | Required | Example |
|---|---|---|
| subject | Yes | Nursing Fundamentals |
| topic | Yes | Cardiovascular System |
| question_text | Yes | Which structure carries oxygenated blood? |
| option_a | Yes | Left ventricle |
| option_b | Yes | Right ventricle |
| option_c | Yes | Aorta |
| option_d | Yes | Pulmonary artery |
| correct_option | Yes | A |
| difficulty | No | Medium |
| explanation | No | The left ventricle pumps blood through the aorta. |
| tags | No | heart, circulation |

The correct option must be one of `A`, `B`, `C`, or `D`.

## 3. Supported subjects

- Nursing Fundamentals
- Anatomy & Physiology
- Medical-Surgical Nursing
- Pharmacology
- Community Health Nursing
- Child Health Nursing
- Maternal & Child Health
- Psychiatric Nursing
- Nutrition
- General Knowledge
- Bangladesh Affairs
- English
- Mathematics
- ICT
- Previous Nursing Questions

Unknown subjects must be rejected with a clear validation error.

## 4. Import workflow

1. Admin opens Questions → Import CSV.
2. Admin selects the CSV file.
3. Laravel validates the file extension, header, and row structure.
4. The system checks duplicate question text and subject/topic.
5. Valid rows are inserted as questions.
6. Invalid rows are shown in an import report.
7. Admin can preview the imported rows before saving.
8. Admin creates a model test and selects the imported question bank.
9. The system auto-generates the test using the 100-question distribution.
10. Admin reviews and publishes the test.

## 5. Automatic model test generation

For a 100-question model test:

- Total questions: 100
- Total marks: 100
- Duration: 60 minutes
- Negative marking: enabled
- Incorrect answer penalty: `-0.25`
- Passing score: 50 by default
- Questions are distributed across all 15 subjects.

Default allocation:

| Subject | Questions |
|---|---:|
| Nursing Fundamentals | 8 |
| Anatomy & Physiology | 8 |
| Medical-Surgical Nursing | 8 |
| Pharmacology | 6 |
| Community Health Nursing | 6 |
| Child Health Nursing | 6 |
| Maternal & Child Health | 6 |
| Psychiatric Nursing | 6 |
| Nutrition | 6 |
| General Knowledge | 6 |
| Bangladesh Affairs | 6 |
| English | 6 |
| Mathematics | 6 |
| ICT | 6 |
| Previous Nursing Questions | 10 |

Total: 100

## 6. Automatic creation API

`POST /api/exams/auto-create`

Request:

```json
{
  "title": "Nursing Model Test 01",
  "code": "NMT-001",
  "duration_minutes": 60,
  "question_count": 100,
  "passing_score": 50,
  "negative_marking": -0.25,
  "question_bank_id": 1,
  "sequence_no": 1,
  "unlock_rule": "previous_completed"
}
```

The server must select questions from the question bank, assign each question to a subject, and save the result in `test_subjects` and `test_questions`.

## 7. CSV upload API

`POST /api/questions/import`

Request fields:

- `file`: CSV file
- `subject_codes`: optional mapping for subjects
- `create_missing_subjects`: optional boolean
- `skip_duplicates`: optional boolean

Response:

```json
{
  "imported": 20,
  "skipped": 2,
  "errors": [],
  "questions": []
}
```

## 8. Validation rules

- File extension must be `.csv`.
- Required headers must exist.
- Every row must contain a subject, topic, question, and four options.
- Correct option must be A–D.
- A question must have a unique combination of subject, topic, and question text.
- Unknown subject names must fail validation.
- Questions with incomplete explanations are allowed if an explanation is optional.

## 9. Exam status lifecycle

- Draft: Admin editing or previewing
- Scheduled: Ready for future release
- Published: Available to students
- Locked: Sequential unlock condition not satisfied
- Completed: Student submitted the exam
- Expired: Timer ended without submission

## 10. Sequential unlock checklist

- Test 01 can be opened immediately when published.
- Test 02 remains locked until Test 01 is completed.
- Test 03 remains locked until Test 02 is completed.
- Admin can manually unlock a test when required.
- Students cannot access a locked test through the API.

## 11. Ready-to-use checklist

- [ ] 15 required subjects exist.
- [ ] Question bank has at least 10 MCQs per subject.
- [ ] CSV import validates subject, topic, options, and correct answer.
- [ ] Admin can create the test manually.
- [ ] Admin can auto-generate the 100-question test.
- [ ] 60-minute timer is configured.
- [ ] Negative marking is `-0.25`.
- [ ] Test is published and ready.
- [ ] Test 02 unlocks after Test 01 completion.
- [ ] Student results and analysis are generated after submission.
