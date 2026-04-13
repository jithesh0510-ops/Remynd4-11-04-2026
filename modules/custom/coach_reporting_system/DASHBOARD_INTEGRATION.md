# Performance Dashboard - Integration with Reports System

## ✅ What's Been Updated

The Performance Dashboard now uses the **exact same logic** as the Reports page (`/reports`) for Company and Coach selection, ensuring consistent data and user experience.

## 🔗 Integration Points

### 1. Company Dropdown
**Uses:** Same logic as `ReportForm::getCompanyOptions()`

**Features:**
- Lists all active users with 'company' role
- Shows full name (if available) with email
- Sorted alphabetically
- Same data source as `/reports` page

**Code Location:**
```php
PerformanceDashboardController::getCompanies()
```

### 2. Coach Dropdown  
**Uses:** Same logic as `ReportForm::getCoachByCompany()`

**Features:**
- Dynamically loads based on selected company
- Gets coaches from `coach` profiles with `field_company`
- Shows full name with email
- Only active coaches
- Same filtering as `/reports` page

**Code Location:**
```php
PerformanceDashboardController::getCoaches($company_uid)
```

### 3. Program List (Accordions)
**Uses:** Same logic as `ReportForm::getQuestionnairesByCompany()`

**Features:**
- Gets programs from company's `field_assigned_questionnaires`
- Filters by coach (only programs with sessions for that coach)
- Only published programs
- Displays as expandable accordions
- Each accordion contains full dashboard

**Code Location:**
```php
PerformanceDashboardController::getPrograms($company_uid, $coach_uid)
```

## 🎯 User Flow

```
Step 1: Visit Dashboard
├── URL: /reports/performance-dashboard
├── See Company dropdown (populated)
└── Coach dropdown (disabled, empty)

Step 2: Select Company
├── Page reloads with company parameter
├── Coach dropdown populates (enabled)
└── Shows coaches from that company

Step 3: Select Coach  
├── Click "View Dashboard" button
├── Page reloads with both parameters
└── Programs accordion appears

Step 4: View Programs
├── Each program shown as accordion
├── Expandable to show full dashboard
├── First program auto-expanded
└── Click to expand/collapse others
```

## 📊 Data Flow Diagram

```
Company Selection
    ↓
Gets from: Users with 'company' role
    ↓
User selects → Page reloads
    ↓
Coach Selection Enabled
    ↓
Gets from: coach profiles WHERE field_company = selected_company
    ↓
User selects → Clicks "View Dashboard"
    ↓
Get Programs
    ↓
Gets from: Company's field_assigned_questionnaires
    ↓
Filter by: Sessions with selected coach
    ↓
Load Program Nodes
    ↓
For Each Program:
    ├── Query sessions (company + coach + program)
    ├── Calculate metrics
    ├── Generate chart data
    ├── Get action report
    ├── Get users report
    └── Build dashboard
    ↓
Render as Accordions
```

## 🔧 Technical Details

### Database Tables Used

**For Companies:**
```sql
SELECT * FROM users_field_data 
WHERE status = 1 
AND roles LIKE '%company%';
```

**For Coaches:**
```sql
SELECT uid FROM profile 
WHERE type = 'coach' 
AND status = 1 
AND field_company = :company_uid;
```

**For Programs:**
```sql
-- From company's assigned questionnaires
SELECT field_assigned_questionnaires_target_id 
FROM user__field_assigned_questionnaires 
WHERE entity_id = :company_uid;

-- Filter by coach sessions
SELECT DISTINCT program_nid 
FROM coach_reporting_session 
WHERE company_uid = :company_uid 
AND coach_uid = :coach_uid 
AND submitted IS NOT NULL;
```

### Field Dependencies

The dashboard requires these fields to exist:

**On User (Company):**
- `field_full_name` (optional, for display)
- `field_assigned_questionnaires` (required, entity reference to questionnaire nodes)

**On Profile (Coach):**
- `field_company` (required, entity reference to company user)

**On User (Coach):**
- `field_full_name` (optional, for display)

## 🎨 UI Components

### Filter Section
```html
┌─────────────────────────────────────────┐
│ Select Filters                          │
├─────────────────────────────────────────┤
│ [Company Dropdown ▼]                    │
│ [Coach Dropdown ▼]                      │
│ [View Dashboard Button]                 │
└─────────────────────────────────────────┘
```

### Empty State
```html
┌─────────────────────────────────────────┐
│         📊 (Icon)                       │
│                                         │
│   Select Company and Coach              │
│                                         │
│   Please select both dropdowns above    │
└─────────────────────────────────────────┘
```

### Program Accordions
```html
┌─────────────────────────────────────────┐
│ ▼ Program 1                             │ ← Expanded
├─────────────────────────────────────────┤
│   [Full Dashboard Content]              │
│   - 5 Metric Cards                      │
│   - 4 Google Charts                     │
│   - Action Report Table                 │
│   - Users Report Table                  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ ▶ Program 2                             │ ← Collapsed
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ ▶ Program 3                             │ ← Collapsed
└─────────────────────────────────────────┘
```

## 🔄 Comparison with Reports Page

| Feature | Reports Page (/reports) | Performance Dashboard |
|---------|------------------------|----------------------|
| **Company Dropdown** | ✅ Same logic | ✅ Same logic |
| **Coach Dropdown** | ✅ Dynamic by company | ✅ Dynamic by company |
| **Program Selection** | Dropdown (single) | Accordions (multiple) |
| **Data Source** | `field_assigned_questionnaires` | `field_assigned_questionnaires` |
| **Employee Filter** | Yes | No (shows all) |
| **Report Type** | Tabs | Dashboard cards |
| **Output** | Single report | Multiple dashboards |

## 📍 URLs

### Reports Page
```
https://new.remynd4.com.ddev.site/reports
```
- Form with Company/Coach/Program/Employee dropdowns
- Redirects to report results

### Performance Dashboard
```
https://new.remynd4.com.ddev.site/reports/performance-dashboard
```
- Company and Coach dropdowns only
- Shows all programs as accordions
- Dashboard for each program

### Example Dashboard URL
```
/reports/performance-dashboard?company=2338&coach=456
```

## 🚀 Quick Start

### 1. Clear Cache
```bash
drush cr
```

### 2. Visit Dashboard
```
/reports/performance-dashboard
```

### 3. Test Flow
1. Select any company from dropdown
2. Page reloads → coaches appear
3. Select a coach
4. Click "View Dashboard"
5. See program accordions
6. Click to expand/collapse

## 🔍 Testing

### Test Scenario 1: Company with Multiple Programs
```
Company: ABC Corp (ID: 2338)
Coach: John Doe (ID: 456)
Expected: Multiple program accordions
Each accordion: Full dashboard
```

### Test Scenario 2: Coach with No Sessions
```
Company: XYZ Ltd (ID: 2339)
Coach: Jane Smith (ID: 457)
Expected: "No programs found" message
```

### Test Scenario 3: Company with No Assigned Programs
```
Company: New Company (ID: 2340)
Coach: Any coach
Expected: Empty accordion list
```

## 🐛 Troubleshooting

### Coach dropdown is empty
**Cause:** No coaches linked to company in profiles

**Solution:**
```sql
-- Check coach profiles
SELECT * FROM profile 
WHERE type = 'coach' 
AND field_company = 2338;
```

**Fix:** Assign coaches to company in coach profiles

### No programs showing
**Cause:** Company has no assigned questionnaires

**Solution:**
```sql
-- Check company's assigned questionnaires
SELECT * FROM user__field_assigned_questionnaires 
WHERE entity_id = 2338;
```

**Fix:** Assign programs to company user

### Programs show but no dashboard data
**Cause:** No sessions exist for company + coach + program

**Solution:**
```sql
-- Check sessions
SELECT * FROM coach_reporting_session 
WHERE company_uid = 2338 
AND coach_uid = 456 
AND program_nid = 338
AND submitted IS NOT NULL;
```

**Fix:** Create coaching sessions or check filters

## ✨ Key Benefits

1. **Consistent Data** - Same source as Reports page
2. **Familiar UX** - Users already know these dropdowns
3. **Role-Based** - Same permissions and filtering
4. **Multi-Program View** - See all programs at once
5. **Reusable Logic** - Maintains DRY principles

## 📝 Summary

The Performance Dashboard is now **fully integrated** with the existing Reports system:

✅ Uses same Company dropdown logic  
✅ Uses same Coach dropdown logic  
✅ Uses same Program data source  
✅ Filters by assigned questionnaires  
✅ Respects coach-company relationships  
✅ Maintains data consistency  
✅ Provides accordion interface for multiple programs  

**Access it at:** `/reports/performance-dashboard`

**Clear cache first:** `drush cr`

Enjoy the integrated dashboard! 🎉


