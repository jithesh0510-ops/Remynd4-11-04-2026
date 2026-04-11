# Report Form Functionality Analysis
## URL: `/reports`

### 📋 Form Overview

The Report Form is a cascading dropdown form that allows users to generate coaching reports by selecting:
1. **Company** → 2. **Program (Questionnaire)** → 3. **Coach** → 4. **Employee** → 5. **Report Type** → 6. **Report Content**

---

## 🔄 Form Flow & Dependencies

### Step 1: Company Selection
- **Field**: `company` (Select2)
- **Required**: ✅ Yes
- **AJAX**: Triggers `updateDependents()` callback
- **Dependencies**: None (first field)
- **Visibility**: Always visible
- **Role-based behavior**:
  - **Admin**: Can select any company
  - **Company**: Locked to their own company (disabled)
  - **Coach**: Only companies assigned to them
  - **Employee**: Only companies they belong to

### Step 2: Program (Questionnaire) Selection
- **Field**: `program` (Select2)
- **Required**: ✅ Yes
- **AJAX**: Triggers `updateDependents()` callback
- **Dependencies**: Requires `company` to be selected
- **Visibility**: Only visible when `company` has a value
- **Data Source**: `getQuestionnairesByCompany()` - Gets questionnaires from company profile's `field_select_questionnaire`
- **Form States**:
  ```php
  '#states' => [
    'visible' => [':input[name="company"]' => ['!value' => '']],
    'disabled' => [':input[name="company"]' => ['value' => '']],
  ]
  ```

### Step 3: Coach Selection
- **Field**: `coach` (Select2)
- **Required**: ✅ Yes
- **AJAX**: Triggers `updateDependents()` callback
- **Dependencies**: Requires `company` AND `program` to be selected
- **Visibility**: Only visible when `program` has a value
- **Options**: Includes "All Coaches" option + specific coaches
- **Role-based behavior**:
  - **Coach**: Locked to their own account (disabled)
  - **Employee**: Only coaches assigned to them
  - **Admin/Company**: All coaches for the selected company
- **Form States**:
  ```php
  '#states' => [
    'visible' => [':input[name="program"]' => ['!value' => '']],
    'disabled' => [':input[name="program"]' => ['value' => '']],
  ]
  ```

### Step 4: Employee Selection
- **Field**: `employee` (Select2)
- **Required**: ✅ Yes
- **AJAX**: Triggers `updateDependents()` callback
- **Dependencies**: Requires `company`, `program`, AND `coach` to be selected
- **Visibility**: Only visible when `coach` has a value
- **Data Source**: `getEmployeeByCompanyCoachProgram()` - Filters employees by:
  - Company (required)
  - Coach (if not "all")
  - Program (optional, currently commented out)
  - Status (active/inactive)
- **Form States**:
  ```php
  '#states' => [
    'visible' => [':input[name="coach"]' => ['!value' => '']],
    'disabled' => [':input[name="coach"]' => ['value' => '']],
  ]
  ```

### Step 5: Employee Status
- **Field**: `employee_status` (Radio buttons)
- **Options**: "Active" | "Inactive"
- **Default**: "active"
- **AJAX**: Triggers `updateDependents()` callback
- **Dependencies**: Requires `coach` to be selected
- **Visibility**: Only visible when `coach` has a value
- **Effect**: Filters employee list based on user account status

### Step 6: Report Type
- **Field**: `report_type` (Radio buttons)
- **Options**: 
  - "Latest Report" (default)
  - "Report over-time"
- **Dependencies**: Requires `employee` to be selected
- **Visibility**: Only visible when `employee` has a value
- **Form States**:
  ```php
  '#states' => ['visible' => [':input[name="employee"]' => ['!value' => '']]]
  ```

### Step 7: Date Range (Conditional)
- **Field**: `date_range` (DateRange or two Date fields)
- **Required**: ✅ Yes (only when report_type = "overtime")
- **Dependencies**: Requires `employee` AND `report_type = "overtime"`
- **Visibility**: Only visible when:
  - `employee` has a value
  - `report_type` = "overtime"
- **Form States**:
  ```php
  '#states' => [
    'visible' => [
      ':input[name="employee"]' => ['!value' => ''],
      ':input[name="report_type"]' => ['value' => 'overtime'],
    ],
    'required' => [':input[name="report_type"]' => ['value' => 'overtime']],
  ]
  ```

### Step 8: Report Content (Conditional)
- **Container**: `report_content`
- **Dependencies**: Requires `report_type` to be selected
- **Visibility**: Only visible when `report_type` has a value
- **Fields**:
  - "Select All" checkbox
  - Individual checkboxes:
    - `per_person`
    - `competency_analysis`
    - `on_job_performance`
    - `coaching_impact`
    - `one_to_one_coaching`

---

## 🎭 Role-Based Filtering Logic

### Administrator
- **Company**: All companies
- **Program**: All programs for selected company
- **Coach**: All coaches for selected company
- **Employee**: All employees matching filters

### Company Role
- **Company**: Locked to their own company (auto-selected, disabled)
- **Program**: Only programs assigned to their company profile
- **Coach**: All coaches assigned to their company
- **Employee**: All employees in their company

### Coach Role
- **Company**: Only companies assigned to them (via coach profile `field_company`)
- **Program**: Programs from selected company
- **Coach**: Locked to their own account (auto-selected, disabled)
- **Employee**: Only employees assigned to them

### Employee Role
- **Company**: Only companies they belong to (via employee profile `field_company`)
- **Program**: Programs from selected company
- **Coach**: Only coaches assigned to them (via employee profile `field_coach`)
- **Employee**: Locked to their own account (auto-selected)

---

## ⚙️ AJAX Functionality

### Callback: `updateDependents()`
- **Method**: `ReportForm::updateDependents()`
- **Action**: Rebuilds the form and returns the `dependents` container
- **Triggered by**: 
  - Company change
  - Program change
  - Coach change
  - Employee change
  - Employee status change

### Auto-Init JavaScript
- **Purpose**: Triggers change events for preselected values on page load
- **Targets**:
  - `select[name="company"]`
  - `select[name="program"]`
  - `select[name="coach"]`
  - `select[name="employee"]`
  - `input[name="employee_status"]` (radio buttons)

---

## 📊 Form States Summary

| Field | Visible When | Disabled When | Required |
|-------|-------------|---------------|----------|
| Company | Always | If user is Company role | ✅ |
| Program | Company selected | Company empty | ✅ |
| Coach | Program selected | Program empty | ✅ |
| Employee | Coach selected | Coach empty | ✅ |
| Employee Status | Coach selected | Coach empty | ❌ |
| Report Type | Employee selected | - | ❌ |
| Date Range | Employee selected AND Report Type = "overtime" | - | ✅ (if overtime) |
| Report Content | Report Type selected | - | ❌ |

---

## 🔍 Data Source Methods

### `getCompanyOptions(?array $limit_uids = NULL)`
- **Source**: User entities with role "company"
- **Filter**: Active users only
- **Limit**: Optional whitelist of UIDs
- **Returns**: Array of `uid => "Full Name (email)"`

### `getQuestionnairesByCompany(int $company_uid)`
- **Source**: Company profile → `field_select_questionnaire` (Paragraphs) → `field_questionnaire` (Node)
- **Filter**: Only nodes with bundle "questionnaire"
- **Returns**: Array of `nid => "Node Title"`

### `getCoachByCompany(int $company_uid)`
- **Source**: Coach profiles with `field_company` matching company
- **Filter**: Active profiles and active users with "coach" role
- **Returns**: Array of `uid => "Full Name (email)"`

### `getEmployeeByCompanyCoachProgram(int $company_uid, ?int $coach_uid, ?int $program_nid, string $status)`
- **Source**: Employee profiles with:
  - `field_company` = company_uid (required)
  - `field_coach` = coach_uid (optional, if not "all")
  - User status = active/inactive (based on status parameter)
- **Returns**: Array of `uid => "Full Name (email)"`

---

## 🐛 Potential Issues & Improvements

### Issues Identified:

1. **Auto-Init JavaScript HTML Encoding**
   - **Status**: ✅ Fixed (using `#markup` instead of `#value`)
   - **Issue**: `&&` operators were being HTML-encoded as `&amp;`
   - **Solution**: Changed to use `#markup` in html_tag render array

2. **Report Content Checkboxes**
   - **Issue**: "Select All" functionality not implemented in JavaScript
   - **Suggestion**: Add JavaScript to handle select all/deselect all

3. **Date Range Fallback**
   - **Status**: ✅ Handled (checks for datetime_range module)
   - **Fallback**: Uses two separate date fields if module not available

4. **Employee Status Default**
   - **Current**: Defaults to "active"
   - **Note**: Should trigger AJAX on initial load if preselected

5. **Program Filtering by Employee**
   - **Status**: Currently commented out in `getEmployeeByCompanyCoachProgram()`
   - **Note**: Program filtering exists but is not active

### Suggested Improvements:

1. **Add "Select All" JavaScript**
   ```javascript
   // Add to auto-init script
   var selectAll = document.querySelector('.selectall');
   if (selectAll) {
     selectAll.addEventListener('change', function() {
       var checkboxes = document.querySelectorAll('.individual');
       checkboxes.forEach(function(cb) {
         cb.checked = selectAll.checked;
       });
     });
   }
   ```

2. **Improve Error Handling**
   - Add validation messages for empty dependent fields
   - Show loading states during AJAX calls

3. **Cache Optimization**
   - Consider caching company/program/coach/employee options
   - Reduce database queries on form rebuilds

4. **Accessibility**
   - Ensure proper ARIA labels for dependent fields
   - Add screen reader announcements for AJAX updates

5. **Form Validation**
   - Add client-side validation before submit
   - Validate date range (from < to)

---

## ✅ Form Submission

### Submit Handlers:

1. **View Results Online** (`submitViewOnline`)
   - Collects all form values
   - Builds query parameters
   - Redirects to `/reports/result` with query params

2. **Download Report** (`submitDownloadReport`)
   - Currently same as `submitViewOnline`
   - **Note**: Should be updated to generate file download

### Parameters Passed:
- `company`
- `program`
- `coach` (or "all")
- `employee`
- `report_type` ("latest" or "overtime")
- `report_content[]` (array of selected checkboxes)
- `from` / `to` (if report_type = "overtime")

---

## 🧪 Testing Checklist

- [ ] Admin can select any company
- [ ] Company role sees only their company (locked)
- [ ] Coach role sees only assigned companies
- [ ] Employee role sees only their companies
- [ ] Program dropdown populates after company selection
- [ ] Coach dropdown populates after program selection
- [ ] Employee dropdown populates after coach selection
- [ ] Employee status filters employee list
- [ ] Report type shows only after employee selection
- [ ] Date range shows only for "overtime" report type
- [ ] Report content checkboxes show after report type selection
- [ ] AJAX callbacks work correctly
- [ ] Auto-init triggers preselected values
- [ ] Form validation prevents submission with empty required fields
- [ ] Submit buttons redirect correctly

---

## 📝 Notes

- Form uses Select2 for enhanced dropdowns
- All dependent fields are in a container with ID `dependent-wrapper`
- Form state is preserved across AJAX rebuilds
- Auto-init script ensures preselected values trigger cascading updates
- Role-based filtering ensures users only see relevant data

