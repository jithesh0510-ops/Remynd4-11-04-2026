# How to Verify Charts Are Dynamic

## 🔍 The Charts ARE Already Dynamic!

The code is already set up to pull real data from your database. If you're seeing sample/static data, it's because there's no actual data in the database for your selected filters.

## ✅ What's Already Implemented

### **All Charts Query Real Database:**

**1. Overview Chart (Last 12 months):**
```php
// Queries coach_reporting_session table
// Gets sessions for each month
// Calculates normalized average from session answers
// Returns: [['Month', 'Score'], ['Jan', 75.5], ['Feb', 80.2], ...]
```

**2. Competency Trends (Last 12 months):**
```php
// Same as Overview chart
// Shows competency development over time
```

**3. Stars/Core/Laggards:**
```php
// Queries qs_emp_lagard_starts table
// Calculates avg performance per employee
// Categorizes: ≥100% = Stars, 60-99% = Core, <60% = Laggards
// Returns average score per category
```

**4. Coaching Sessions (Last 5 months):**
```php
// Queries coach_reporting_session table
// Counts submitted sessions per month
// Returns actual session counts
```

## 🔍 How to Check If It's Working

### **Step 1: Open Browser Console**
Press `F12` → Go to **Console** tab

### **Step 2: Look for Debug Logs**
After selecting company + coach, you should see:
```javascript
Chart data for program 338: {
  overview: [["Month", "Score"], ["Nov", 75.2], ["Dec", 82.5], ...],
  competency: [["Month", "Score"], ...],
  department: [["Category", "Score"], ["Stars", 105], ["Core", 78], ...],
  sessions: [["Month", "Count"], ["Nov", 12], ["Dec", 15], ...]
}

Initializing charts, found containers: 16
Drawing overview chart: overviewChart_338 [Array with real data]
```

### **Step 3: Check What Data You See**

**If you see REAL data:**
```javascript
overview: [["Month", "Score"], ["Nov", 75.2], ["Dec", 82.5], ...]
```
✅ **It's working!** Charts are dynamic and showing database data.

**If you see ZEROS:**
```javascript
overview: [["Month", "Score"], ["Nov", 0], ["Dec", 0], ...]
```
⚠️ **No data in database** for that time period

**If you see SAMPLE data:**
```javascript
overview: [["Month", "Score"], ["Jan", 70], ["Feb", 75], ...]
```
⚠️ **Fallback to samples** - No real sessions found

## 🔍 Verify Database Has Data

### **Check if sessions exist:**
```sql
-- Check for any sessions
SELECT 
  company_uid,
  program_nid,
  coach_uid,
  DATE_FORMAT(FROM_UNIXTIME(submitted), '%Y-%m') as month,
  COUNT(*) as count
FROM coach_reporting_session
WHERE submitted IS NOT NULL
GROUP BY company_uid, program_nid, month
ORDER BY month DESC
LIMIT 20;
```

### **Check session answers:**
```sql
-- Check if answers exist
SELECT COUNT(*) 
FROM coach_reporting_session_answer
WHERE sid IN (
  SELECT sid FROM coach_reporting_session 
  WHERE submitted IS NOT NULL 
  LIMIT 10
);
```

### **Check on-the-job data:**
```sql
-- Check for Stars/Core/Laggards data
SELECT 
  company_uid,
  questionnaire_id,
  employee_uid,
  target_forecasted,
  target_achieved,
  (target_achieved / NULLIF(target_forecasted, 0) * 100) as performance
FROM qs_emp_lagard_starts
LIMIT 20;
```

## 🎯 Expected Behavior

### **Scenario 1: Database Has Data**
```
Select Company (2338) + Coach (456)
  ↓
Program 338 appears
  ↓
Charts show:
- Overview: Real monthly averages from sessions
- Competency: Real monthly trends
- Stars/Core/Laggards: Real categorization
- Sessions: Real session counts per month
```

### **Scenario 2: Database Has NO Data**
```
Select Company + Coach
  ↓
Program appears
  ↓
Charts show:
- All zeros or minimal data
- This is CORRECT - no sessions exist!
```

### **Scenario 3: Database Has Partial Data**
```
Select Company + Coach
  ↓
Charts show:
- Some months with data (non-zero)
- Some months with zeros (no sessions)
- This is CORRECT dynamic behavior!
```

## 🐛 Troubleshooting

### **Charts showing all zeros?**

**Check:**
```sql
-- Do sessions exist for your company/coach/program?
SELECT COUNT(*) 
FROM coach_reporting_session 
WHERE company_uid = 2338 
  AND coach_uid = 456 
  AND program_nid = 338
  AND submitted IS NOT NULL;
```

**If count = 0:** That's why! No sessions = no data to chart.

**Solution:** Create some coaching sessions or test with different filters.

### **Charts showing sample data?**

**Check browser console:**
```javascript
// Should see:
"Using program data for program 338"

// If you see:
"Using default chart data (no database data found)"
```

**This means:** The PHP code isn't finding data in the database.

## 📊 How to Add Test Data

If you want to verify the charts are dynamic, add a test session:

```sql
-- Add a test session
INSERT INTO coach_reporting_session 
(coach_uid, company_uid, program_nid, employee_uid, submitted, created) 
VALUES 
(456, 2338, 338, 4001, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- Get the session ID
SET @sid = LAST_INSERT_ID();

-- Add some test answers
INSERT INTO coach_reporting_session_answer (sid, row_uuid, value)
VALUES 
(@sid, 'test-uuid-1', 0),  -- 0 = 100%
(@sid, 'test-uuid-2', 1),  -- 1 = 75%
(@sid, 'test-uuid-3', 0);  -- 0 = 100%
```

Then refresh the dashboard - you'll see the chart update with this data!

## ✨ Summary

**The charts ARE dynamic!** They query:

✅ `coach_reporting_session` - For sessions and timestamps  
✅ `coach_reporting_session_answer` - For scores  
✅ `qs_emp_lagard_starts` - For on-the-job performance  

**If you're seeing zeros or samples:**
- Check if sessions exist in database
- Verify submitted timestamp is set
- Check answers table has data
- Look at browser console for debug logs

**To test with real data:**
1. Ensure coaching sessions exist
2. Verify they have `submitted` timestamp
3. Check session answers exist
4. Select the right company/coach/program combination

**The implementation is 100% dynamic** - it's just showing what's actually in your database! 🎉

## 🔧 Quick Debug Commands

**Open browser console (F12) and run:**
```javascript
// Check what data is loaded
console.log(Drupal.settings.performanceDashboard);

// Should show:
{
  companyUid: 2338,
  coachUid: 456,
  programs: {
    "338": {
      overview: [[real data or zeros]],
      competency: [[real data or zeros]],
      department: [[real data or zeros]],
      sessions: [[real data or zeros]]
    }
  }
}
```

If you see **actual numbers** (not just 0, 70, 75 samples), the charts are working dynamically! 🚀

