# Charts Showing Static Data - Debugging Guide

**Status:** 🔍 Troubleshooting Mode  
**Issue:** Charts displaying sample data instead of real database data

---

## 🎯 Problem Identification

If you see charts with these values, you're seeing SAMPLE data (not real data):

### ❌ Sample Data Indicators:

**Stars, Core and Laggards Chart:**
- Engineering: 75
- Sales: 30
- Marketing: 40
- HR: 65
- Finance: 25

**Coaching Sessions Chart:**
- 20-30: 80
- 31-40: 60
- 41-50: 50
- 51-60: 55
- 61+: 45

**Overview Chart:**
- Jan: 70, Feb: 75, Mar: 78, Apr: 80, May: 82, Jun: 85

---

## 🔍 Debugging Steps

### Step 1: Open Browser Console

1. Press **F12** (or Cmd+Option+I on Mac)
2. Go to **Console** tab
3. Look for these messages:

#### ✅ Good Messages (Data is loading):
```
✅ Chart data loaded for program 338: {...}
  - Overview data points: 7
  - Competency data points: 7
  - Department data points: 4
  - Sessions data points: 7

🎨 Initializing charts...
  - Found chart containers: 4
  - drupalSettings available: true

📊 Processing chart: overviewChart_338 (type: overview)
  ✅ Using REAL database data for program 338
  🎨 Drawing overview chart with 6 data points
```

#### ❌ Bad Messages (Data not loading):
```
⚠️ WARNING: Using SAMPLE data (no database data found)
  - This means data is not being passed from PHP to JavaScript

❌ No performance dashboard data found in drupalSettings or settings!
```

---

### Step 2: Check drupalSettings in Console

In browser console, type:

```javascript
// Check if drupalSettings exists
drupalSettings

// Check performance dashboard data
drupalSettings.performanceDashboard

// Check if program data exists
drupalSettings.performanceDashboard.programs

// Check specific program (replace 338 with your program ID)
drupalSettings.performanceDashboard.programs['338']

// See the actual chart data
console.log(JSON.stringify(drupalSettings.performanceDashboard.programs['338'], null, 2));
```

---

### Step 3: Verify Database Has Data

Run these SQL queries to check if data exists:

```sql
-- Check if coaching sessions exist
SELECT COUNT(*) as session_count 
FROM coach_reporting_session 
WHERE submitted IS NOT NULL
  AND company_uid = YOUR_COMPANY_ID
  AND program_nid = YOUR_PROGRAM_ID;

-- Check if answers exist
SELECT COUNT(*) as answer_count
FROM coach_reporting_session_answer;

-- Check if on-the-job data exists
SELECT COUNT(*) as onjob_count
FROM qs_emp_lagard_starts
WHERE company_uid = YOUR_COMPANY_ID
  AND questionnaire_id = YOUR_PROGRAM_ID;

-- Check date range of data
SELECT 
  FROM_UNIXTIME(MIN(submitted)) as earliest_session,
  FROM_UNIXTIME(MAX(submitted)) as latest_session,
  COUNT(*) as total_sessions
FROM coach_reporting_session
WHERE submitted IS NOT NULL
  AND company_uid = YOUR_COMPANY_ID
  AND program_nid = YOUR_PROGRAM_ID;
```

---

### Step 4: Check if Controller Method is Being Called

Add temporary debug to controller (after line 324):

```php
// In PerformanceDashboardController.php, method getChartData()
protected function getChartData($company_uid, $program_nid, $employee_uid, $report_type, $from_date, $to_date) {
  // ADD THIS DEBUG LINE:
  \Drupal::logger('coach_reporting_system')->notice('Getting chart data for program @program', [
    '@program' => $program_nid,
  ]);
  
  $chart_data = [
    'overview' => $this->getOverviewChartData(...),
    // ... rest of code
  ];
  
  // ADD THIS DEBUG LINE:
  \Drupal::logger('coach_reporting_system')->notice('Chart data generated: @data', [
    '@data' => print_r($chart_data, TRUE),
  ]);
  
  return $chart_data;
}
```

Then check logs:
```bash
drush watchdog:show --type=coach_reporting_system
```

---

### Step 5: Verify Template is Rendering

View page source (Ctrl+U or Cmd+U) and search for:

```html
<script>
  drupalSettings.performanceDashboard.programs
```

You should see something like:

```javascript
drupalSettings.performanceDashboard.programs['338'] = {
  "overview": [
    ["Month", "Average Score"],
    ["Jun 2025", 75.5],
    ["Jul 2025", 78.2],
    // ... more data
  ],
  "competency": [...],
  "department": [
    ["Category", "Score"],
    ["Stars", 105.2],
    ["Core", 78.5],
    ["Laggards", 52.3]
  ],
  "sessions": [...]
};
```

If you DON'T see this, the template isn't rendering the data.

---

## 🔧 Common Issues & Fixes

### Issue 1: No Data in Database

**Symptom:** SQL queries return 0 rows

**Fix:**
1. Import coaching session data
2. Ensure sessions have `submitted` timestamp
3. Check company_uid and program_nid values match

---

### Issue 2: Wrong Date Range

**Symptom:** Database has data, but all months show 0

**Fix:**
```php
// Check if submitted timestamps are in correct format
SELECT 
  FROM_UNIXTIME(submitted) as submission_date,
  submitted as timestamp
FROM coach_reporting_session
LIMIT 10;
```

If dates are wrong, they need to be Unix timestamps.

---

### Issue 3: drupalSettings Not Available

**Symptom:** Console shows "drupalSettings is undefined"

**Fix:**
1. Check if Drupal core library is loaded
2. Ensure `core/drupalSettings` is a dependency in `.libraries.yml`
3. Clear cache: `drush cr`

Check library file:

```yaml
# coach_reporting_system.libraries.yml
performance_dashboard:
  version: 3.3
  css:
    theme:
      css/performance-dashboard.css: { preprocess: false }
  js:
    js/performance-dashboard.js: { preprocess: false }
  dependencies:
    - core/drupal
    - core/drupalSettings  # ← Make sure this is here
    - core/once
```

---

### Issue 4: Chart Data Not Passed to Template

**Symptom:** View source shows empty or missing chart_data

**Check controller:**
```php
// In getDashboardDataForProgram()
return [
  'metrics' => $this->getMetrics(...),
  'chart_data' => $this->getChartData(...),  // ← Make sure this is here
  'action_report' => $this->getActionReport(...),
  'users_report' => $this->getUsersReport(...),
];
```

**Check template rendering:**
```twig
{{ include('performance-dashboard.html.twig', {
  'program_id': item.program_id,
  'metrics': item.dashboard_data.metrics,
  'chart_data': item.dashboard_data.chart_data,  {# ← Make sure this is passed #}
  'action_report': item.dashboard_data.action_report,
  'users_report': item.dashboard_data.users_report
}) }}
```

---

## 🎨 Expected Chart Data Structure

The controller should return data in this format:

```php
[
  'overview' => [
    ['Month', 'Average Score'],
    ['Jun 2025', 75.5],
    ['Jul 2025', 78.2],
    ['Aug 2025', 80.1],
    ['Sep 2025', 82.3],
    ['Oct 2025', 84.5],
    ['Nov 2025', 86.7]
  ],
  'competency' => [
    ['Month', 'Score'],
    ['Jun 2025', 72.3],
    // ... 6 months
  ],
  'department' => [
    ['Category', 'Score'],
    ['Stars', 105.2],      // NOT "Engineering"!
    ['Core', 78.5],        // NOT "Sales"!
    ['Laggards', 52.3]     // NOT "Marketing"!
  ],
  'sessions' => [
    ['Period', 'Sessions'],
    ['Jun 2025', 15],      // NOT "20-30"!
    ['Jul 2025', 22],      // NOT "31-40"!
    // ... 6 months
  ]
]
```

---

## 🚀 Quick Fixes

### Fix 1: Force Regenerate Everything

```bash
# Clear ALL caches
drush cr
drush cc css-js
drush cc render
drush cr

# Hard refresh browser
# Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)
```

### Fix 2: Disable CSS/JS Aggregation

```bash
# Via Drush
drush config-set system.performance css.preprocess 0 -y
drush config-set system.performance js.preprocess 0 -y
drush cr
```

Or via UI:
1. Go to: `/admin/config/development/performance`
2. Uncheck "Aggregate CSS files"
3. Uncheck "Aggregate JavaScript files"
4. Click "Save configuration"
5. Clear cache

### Fix 3: Check Twig Debug

Enable Twig debug to see what variables are available:

```yaml
# sites/default/services.yml
parameters:
  twig.config:
    debug: true
```

Then view page source to see Twig comments showing available variables.

---

## 🧪 Test Query

Run this in MySQL to verify data exists:

```sql
-- Replace with your actual company_uid and program_nid
SET @company = 123;  -- Your company UID
SET @program = 338;  -- Your program NID

-- Check coaching sessions in last 6 months
SELECT 
  DATE_FORMAT(FROM_UNIXTIME(submitted), '%b %Y') as month,
  COUNT(*) as session_count,
  AVG(
    (SELECT AVG(100 - (value * 25))
     FROM coach_reporting_session_answer 
     WHERE sid = s.sid)
  ) as avg_score
FROM coach_reporting_session s
WHERE company_uid = @company
  AND program_nid = @program
  AND submitted IS NOT NULL
  AND submitted >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 6 MONTH))
GROUP BY DATE_FORMAT(FROM_UNIXTIME(submitted), '%Y-%m')
ORDER BY DATE_FORMAT(FROM_UNIXTIME(submitted), '%Y-%m');

-- Check Stars/Core/Laggards data
SELECT 
  CASE 
    WHEN (target_achieved / NULLIF(target_forecasted, 0) * 100) >= 100 THEN 'Stars'
    WHEN (target_achieved / NULLIF(target_forecasted, 0) * 100) >= 60 THEN 'Core'
    ELSE 'Laggards'
  END as category,
  COUNT(DISTINCT employee_uid) as count,
  AVG(target_achieved / NULLIF(target_forecasted, 0) * 100) as avg_performance
FROM qs_emp_lagard_starts
WHERE company_uid = @company
  AND questionnaire_id = @program
  AND month >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH), '%Y-%m')
GROUP BY category;
```

---

## 📝 Debugging Checklist

Run through this checklist:

- [ ] Open browser console (F12)
- [ ] Check for "✅ Chart data loaded" messages
- [ ] Check drupalSettings object has data
- [ ] Verify database has coaching sessions
- [ ] Verify submitted timestamps are valid
- [ ] Check company_uid and program_nid are correct
- [ ] View page source for chart_data script
- [ ] Check Drupal logs for errors
- [ ] Ensure libraries are loaded
- [ ] Clear all caches
- [ ] Hard refresh browser

---

## 🎯 Expected vs Actual

### If Data is Loading Correctly:

**Console Output:**
```
✅ Chart data loaded for program 338: {overview: Array(7), competency: Array(7), ...}
  - Overview data points: 7
  - Competency data points: 7
  - Department data points: 4
  - Sessions data points: 7

🎨 Initializing charts...
📊 Processing chart: overviewChart_338 (type: overview)
  ✅ Using REAL database data for program 338
  🎨 Drawing overview chart with 6 data points
  - Data preview: [["Month", "Average Score"], ["Jun 2025", 75.5], ...]
```

**Charts Display:**
- Real month names from database
- Real performance scores
- Stars/Core/Laggards (not departments!)
- Session counts by month (not age ranges!)

### If Data is NOT Loading:

**Console Output:**
```
❌ Drupal or drupalSettings not available!
⚠️ WARNING: Using SAMPLE data (no database data found)
```

**Charts Display:**
- Static sample data
- Departments (Engineering, Sales, etc.)
- Age ranges (20-30, 31-40, etc.)
- Fixed sample scores

---

## 🔧 Immediate Actions

### 1. Clear Cache RIGHT NOW

```bash
drush cr
```

### 2. Open Console and Check

```javascript
// In browser console
drupalSettings.performanceDashboard
```

### 3. If Still Sample Data...

Check this file exists and has data in view source:

```html
<!-- Search for this in page source (Ctrl+U) -->
<script>
drupalSettings.performanceDashboard.programs['XXX'] = {
  "overview": [...],
  "competency": [...],
  "department": [...],
  "sessions": [...]
};
</script>
```

If you DON'T see this script tag, the problem is in the controller not generating data or template not rendering it.

---

## 💡 Quick Diagnostic

Run this JavaScript in browser console:

```javascript
// Diagnostic Script
console.log('=== CHART DATA DIAGNOSTIC ===');
console.log('1. drupalSettings exists?', typeof drupalSettings !== 'undefined');
console.log('2. performanceDashboard exists?', drupalSettings?.performanceDashboard ? 'YES' : 'NO');
console.log('3. programs exists?', drupalSettings?.performanceDashboard?.programs ? 'YES' : 'NO');
console.log('4. Program IDs available:', drupalSettings?.performanceDashboard?.programs ? Object.keys(drupalSettings.performanceDashboard.programs) : 'NONE');

// Check chart containers
const containers = document.querySelectorAll('.chart-container[data-chart-type]');
console.log('5. Chart containers found:', containers.length);

containers.forEach(c => {
  const id = c.id;
  const type = c.getAttribute('data-chart-type');
  const programId = id.match(/_(\d+)$/)?.[1];
  console.log(`   - ${id}: type=${type}, programId=${programId}`);
});

console.log('=== END DIAGNOSTIC ===');
```

---

## 🎯 Root Cause Analysis

### Possible Causes:

1. **No Data in Database**
   - Solution: Import coaching session data
   - Solution: Ensure `submitted` field has timestamps

2. **Wrong Company/Program Selected**
   - Solution: Verify correct company and coach selected
   - Solution: Check program belongs to that company

3. **Date Range Too Narrow**
   - Solution: Check data exists in last 6 months
   - Solution: Try longer date range

4. **Cache Not Cleared**
   - Solution: `drush cr` and hard refresh

5. **drupalSettings Not Available**
   - Solution: Check library dependencies
   - Solution: Ensure `core/drupalSettings` is loaded

6. **Template Not Rendering**
   - Solution: Check Twig syntax
   - Solution: Verify chart_data variable is passed

---

## 🚀 Force Data to Load

### Method 1: Manual drupalSettings Injection

Add this temporarily to test (in browser console):

```javascript
// Force inject test data
if (typeof drupalSettings === 'undefined') {
  window.drupalSettings = {};
}

drupalSettings.performanceDashboard = {
  programs: {
    '338': {  // Replace with your program ID
      overview: [
        ['Month', 'Average Score'],
        ['Jun', 75],
        ['Jul', 80],
        ['Aug', 82],
        ['Sep', 85],
        ['Oct', 87],
        ['Nov', 90]
      ],
      competency: [
        ['Month', 'Score'],
        ['Jun', 70],
        ['Jul', 72],
        ['Aug', 75],
        ['Sep', 78],
        ['Oct', 80],
        ['Nov', 82]
      ],
      department: [
        ['Category', 'Score'],
        ['Stars', 105],
        ['Core', 78],
        ['Laggards', 52]
      ],
      sessions: [
        ['Period', 'Sessions'],
        ['Jun', 15],
        ['Jul', 22],
        ['Aug', 18],
        ['Sep', 25],
        ['Oct', 20],
        ['Nov', 12]
      ]
    }
  }
};

// Then manually trigger chart initialization
if (typeof google !== 'undefined') {
  google.charts.setOnLoadCallback(function() {
    // Find the init function and call it
    Drupal.behaviors.performanceDashboard.attach(document, drupalSettings);
  });
}
```

If charts now show this test data, it confirms JavaScript works and the issue is data not being passed from PHP.

---

## 📞 Next Steps

### If Console Shows "✅ Using REAL database data":

**Problem:** Database is empty or has no data for selected filters

**Solution:**
1. Check database has coaching sessions
2. Verify correct company/program selected
3. Widen date range
4. Import sample data if needed

### If Console Shows "⚠️ Using SAMPLE data":

**Problem:** Data not being passed from PHP to JavaScript

**Solutions:**
1. Check controller is generating chart_data
2. Verify template is rendering script tag
3. Check Twig syntax is correct
4. Clear cache and test again

### If Console Shows Nothing:

**Problem:** JavaScript not loading or error preventing execution

**Solutions:**
1. Check for JavaScript errors (red in console)
2. Verify `performance-dashboard.js` is loading
3. Check library is attached to render array
4. Test in different browser

---

## 🎓 Understanding Data Flow

### Correct Flow:

```
1. User selects company & coach
2. Controller queries database
   → getOverviewChartData() returns [[Month, Score], ...]
   → getCompetencyChartData() returns [[Month, Score], ...]
   → getDepartmentChartData() returns [[Category, Score], ...]
   → getSessionsChartData() returns [[Period, Sessions], ...]
   
3. Controller packages chart data
   → $chart_data = [ 'overview' => [...], 'competency' => [...], ...]
   
4. Controller passes to template
   → '#chart_data' => $chart_data
   
5. Template renders JavaScript
   → drupalSettings.performanceDashboard.programs['338'] = {...}
   
6. JavaScript reads drupalSettings
   → chartData = drupalSettings.performanceDashboard.programs['338']
   
7. JavaScript draws charts
   → drawLineChart(id, chartData.overview, ...)
   
8. ✅ Real data displayed!
```

### Where It Can Break:

❌ **Step 2:** Database empty → No data to return  
❌ **Step 3:** Controller error → Chart data not generated  
❌ **Step 4:** Variable not passed → Template doesn't get data  
❌ **Step 5:** Twig error → Script tag not rendered  
❌ **Step 6:** drupalSettings undefined → Can't read data  
❌ **Step 7:** Google Charts not loaded → Can't draw  

---

## 📊 Sample vs Real Data Comparison

### Sample Data (What you're seeing now):

```javascript
department: [
  ['Department', 'Score'],
  ['Engineering', 75],    // ← Wrong!
  ['Sales', 30],
  ['Marketing', 40]
]

sessions: [
  ['Age Group', 'Sessions'],
  ['20-30', 80],          // ← Wrong!
  ['31-40', 60]
]
```

### Real Data (What you should see):

```javascript
department: [
  ['Category', 'Score'],
  ['Stars', 105.2],       // ← Correct!
  ['Core', 78.5],
  ['Laggards', 52.3]
]

sessions: [
  ['Period', 'Sessions'],
  ['Jun 2025', 15],       // ← Correct!
  ['Jul 2025', 22]
]
```

---

## ✅ Verification Steps

After fixing:

1. **Clear cache:** `drush cr`
2. **Hard refresh:** Cmd+Shift+R
3. **Open console:** F12
4. **Look for:** "✅ Using REAL database data"
5. **Check charts:** Should show real month names and categories
6. **Test date range:** Select dates, verify charts update

---

## 🆘 Emergency Contact Points

If still not working after all debugging:

1. **Check Drupal logs:**
   ```bash
   drush watchdog:show
   ```

2. **Check PHP errors:**
   ```bash
   tail -f /var/log/apache2/error.log
   ```

3. **Test controller directly:**
   ```
   Visit: /reports/performance-dashboard/ajax/program-data?program_nid=338&company_uid=123&coach_uid=456
   ```

4. **Verify JSON output:**
   Should return: `{"success":true,"data":{"metrics":{...},"chart_data":{...}}}`

---

## 🎯 Summary

**The JavaScript is ready and working.**  
**The Controller is generating data.**  
**The issue is likely:**
1. No data in database, OR
2. Cache not cleared, OR
3. Wrong filters selected

**Run the diagnostic steps above to identify the exact issue!**

---

**Clear cache now and check browser console for clues!** 🔍











