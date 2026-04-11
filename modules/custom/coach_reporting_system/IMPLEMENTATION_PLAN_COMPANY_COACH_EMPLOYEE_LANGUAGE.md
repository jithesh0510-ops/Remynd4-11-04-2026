# Implementation Plan: Company / Coach / Employee Forms (CSV Import/Export) and Language Import/Export

## 1. Company, Coach, and Employee Management Forms

### 1.1 Goals

- **Company management:** List companies (users with role `company`), export to CSV, import from CSV (create/update companies and their program assignments).
- **Coach management:** List coaches (users with role `coach`), export to CSV, import from CSV (create/update coaches and their company assignments).
- **Employee management:** List employees (users with role `employee`), export to CSV, import from CSV (create/update employees and their company/coach/program assignments).

### 1.2 Suggested Routes and Permissions

| Feature | Route name | Path | Permission |
|--------|------------|------|------------|
| Company list/import/export | `coach_reporting_system.company_manage` | e.g. `/admin/coach-reporting/companies` | `administer coach reporting` or `administer users` |
| Coach list/import/export | `coach_reporting_system.coach_manage` | e.g. `/admin/coach-reporting/coaches` | same |
| Employee list/import/export | `coach_reporting_system.employee_manage` | e.g. `/admin/coach-reporting/employees` | same |

### 1.3 Company Form

- **List:** Table of companies (name, email, programs count, link to edit user).
- **Export CSV:** Columns e.g. `uid, name, mail, status, program_nids` (or one row per company–program pair). Use profile `field_select_questionnaire` for programs.
- **Import CSV:** Upload CSV; columns map to user fields (name, mail, password or leave empty) and profile (e.g. program IDs). Create user + company profile, or update existing by mail/uid. Validation: required columns, duplicate mail, valid program nids.

### 1.4 Coach Form

- **List:** Table of coaches (name, email, companies count).
- **Export CSV:** Columns e.g. `uid, name, mail, status, company_uids` (comma-separated).
- **Import CSV:** Create/update users with role coach and coach profile; set `field_company` from CSV (company UIDs or emails).

### 1.5 Employee Form

- **List:** Table of employees (name, email, company, coach, program).
- **Export CSV:** Columns e.g. `uid, name, mail, status, company_uid, coach_uid, program_nid` (or use emails/titles for readability).
- **Import CSV:** Create/update users with role employee and employee profile; set `field_company`, `field_coach`, `field_program` from CSV (IDs or identifiers).

### 1.6 Technical Approach

- Reuse patterns from existing `PrePostImportForm` and `BaseUploadForm`: `managed_file` for CSV, `validateCsvFile()`, role-based company filter if needed.
- Each “manage” page can be one form with:
  - A list (table render array or view).
  - Actions: “Export CSV” (submit → generate download), “Import CSV” (file upload + submit → parse and create/update entities).
- Use batch API for large imports to avoid timeouts.
- CSV format: one header row; document required columns and examples in the form description or a sample download.

---

## 2. Language Import/Export Feature

### 2.1 Goals

- **Export:** Extract translatable strings (e.g. interface strings used by the module or the site) into a format suitable for translation (CSV or PO-like).
- **Import:** Upload a file (CSV/PO) with translations and apply them (e.g. as locale overrides or custom translations).

### 2.2 Options in Drupal

- **Core Locale:** Drupal’s `locale` module stores translations in the database; it has no built-in CSV import/export in core. Contributed modules (e.g. “Locale CSV”, “Translation template extractor”) exist.
- **Custom approach in this module:**
  - **Export:** Generate a CSV with columns such as `source_string, context, language_code, translation`. Source can be collected from:
    - Custom strings (e.g. all `$this->t()` / `t()` in this module), or
    - A scan of the codebase for translatables, or
    - Drupal’s locale storage (`locale_source` / `locale_target`) for a given language.
  - **Import:** Parse CSV; for each row, create or update a translation (e.g. via `LocaleStorage` or `StringStorage` if using interface translation API). Validate language code and avoid overwriting core without warning.

### 2.3 Suggested Route and Permission

| Feature | Route name | Path | Permission |
|--------|------------|------|------------|
| Language import/export | `coach_reporting_system.language_manage` | e.g. `/admin/coach-reporting/language` | `administer languages` or `administer coach reporting` |

### 2.4 Dependencies

- **Locale module:** Enable `locale` if you want to read/write Drupal’s standard interface translations.
- **Language module:** Usually needed for configuring languages. No extra dependency if you only export/import custom CSV for use in your own code.

### 2.5 Implementation Outline

- One form with two sections (or two forms):
  1. **Export:** Select language (or “source only”), optional filters → submit → generate CSV download of strings/translations.
  2. **Import:** Upload CSV (and optionally select language) → validate → batch import into locale/custom storage.
- Document CSV format (columns, encoding UTF-8) and provide a sample export so translators know the template.

---

## 3. Implementation Order

1. **Company manage form** (list + export CSV + import CSV) – as reference for the other two.
2. **Coach manage form** (same pattern).
3. **Employee manage form** (same pattern; ensure “Add Employee” flow exists or add it).
4. **Language export** (generate CSV from locale/source).
5. **Language import** (parse CSV and write to locale/custom storage).

---

## 4. Files to Add (Summary)

- **Routes:** Add to `coach_reporting_system.routing.yml`: `company_manage`, `coach_manage`, `employee_manage`, `language_manage`.
- **Controllers:** Optional; if the form is the main content, use `_form` in routing and a single form class per feature.
- **Forms:**  
  - `CompanyManageForm.php` (list + export + import).  
  - `CoachManageForm.php`.  
  - `EmployeeManageForm.php`.  
  - `LanguageImportExportForm.php`.
- **Services:** Optional shared “CsvCompanyImport”, “CsvCoachImport”, “CsvEmployeeImport”, “LanguageExport/Import” services to keep forms thin.
- **Permissions:** Reuse `administer coach reporting` or add `manage companies`, `manage coaches`, `manage employees`, `manage language import export` if you need finer control.

This plan can be executed step by step; the review document (`USER_ROLES_AND_ASSIGNMENT_REVIEW.md`) and this plan together define how users are created/assigned and how the new features should behave.
