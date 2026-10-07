# Nursing Model Test Content and Test Rules

## 1. Required subjects in Bengali

1. Nursing Fundamentals
2. Anatomy & Physiology
3. Medical-Surgical Nursing
4. Pharmacology
5. Community Health Nursing
6. Child Health Nursing
7. Maternal & Child Health
8. Psychiatric Nursing
9. Nutrition
10. General Knowledge
11. Bangladesh Affairs
12. English
13. Mathematics
14. ICT
15. Previous Nursing Questions

প্রতিটি subject-র question bank-এ minimum ১০টি MCQ থাকবে। A model test will select questions from multiple subjects, not require exactly ১০ questions from every subject. The default ১০০-question model test will distribute ১০০ questions across all ১৫ subjects according to the allocation below. Subject name, code, topic, difficulty, and language metadata থাকবে।

## 2. Test format

- Total questions: 100
- Total marks: 100
- Duration: 60 minutes
- Question type: MCQ only
- Options: 4 options for each question
- Passing score: default 50 marks
- Negative marking: enabled
- Negative mark per incorrect answer: `-0.25`
- Unanswered question: 0 mark

## 3. Scoring formula

For each question:

- Correct answer: `+1`
- Incorrect answer: `-0.25`
- Unanswered: `0`
- Total marks: sum of all question marks

`Total score = correct_count × 1 + incorrect_count × (-0.25) + unanswered_count × 0`

`Percentage = (Total score ÷ 100) × 100`

`Pass status = Percentage >= Passing Score`

Example:

- Correct: 70
- Incorrect: 10
- Unanswered: 20
- Total score: `70 - 2.5 = 67.5`
- Percentage: `67.5%`

## 4. Test creation rules

Admin পরীক্ষার creation process step-by-step হবে:

1. Test title, code, description, and subject selection.
2. Test duration, question count, passing score, and negative marking configuration.
3. Question bank selection from the 15 required subjects.
4. Question count per subject configuration.
5. Difficulty selection: Easy, Medium, Hard.
6. Question ordering and sequence configuration.
7. Review of selected questions.
8. Test status set to Draft.
9. Test published by admin.
10. Test unlock configuration.

## 5. Sequential unlock rule

If model test `01` is completed, then model test `02` will be unlocked automatically. If test `02` is opened, test `03` remains locked until `02` is completed.

Suggested fields:

- `sequence_no`
- `unlock_after_attempt_id`
- `is_published`
- `is_unlocked`
- `unlock_rule`

Unlock rule values:

- `none`
- `previous_completed`
- `previous_score_threshold`

Default rule: `previous_completed`.

For test `01`, no previous test is required. For test `02`, previous test completion is needed. For test `03`, test `02` completion is needed.

## 6. Admin test access control

Admin can:

- Create a test.
- Set test status to draft.
- Assign a test to a subject.
- Select questions from the question bank.
- Configure 100 questions and marking rules.
- Publish the test.
- Unlock or lock the test.
- Set the required previous test.
- View active and completed attempts.
- Adjust the test configuration.

Students can only see unlocked tests.

## 7. User-facing test screen

- Subject and test number displayed.
- 60-minute timer displayed.
- Progress: Question 1 of 100.
- Question number and subject label.
- Four MCQ options.
- Previous, Next, Save, and Submit controls.
- Negative marking visible in the test instructions.
- Auto-save of selected answers.
- Automatic submission when timer is exhausted.

## 8. Requirement for Bengali content

- All nursing questions must be in Bengali.
- English terms may be included where necessary, but the main explanation must be in Bengali.
- Question bank must contain nursing-specific contexts.
- The top-level subject labels must be exactly as listed.
- A question must have a Bengali explanation after submission.

## 9. Test content allocation

Suggested default allocation for a 100-question test:

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

## 10. Required question-level structure

Each question should have:

- Subject
- Topic
- Question text
- Option A
- Option B
- Option C
- Option D
- Correct option
- Explanation
- Difficulty
- Tags
- Language: Bengali
- Answers should be hidden until submission

## 11. Admin workflow guidance

Admin dashboard flow:

1. Open Test Manager.
2. Select Create New Test.
3. Enter title and test code.
4. Select subject and required question count.
5. Configure duration, passing score, and negative marking.
6. Select questions from the question bank.
7. Set test order and unlock rule.
8. Preview the test.
9. Publish the test.
10. View student attempts and results.

The admin should be able to change any of these values before publishing.

## 12. Student workflow guidance

1. Student logs in.
2. Student sees unlocked tests only.
3. Student selects a test.
4. Test starts with a 60-minute timer.
5. Student solves 100 questions.
6. Negative marking is applied to incorrect answers.
7. Student submits the test.
8. The system calculates score and analysis.
9. Student sees result and weak areas.
10. The next sequential test unlocks after completion.

## 13. Accepted test status values

- Draft
- Scheduled
- Published
- Locked
- Completed
- Expired
- Submitted

## 14. Acceptance criteria

- The system contains all 15 subjects.
- A 100-question test can be created.
- Each test lasts 60 minutes.
- Negative marking is enabled by default.
- Correct, incorrect, and unanswered answers are scored separately.
- Admin can unlock tests in sequence.
- Student cannot access a locked test.
- Every answer displays the correct explanation after submission.
- Result shows subject-wise score and topic-wise weakness.
