# Dynamic Performance Dashboard with Program Accordions

## 🎯 Overview

A fully dynamic Performance Dashboard that displays coaching metrics organized by programs in an accordion layout. Features include:

- **Company & Coach Filters** - Select dropdowns at the top
- **Empty State** - No results shown until both filters are selected
- **Program Accordions** - Each program gets its own expandable section
- **Real Dashboard Data** - Pulls from actual database tables
- **Google Charts** - Interactive visualizations
- **Responsive Design** - Works on all devices

## 📁 Files Created

### 1. Controller (NEW)
```
src/Controller/PerformanceDashboardController.php
```
- Handles company and coach dropdowns
- Fetches programs based on selected filters
- Generates dashboard data for each program
- Integrates with existing database tables

### 2. Template (NEW)
```
templates/performance-dashboard-accordion.html.twig
```
- Filter dropdowns (Company & Coach)
- Empty state when no filters selected
- Program accordions with dashboard inside each
- Responsive layout

### 3. Updated Files
- `css/performance-dashboard.css` - Added filter and accordion styles
- `js/performance-dashboard.js` - Added filter handling
- `coach_reporting_system.module` - Added theme hook
- `coach_reporting_system.routing.yml` - Added route

## 🚀 Quick Start

### Step 1: Clear Cache
```bash
drush cr
```

### Step 2: Access the Dashboard
Navigate to: **`/reports/performance-dashboard`**

Or visit: `https://yourdomain.com/reports/performance-dashboard`

### Step 3: Use the Dashboard

1. **Select Company** from the first dropdown
2. Page will reload and populate coaches for that company
3. **Select Coach** from the second dropdown  
4. Click **"View Dashboard"** button
5. Program accordions will appear with full dashboards inside

## 🎨 Features

### Filter Section
- **Company Dropdown** - Shows all companies with 'company' role
- **Coach Dropdown** - Dynamically populates based on selected company
- **Submit Button** - Applies filters and loads programs
- **Auto-disable** - Coach dropdown disabled until company selected

### Empty State
When no filters are selected, displays:
- Icon with helpful message
- Instructions to select filters
- Clean, professional design

### Program Accordions
Each accordion contains:
- **Program Name** with icon
- **Expandable/Collapsible** sections
- **Complete Dashboard** inside each:
  - 5 Metric Cards
  - 4 Google Charts
  - Action Report Table
  - Users Report Table

### Dynamic Data
The dashboard pulls real data from:
- `coach_reporting_session` table
- `coach_reporting_session_answer` table
- `qs_emp_lagard_starts` table
- User and Profile entities

## 📊 Data Sources

### Metrics Calculations

#### 1. Number of Users Coached
```sql
SELECT COUNT(DISTINCT employee_uid) 
FROM coach_reporting_session 
WHERE company_uid = ? AND program_nid = ? AND submitted IS NOT NULL
```

#### 2. Coaching Sessions
```sql
SELECT COUNT(*) 
FROM coach_reporting_session 
WHERE company_uid = ? AND program_nid = ? AND submitted IS NOT NULL
```

#### 3. Behavioral Progress
Calculated from session answers with normalized scoring (0-100 scale)

#### 4. On-The-Job Progress
```sql
SELECT AVG(target_achieved / target_forecasted * 100) 
FROM qs_emp_lagard_starts 
WHERE company_uid = ? AND questionnaire_id = ?
```

#### 5. ROI
Calculated from performance improvement over time

### Chart Data

#### Overview Chart (Line)
- Last 6 months of average scores
- Grouped by month
- Shows coaching progress trend

#### Competency Chart (Line)
- Last 12 months of data
- Monthly competency scores
- Long-term trend analysis

#### Department Chart (Bar)
- Stars, Core, Laggards categories
- Based on on-the-job performance
- Performance thresholds:
  - Stars: ≥90% achievement
  - Core: 70-89% achievement
  - Laggards: <70% achievement

#### Sessions Chart (Bar)
- Last 5 months of session counts
- Grouped by month
- Shows coaching activity

## 🔧 How It Works

### Flow Diagram
```
1. User visits /reports/performance-dashboard
   ↓
2. Page loads with Company dropdown populated
   ↓
3. User selects Company
   ↓
4. Page reloads with Coach dropdown populated for that company
   ↓
5. User selects Coach
   ↓
6. User clicks "View Dashboard"
   ↓
7. Controller queries for programs (company + coach)
   ↓
8. For each program:
   - Generate metrics
   - Generate chart data
   - Generate action report
   - Generate users report
   ↓
9. Render accordions with program dashboards
```

### Controller Logic

```php
// Get all companies
protected function getCompanies() {
  // Query users with 'company' role
  // Return as dropdown options
}

// Get coaches for company
protected function getCoaches($company_uid) {
  // Check coach_profiles linked to company
  // Or query coaches with sessions for this company
  // Return as dropdown options
}

// Get programs
protected function getPrograms($company_uid, $coach_uid) {
  // Query sessions for company + coach
  // Get unique program_nid values
  // Load program nodes
  // Return program data
}

// Get dashboard data per program
protected function getDashboardDataForProgram() {
  // Calculate metrics
  // Generate chart data
  // Get action report
  // Get users report
  // Return complete dashboard data
}
```

## 🎭 URL Parameters

The dashboard uses URL parameters for filtering:

- `?company=2338` - Selected company ID
- `&coach=456` - Selected coach ID
- `&employee=4001` - Optional: Filter to specific employee
- `&report_type=latest` - Report type (latest, overtime)
- `&from=2024-01-01` - Optional: Date from
- `&to=2024-12-31` - Optional: Date to

### Example URLs

**Step 1: Initial Load**
```
/reports/performance-dashboard
```

**Step 2: Company Selected**
```
/reports/performance-dashboard?company=2338
```

**Step 3: Coach Selected (Full Dashboard)**
```
/reports/performance-dashboard?company=2338&coach=456
```

**Step 4: With Additional Filters**
```
/reports/performance-dashboard?company=2338&coach=456&employee=4001&report_type=latest
```

## 🎨 Customization

### Modify Metrics
Edit `PerformanceDashboardController.php`:

```php
protected function getMetrics($company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date) {
  // Add your custom metric calculations here
  $custom_metric = $this->database->query("YOUR SQL")->fetchField();
  
  return [
    'custom_metric' => [
      'value' => $custom_metric,
      'change' => '+X%',
    ],
    // ... other metrics
  ];
}
```

### Modify Filter Logic
Edit the `getCoaches()` method to change how coaches are filtered:

```php
protected function getCoaches($company_uid) {
  // Change logic to filter coaches differently
  // Example: Only active coaches
  // Example: Coaches with recent sessions
  // Example: Coaches from specific department
}
```

### Styling
Edit `css/performance-dashboard.css`:

```css
/* Change filter section colors */
.dashboard-filters-section {
  background: YOUR_COLOR;
  border: 3px solid YOUR_COLOR;
}

/* Change accordion colors */
.accordion-item {
  border: 2px solid YOUR_COLOR;
}

/* Change button colors */
.btn-submit {
  background: YOUR_GRADIENT;
}
```

## 🔒 Permissions

The dashboard requires the `access coach reporting` permission.

### Grant Access
1. Go to `/admin/people/permissions`
2. Find "Coach Reporting System" section
3. Check "Access coach reporting" for appropriate roles
4. Save permissions

### Restrict by Role
In routing file (`coach_reporting_system.routing.yml`):

```yaml
coach_reporting_system.performance_dashboard:
  requirements:
    _role: 'coach+company'  # Only coaches and companies
```

## 📱 Responsive Behavior

### Desktop (1400px+)
- Filter row: 3 columns (Company | Coach | Button)
- Metrics: 5 cards per row
- Charts: 2 per row

### Tablet (768px - 1399px)
- Filter row: Single column stack
- Metrics: 2-3 cards per row
- Charts: 1 per row

### Mobile (<768px)
- Everything stacks vertically
- Touch-friendly accordion buttons
- Optimized font sizes
- Full-width elements

## 🐛 Troubleshooting

### Problem: Coach dropdown is empty
**Cause:** No coaches linked to selected company

**Solution:**
1. Check if coaches have `coach_profiles` with `field_company` set
2. Or check if coaches have sessions for this company
3. Verify coach users have 'coach' role

**Code to Debug:**
```php
// In PerformanceDashboardController::getCoaches()
\Drupal::logger('coach_reporting')->notice('Found coaches: @count', ['@count' => count($coaches)]);
```

### Problem: No programs found
**Cause:** No sessions exist for company + coach combination

**Solution:**
1. Verify sessions exist:
```sql
SELECT program_nid, COUNT(*) 
FROM coach_reporting_session 
WHERE company_uid = 2338 AND coach_uid = 456 AND submitted IS NOT NULL 
GROUP BY program_nid;
```
2. Check if programs are published
3. Verify session submissions are complete

### Problem: Charts not loading
**Solution:**
1. Check browser console for JavaScript errors
2. Verify Google Charts API is loading
3. Clear Drupal cache: `drush cr`
4. Check chart data structure in template

### Problem: Empty metrics (showing 0)
**Solution:**
1. Check database tables have data
2. Verify date ranges are correct
3. Check calculation methods in controller
4. Add debug logging:
```php
\Drupal::logger('dashboard')->notice('Metrics: @metrics', ['@metrics' => print_r($metrics, TRUE)]);
```

## 🔄 Integration with Existing Reports

The dashboard integrates with your existing report system:

### Link from Reports Page
Add a link to the dashboard from `/reports`:

```php
// In ReportController or template
$dashboard_link = Link::createFromRoute(
  'Performance Dashboard',
  'coach_reporting_system.performance_dashboard',
  [],
  ['attributes' => ['class' => ['button', 'button--primary']]]
);
```

### Pre-fill from Report Results
Link from report results page with pre-selected filters:

```twig
{# In report-result.html.twig #}
<a href="/reports/performance-dashboard?company={{ company_uid }}&coach={{ coach_uid }}" 
   class="btn-dashboard">
  View Performance Dashboard
</a>
```

### Menu Integration
Add to navigation in `coach_reporting_system.links.menu.yml`:

```yaml
coach_reporting_system.performance_dashboard:
  title: 'Performance Dashboard'
  route_name: coach_reporting_system.performance_dashboard
  parent: 'coach_reporting_system.reports'
  weight: 2
```

## 📈 Data Optimization

### Caching
The dashboard doesn't cache by default to show live data. To enable caching:

```php
// In PerformanceDashboardController::view()
return [
  '#theme' => 'performance_dashboard_accordion',
  // ... variables
  '#cache' => [
    'max-age' => 3600, // Cache for 1 hour
    'tags' => ['coach_reporting_session_list'],
    'contexts' => ['url.query_args'],
  ],
];
```

### Query Optimization
For large datasets, add indexes:

```sql
CREATE INDEX idx_session_company_coach ON coach_reporting_session(company_uid, coach_uid, program_nid, submitted);
CREATE INDEX idx_session_month ON coach_reporting_session(company_uid, program_nid, submitted);
CREATE INDEX idx_ontheJob_month ON qs_emp_lagard_starts(company_uid, questionnaire_id, month);
```

## 🆕 Adding New Features

### Add Date Range Filter
1. Add date inputs to template filter section
2. Update form submission to include dates
3. Use existing `from_date` and `to_date` parameters

### Add Employee Filter
Already supported! Just add:
```twig
<select name="employee">
  {% for uid, name in employees %}
    <option value="{{ uid }}">{{ name }}</option>
  {% endfor %}
</select>
```

### Add Export Functionality
```php
// Add download button to template
// Create new method in controller
public function export(Request $request) {
  // Generate Excel/PDF
  // Use existing data methods
}
```

### Add Email Reports
```php
// Create scheduled task
// Use \Drupal::service('plugin.manager.mail')
// Send dashboard HTML as email
```

## ✨ Summary

**You now have a fully dynamic Performance Dashboard with:**

✅ Company and Coach filter dropdowns  
✅ Empty state until filters are selected  
✅ Program-wise accordion organization  
✅ Complete dashboard metrics per program  
✅ Google Charts visualization  
✅ Real-time database queries  
✅ Responsive mobile-friendly design  
✅ Integrated with existing coaching system  

**Access it at:** `/reports/performance-dashboard`

**Clear cache first:**
```bash
drush cr
```

Enjoy your new dynamic dashboard! 🎉


