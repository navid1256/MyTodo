# Task 8 report — current and future recurring-task edits

Status: implemented and committed.

## Implementation

- Added ownership-scoped `completeForSplit()` and `updatePausedRule()` repository helpers.
- Added transactional `RepeatService::updateThisAndFuture()` and `updateRule()` paths, including old-rule completion, future-incomplete cleanup, selected occurrence zero reassignment, reminder/template replacement, and initial 30-day generation.
- Added authenticated CSRF-protected `POST /api/repeat-rules/update`; `scope=future` now uses the series-split service while `scope=single` remains unchanged.
- Connected recurring-rule Edit to the shared task modal, preserved scope controls for task edits, and updated/removes affected DOM rows/tasks without reload.
- Added bilingual success messaging and frontend request coverage.

## Verification

- `php -l` passed for all changed PHP files.
- `php tests/Backend/repeat-backend.test.php` passed.
- `php tests/Backend/repeat-controller-contract.test.php` passed.
- `php tests/Backend/repeat-repository-contract.test.php` passed.
- `php tests/Backend/repeat-lifecycle.integration.php` safely skipped because no explicit `MYTODO_TEST_*` MySQL test DSN was configured.
- `node --test tests/Frontend/*.test.mjs` passed (11 tests).
- `git diff --check` passed; Git only reported the repository's existing LF-to-CRLF conversion warnings.

## Limitations

- Real MySQL locking/cascade assertions remain dependent on the isolated `_test` database credentials.
- Browser/HTTPS verification remains deferred to Task 9.

Commit: `a015486` — `Edit current and future recurring tasks`
