# Coach, Company, and Employee – Login, Creation, and Assignment Review

## 1. Overview

The Coach Reporting System uses three **user roles** (company, coach, employee) and the **Profile** module. Each role has a matching **profile type** (company, coach, employee) that stores assignments (company → programs; coach → companies; employee → company, coach, program).

---

## 2. How Users Log In

- **Login:** All three (Company, Coach, Employee) log in via the standard Drupal login (e.g. `/user/login`). There are no separate login URLs per role.
- **Permissions after login:**
  - **Company:** Can access reports for their own company only (filtered by their user ID).
  - **Coach:** Can access reports for companies they are assigned to (via coach profile `field_company`).
  - **Employee:** Can access reports only for themselves as employee, and only for companies they are assigned to (via employee profile `field_company`).
- **Administrator:** Can access all companies, coaches, and employees and create Coach/Company users via the dedicated add forms.

---

## 3. How Users Are Created

### 3.1 Company

| Item | Detail |
|------|--------|
| **URL** | `/admin/people/add-company` |
| **Route** | `coach_reporting_system.add_company` |
| **Permission** | `administer users` |
| **Controller** | `CompanyAddController::build()` |
| **Flow** | Controller creates a new `User` with `status = 1`, adds role **company**, then returns the entity’s **register** form (same as user registration form display). |
| **Form alter** | `coach_reporting_system_form_user_register_form_alter()` runs on this route and: (1) locks the role to **company** only, (2) hides all profile fields except **company_profiles**, (3) adds validate/submit handlers to enforce company role and strip other profile values. |
| **Result** | New user with role **company** and (if the form has it) a **company** profile. Company profile typically has **field_select_questionnaire** (programs/questionnaires assigned to that company). |

### 3.2 Coach

| Item | Detail |
|------|--------|
| **URL** | `/admin/people/add-coach` |
| **Route** | `coach_reporting_system.add_coach` |
| **Permission** | `administer users` |
| **Controller** | `CoachAddController::build()` |
| **Flow** | Same pattern: new `User`, add role **coach**, return **register** form. |
| **Form alter** | Same hook: on add-coach route it locks role to **coach**, shows only **coach_profiles**, and enforces coach role on submit. |
| **Result** | New user with role **coach** and (if the form has it) a **coach** profile. Coach profile has **field_company** (entity reference to User) – one or more companies the coach is assigned to. |

### 3.3 Employee

| Item | Detail |
|------|--------|
| **URL** | There is **no** dedicated route like `add_company` / `add_coach` for employees. |
| **Creation** | Employees are created either: (1) via Drupal’s standard “Add user” (`/admin/people/create`) with role **employee** and then given an **employee** profile, or (2) via a custom flow that uses the “register users as employee” permission (if implemented elsewhere). |
| **Profile** | Employee profile type **employee** has: **field_company** (User reference), **field_coach** (User reference), **field_program** (Node reference – questionnaire). These link the employee to one company, optionally one coach, and one program. |

**Gap:** There is no dedicated “Add Employee” form in this module (no route, no controller, no form alter). Adding one (e.g. `/admin/people/add-employee`) with role locked to employee and only **employee_profiles** visible would mirror Company/Coach and make assignment consistent.

---

## 4. How Assignments Work

### 4.1 Company

- **Stored in:** User has role **company**. Optional **company** profile.
- **Profile fields (typical):**  
  - **field_select_questionnaire** – Paragraphs or entity reference to **programs (questionnaires)** that this company offers.  
- **Used in:** Report form “Select a Program” – only programs from the selected company’s profile are listed (via `getQuestionnairesByCompany()`).

### 4.2 Coach

- **Stored in:** User has role **coach**. Optional **coach** profile.
- **Profile fields (typical):**  
  - **field_company** – Entity reference to **User** (company). Can be multiple; coach is “assigned” to those companies.  
- **Used in:** Report form “Select Company” – for a coach, only companies in their profile’s `field_company` are shown. Same in Start Session and Performance Dashboard.

### 4.3 Employee

- **Stored in:** User has role **employee**. Optional **employee** profile.
- **Profile fields (typical):**  
  - **field_company** – User reference (company the employee belongs to).  
  - **field_coach** – User reference (optional; coach assigned to this employee).  
  - **field_program** – Node reference (questionnaire/program).  
- **Used in:** Report form “Select Employee” – employees are loaded by company + program + coach via `getEmployeeByCompanyCoachProgram()`, which uses employee profiles (company, coach, program).

---

## 5. Summary Table

| Role     | Created via                          | Profile type | Key assignment fields              |
|----------|--------------------------------------|--------------|------------------------------------|
| Company  | `/admin/people/add-company`          | company      | field_select_questionnaire (programs) |
| Coach    | `/admin/people/add-coach`            | coach        | field_company (companies)          |
| Employee | Standard add user / no dedicated form| employee     | field_company, field_coach, field_program |

---

## 6. Recommendations

1. **Add an “Add Employee” flow** (route + controller + form alter) similar to Add Company / Add Coach, so employees are created and assigned in one place.
2. **Central management forms** for each role (list + edit assignments) with **CSV import/export** to bulk-create and bulk-update companies, coaches, and employees (see implementation plan below).
3. **Language/translation:** Add a **Language Import/Export** feature (e.g. export interface strings or locale files to CSV/PO, import translations) for translating the app (see implementation plan below).
