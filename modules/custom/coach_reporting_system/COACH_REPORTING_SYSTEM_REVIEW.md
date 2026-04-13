# Coach Reporting System – Module Review

Review date: 2025. Summary of findings, fixes applied, and recommendations.

---

## 1. Security & Access

### 1.1 Report result URL parameters
- **Fixed:** `report_type` from the query string is now restricted to `latest` or `overtime`. Any other value falls back to `latest`, avoiding unexpected behavior or injection.
- **Fixed:** `report_content` is filtered to the five allowed keys: `per_person`, `competency_analysis`, `on_job_performance`, `coaching_impact`, `one_to_one_coaching`. Unknown keys from the URL are ignored.
- **Access control:** `currentUserCanAccessReport()` correctly restricts access by role (admin, company, coach, employee). Entity load and redirect-on-failure are in place.

### 1.2 Sample file download
- **SampleFileController:** Filename is restricted to an allowlist; path traversal is not possible. Route requirement `filename: '[a-zA-Z0-9\-_]+\.csv'` matches the allowlist.
- **Note:** Allowed list uses `prepost-training-sample.csv`; ensure the file exists in `samples/` (it does in the repo).

### 1.3 Node route override
- **Route** `coach_reporting_system.node_override` uses path `/node/{node}` and can override core’s node view for that path. The controller only customizes `questionnaire` and `survey` bundles and delegates to the default node view for others. Entity access is enforced via `_entity_access: 'node.view'`. Document this override in deployment/onboarding so it’s clear that questionnaire/survey nodes use a custom view.

---

## 2. Code Quality & Correctness

### 2.1 Report form
- **Form ID:** `coach_reporting_system_report_form`; cache max-age 0; role-based company/coach/employee filtering is consistent.
- **Validation:** `validateForm()` checks company, program, coach, employee, report type, date range (for overtime), and at least one report content option. Errors are set with `setErrorByName()` and appear in standard Drupal messages (with `status_messages` in the controller).
- **Submit handlers:** `submitViewOnline` and `submitDownloadReport` only build params and redirect; no validation logic there (correct).
- **Libraries:** `report_form` now declares `core/once` so the inline JS that uses `once()` is guaranteed to have it.

### 2.2 Report result controller
- **Required params:** company, program, employee are validated; missing params trigger redirect with an error message.
- **Entity loading:** Company, program, employee (and coach when present) are loaded and checked; invalid IDs trigger redirect.
- **Exception handling:** `viewResult()` wraps logic in try/catch, logs the exception, and redirects with a user message.

### 2.3 Permissions
- Routes use `access coach reporting` for report and report result; `administer coach reporting` for prepost import, uploads, and sample download; `_role: coach` for session routes; `administer users` for add-coach/add-company.
- Permission “access coach reporting system” exists in `.permissions.yml` but is not used in routing; consider removing or using it consistently to avoid confusion.

---

## 3. Issues Fixed in This Review

1. **ReportResultController**
   - Validate `report_type`: allow only `latest` or `overtime`; otherwise use `latest`.
   - Whitelist `report_content`: only the five known keys are kept; others are dropped.

2. **report_form library**
   - Added dependency `core/once` so `Drupal.behaviors` using `once()` (e.g. crsAutoInit, crsFormValidation) work even if the theme does not load `once` elsewhere.

---

## 4. Recommendations (Optional)

### 4.1 Date parameters for overtime
- When `report_type === 'overtime'`, `from` and `to` are used in queries (e.g. `strtotime($from_date . ' 00:00:00')`). Consider validating that they match a safe date format (e.g. `Y-m-d`) and that `from <= to` before using them, to avoid odd behavior or edge cases.

### 4.2 Install / update hooks
- `coach_reporting_system_update_8001()` creates tables if missing. For Drupal 10, update hooks are typically named with a 10xxx prefix (e.g. `coach_reporting_system_update_10001`). If this module was never run on D8, consider adding a 10xxx update that ensures the same tables exist, and keep 8001 for existing installs that already ran it.

### 4.3 Unused permission
- Either use “access coach reporting system” in routing (if you want a separate permission) or remove it from `coach_reporting_system.permissions.yml` and rely on “access coach reporting” only.

### 4.4 Duplicate logic in submit handlers
- `submitViewOnline` and `submitDownloadReport` share the same parameter-building logic. Consider extracting a helper (e.g. `getReportRedirectParams(FormStateInterface $form_state, bool $for_download)`) to reduce duplication and keep redirect params in one place.

### 4.5 XSS in tab markup
- In `buildDynamicTabs()`, tab titles/descriptions are injected into HTML via `#markup`. The titles come from `$config['title']` and `$config['description']` (translated strings), so they are not user input. If you ever allow user-editable labels, ensure they are escaped (e.g. via `\Drupal\Component\Utility\Html::escape()` or render arrays).

---

## 5. File / Structure Overview

| Area              | Status / note |
|-------------------|----------------|
| Routing           | Permissions and route names consistent; node override is intentional. |
| ReportForm        | Validation and submit flow correct; libraries and messages in place. |
| ReportController  | Wrapper includes status_messages; form built via FormBuilder. |
| ReportResultController | Access check, entity load, param validation; report_type and report_content fixed. |
| SampleFileController   | Allowlist and route constraint; safe. |
| QuestionnaireController | nodeViewOverride only for questionnaire/survey; safe. |
| Permissions       | Used correctly; one unused permission. |
| Libraries         | report_form now has core/once; others fine. |
| .module           | Theme and form alters; hook_entity_operation_alter. |
| .install          | Schema and update_8001; consider 10xxx for D10. |

---

## 6. Summary

- **Security:** Access control and parameter handling are in good shape; `report_type` and `report_content` are now validated/whitelisted.
- **Stability:** Report form and result flow are consistent; exception handling and redirects are in place.
- **Maintainability:** Small improvements possible (shared redirect params, date validation, update hook naming, unused permission).

All critical and high-priority issues identified in this review have been addressed in code; the rest are optional improvements.
