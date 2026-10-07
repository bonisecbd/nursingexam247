# Documentation Audit Report

## Summary

This audit reviewed all documentation files for scope consistency, malformed content, schema contradictions, test arithmetic, and missing requirements.

## Findings and resolutions

| Finding | Resolution |
|---|---|
| Test schema assumed one subject per test | Added `test_subjects` pivot table for multi-subject tests |
| Subject-bank minimum and test allocation conflicted | Clarified that each subject needs at least 10 bank questions, while a 100-question test uses the allocation below |
| 100-question allocation did not total 100 | Corrected allocation to total exactly 100 |
| Duplicate `duration_minutes` column | Removed duplicate column |
| Corrupted dashboard topic emoji | Replaced with a valid topic icon |
| CSV import was not specified | Added CSV columns, validation, import API, and automatic generation rules |
| Unlock semantics were vague | Defined Test 01 → Test 02 → Test 03 sequential unlock behavior |
| Negative marking formula was not connected to test configuration | Added `negative_marking`, `is_negative_marking_enabled`, scoring formula, and API contract |

## Verified requirements

- 15 required Bengali subjects
- 100-question model test
- 100 marks
- 60-minute duration
- Negative marking `-0.25`
- Admin-created test generation
- CSV import
- Admin step-by-step workflow
- Sequential unlock
- Student result analysis

## Validation evidence

- 10 documentation files found
- All documents are non-empty
- All fenced code blocks are balanced
- Subject allocation rows: 15
- Question allocation total: 100
- `duration_minutes` schema rows: 1
- Corrupted characters: 0 in current dashboard documentation

## Remaining implementation checklist

- Implement Laravel migrations for all tables.
- Add CSV parser and validation.
- Add auto-test generation service.
- Add admin UI for import and test creation.
- Add student unlock-api checks.
- Add tests for scoring and CSV import.
