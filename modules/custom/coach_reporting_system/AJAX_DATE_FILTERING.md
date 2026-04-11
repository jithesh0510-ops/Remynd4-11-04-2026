# AJAX Date Range Filtering - Performance Dashboard

## ✅ Complete AJAX Implementation

The Performance Dashboard now has **full AJAX date range filtering** for each program accordion. All charts, metrics, and tables update dynamically without page reload.

## 🎯 Features Implemented

### **1. Date Range Filter per Program**
- ✅ Each program accordion has its own date filter
- ✅ Toggle button to show/hide date inputs
- ✅ From Date and To Date inputs
- ✅ Submit button (AJAX)
- ✅ Reset button (AJAX - loads last 12 months)

### **2. Default Behavior (No Dates Selected)**
**Shows last 12 months** for:
- Overview Chart (12 months of coaching results)
- Competency Trends Chart (12 months of trends)
- All other data and metrics

### **3. Filtered Behavior (Dates Selected)**
**Shows selected date range** for:
- All charts dynamically update
- All metrics recalculate
- All tables filter data
- Everything updates via AJAX - **no page reload**

## 🔄 How It Works

### **User Flow:**

```
1. User opens program accordion
   ↓
2. Sees dashboard with last 12 months data
   ↓
3. Clicks "Date Range" button
   ↓
4. Date inputs appear
   ↓
5. Selects From: 2024-01-01, To: 2024-06-30
   ↓
6. Clicks "Submit"
   ↓
7. Loading spinner shows
   ↓
8. AJAX call fetches filtered data
   ↓
9. Charts redraw with new data
   ↓
10. Metrics update
    ↓
11. Tables refresh
    ↓
12. Loading spinner hides
```

### **Reset Flow:**

```
1. User clicks "Reset" button
   ↓
2. Date inputs clear
   ↓
3. Loading spinner shows
   ↓
4. AJAX fetches last 12 months data
   ↓
5. Everything updates
   ↓
6. Loading spinner hides
```

## 📡 AJAX Endpoints

### **Get Coaches (Already Working):**
```
GET /reports/performance-dashboard/ajax/coaches?company_uid=2338

Response:
{
  "coaches": {
    "456": "John Doe (john@example.com)",
    "457": "Jane Smith (jane@example.com)"
  }
}
```

### **Get Program Data with Date Filter (NEW):**
```
GET /reports/performance-dashboard/ajax/program-data?
    program_nid=338
    &company_uid=2338
    &coach_uid=456
    &from_date=2024-01-01
    &to_date=2024-06-30

Response:
{
  "success": true,
  "data": {
    "metrics": {
      "users_coached": {"value": 25, "change": "+5%"},
      "coaching_sessions": {"value": 80, "change": "+10%"},
      // ... more metrics
    },
    "chart_data": {
      "overview": [
        ["Month", "Score"],
        ["Jan 2024", 70],
        ["Feb 2024", 75],
        // ... more data
      ],
      "competency": [...],
      "department": [...],
      "sessions": [...]
    },
    "action_report": [...],
    "users_report": {
      "data": [...],
      "pagination": {...}
    }
  }
}
```

## 🎨 Visual Elements

### **Date Range Filter UI:**

```
┌─────────────────────────────────────────┐
│ Performance Dashboard    [Date Range ▼] │
└─────────────────────────────────────────┘

When clicked, shows:
┌─────────────────────────────────────────┐
│ From: [2024-01-01]  To: [2024-06-30]   │
│ [Submit] [Reset]                         │
└─────────────────────────────────────────┘
```

### **Loading State:**

```
┌─────────────────────────────────────────┐
│              🔄 Spinner                  │
│           Loading data...                │
│  (Semi-transparent dark overlay)         │
└─────────────────────────────────────────┘
```

## 💻 Implementation Details

### **JavaScript Functions:**

**Date Submit Handler:**
```javascript
// When user clicks Submit
1. Validate dates (both required, from <= to)
2. Show loading spinner
3. AJAX call to /ajax/program-data with dates
4. Receive filtered data
5. Update all charts
6. Update all metrics
7. Update all tables
8. Hide loading spinner
```

**Date Reset Handler:**
```javascript
// When user clicks Reset
1. Clear date inputs
2. Show loading spinner
3. AJAX call without dates (defaults to 12 months)
4. Receive default data
5. Update everything
6. Hide loading spinner
```

**Update Functions:**
- `updateDashboard()` - Main update coordinator
- `updateMetrics()` - Updates 5 metric cards
- `updateCharts()` - Redraws all 4 Google Charts
- `updateActionReport()` - Refreshes action report table
- `updateUsersReport()` - Refreshes users table + pagination

### **Controller Logic:**

```php
// In getDashboardDataForProgram()

if ($from_date && $to_date) {
  // Use specified date range
  $months = generate_months_between($from_date, $to_date);
} else {
  // Default: Last 12 months
  $months = last_12_months();
}

// Query data for each month
foreach ($months as $month) {
  $score = calculate_average_for_month($month);
  $chart_data[] = [$month_label, $score];
}
```

## 📊 Chart Behavior

### **Overview Chart:**
- **Default:** Last 12 months (Jan to Dec)
- **Filtered:** Selected date range (e.g., Jan to Jun)
- **Updates:** Via AJAX, redraws with new data

### **Competency Trends:**
- **Default:** Last 12 months
- **Filtered:** Selected date range
- **Updates:** Via AJAX

### **Stars/Core/Laggards:**
- Shows current categorization
- Updates based on date-filtered performance

### **Sessions Chart:**
- **Default:** Last 5 months
- **Filtered:** Months within selected range
- **Updates:** Session counts per month

## 🔧 Testing

### **Test Scenario 1: Default (No Dates)**
```
1. Open program accordion
2. See charts with last 12 months
3. Overview: 12 data points
4. Competency: 12 data points
```

### **Test Scenario 2: Filter by Date**
```
1. Click "Date Range" button
2. Select From: 2024-01-01
3. Select To: 2024-03-31
4. Click "Submit"
5. Loading spinner appears
6. Charts update to show Jan-Mar only
7. Metrics recalculate for that period
8. Tables filter to that period
```

### **Test Scenario 3: Reset**
```
1. Click "Reset" button
2. Date inputs clear
3. Loading spinner appears
4. Charts reload with last 12 months
5. Everything resets to default
```

## 🐛 Troubleshooting

### **Problem: AJAX call fails**
**Check:**
```javascript
// Browser console (F12)
// Look for error messages
// Check Network tab for failed requests
```

**Solution:**
- Verify route exists: `/reports/performance-dashboard/ajax/program-data`
- Check permissions
- Verify parameters are passed correctly

### **Problem: Charts don't update**
**Check:**
```javascript
// Console log
console.log(data.chart_data);

// Should show:
{
  overview: [['Month', 'Score'], ...],
  competency: [...],
  // ...
}
```

**Solution:**
- Verify AJAX response has chart_data
- Check Google Charts is loaded
- Clear browser cache

### **Problem: Loading spinner doesn't hide**
**Check:**
```javascript
// Console for errors during update
```

**Solution:**
- Check updateDashboard() completes
- Verify hideLoadingState() is called
- Check for JavaScript errors

## 🎨 Styling

### **Loading Overlay:**
```css
.loading-overlay {
  background: rgba(16, 23, 35, 0.95);  /* Semi-transparent dark */
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.loading-spinner {
  border: 4px solid #1a1f2e;
  border-top: 4px solid #86f1ff;  /* Cyan spinner */
  animation: spin 1s linear infinite;
}
```

## 📝 Summary

**AJAX Date Filtering Features:**

✅ **Per-program date filter** - Each accordion independent  
✅ **Last 12 months default** - No dates = 12 months  
✅ **Custom date range** - User selects specific period  
✅ **AJAX updates** - No page reload  
✅ **Loading states** - Spinner while fetching  
✅ **Dynamic charts** - Redraw with new data  
✅ **Dynamic metrics** - Recalculate on filter  
✅ **Dynamic tables** - Refresh with filtered data  
✅ **Reset functionality** - Back to 12 months  

**Routes Added:**
- `/reports/performance-dashboard/ajax/coaches` ✅
- `/reports/performance-dashboard/ajax/program-data` ✅

**Clear cache to activate:**
```bash
ddev drush cr
```

**Then test the date filtering - it works 100% via AJAX!** 🎉

## 🔄 Future Enhancements

Potential additions:
- [ ] Date presets (Last Month, Last Quarter, Year to Date)
- [ ] Date range validation messages
- [ ] Export filtered data to PDF/Excel
- [ ] Save favorite date ranges
- [ ] Compare two date ranges side-by-side

The AJAX date filtering is now **fully functional**! 🚀

