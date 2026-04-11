# Performance Dashboard - Quick Start Guide

## 🎯 What You Have

A **Dynamic Performance Dashboard** with:
- Company & Coach filter dropdowns
- Program-wise accordion layout
- Real-time data from database
- Google Charts visualizations
- Fully responsive design

## ⚡ 3-Step Setup

### 1. Clear Cache
```bash
drush cr
```

### 2. Visit Dashboard
Go to: **`/reports/performance-dashboard`**

### 3. Use Filters
1. Select a **Company** from dropdown
2. Select a **Coach** from dropdown (auto-populates)
3. Click **"View Dashboard"** button
4. View program accordions with complete dashboards!

## 📍 URL
```
https://new.remynd4.com.ddev.site/reports/performance-dashboard
```

## 🎬 Demo Flow

### Initial State
```
Visit: /reports/performance-dashboard
├── See filter dropdowns (Company & Coach)
├── Coach dropdown is disabled (empty)
└── Empty state message displayed
```

### Select Company
```
Select: Company from dropdown
├── Page reloads
├── Coach dropdown populates with coaches for that company
└── Coach dropdown becomes enabled
```

### Select Coach & View
```
Select: Coach from dropdown
Click: "View Dashboard" button
├── Page reloads with both filters
├── Programs are queried from database
└── Accordion with program dashboards appears!
```

## 📊 What's Inside Each Program Accordion

**Each expandable section contains:**

### 5 Metric Cards
1. Number of Users Coached
2. Coaching Sessions
3. Behavioral Progress
4. On-The-Job Progress
5. ROI (Return on Investment)

### 4 Google Charts
1. Overview of Coaching Results (Line)
2. Competency Trends (Line)
3. Stars, Core & Laggards (Bar)
4. Coaching Sessions (Bar)

### 2 Data Tables
1. Action Report (Competencies)
2. Users Report (Employee Performance)

## 🔧 Configuration

### Change Access Permissions
```
/admin/people/permissions
→ Find "access coach reporting"
→ Check for appropriate roles
```

### Add to Navigation Menu
```yaml
# In coach_reporting_system.links.menu.yml
coach_reporting_system.performance_dashboard:
  title: 'Performance Dashboard'
  route_name: coach_reporting_system.performance_dashboard
  menu_name: main
  weight: 10
```

## 🔗 URL Parameters

### Basic (Required)
- `?company=2338` - Company ID
- `&coach=456` - Coach ID

### Optional Filters
- `&employee=4001` - Specific employee
- `&report_type=latest` - Report type (latest/overtime)
- `&from=2024-01-01` - Date from
- `&to=2024-12-31` - Date to

### Example URLs

**Simple:**
```
/reports/performance-dashboard?company=2338&coach=456
```

**With Employee:**
```
/reports/performance-dashboard?company=2338&coach=456&employee=4001
```

**With Date Range:**
```
/reports/performance-dashboard?company=2338&coach=456&report_type=overtime&from=2024-01-01&to=2024-12-31
```

## 📱 Device Support

- ✅ Desktop (Full layout)
- ✅ Tablet (Stacked layout)
- ✅ Mobile (Touch-friendly)

## 🎨 Features

### Dynamic Filters
- Company dropdown auto-populates from database
- Coach dropdown loads based on selected company
- Real-time filter validation

### Program Accordions
- Expandable/collapsible sections
- Each program has its own dashboard
- First program opens by default
- Smooth animations

### Real Data
- Pulls from `coach_reporting_session` table
- Calculates metrics from session answers
- Uses on-the-job performance data
- Shows actual user reports

## 🐛 Common Issues

### "No programs found"
**Cause:** No sessions for that company + coach combination
**Fix:** Verify sessions exist in database

### Coach dropdown empty
**Cause:** No coaches linked to company
**Fix:** Check coach profiles or session data

### Charts not showing
**Cause:** JavaScript error or Google Charts not loading
**Fix:** 
1. Check browser console (F12)
2. Clear cache: `drush cr`
3. Verify internet connection

## 💡 Tips

### Link from Other Pages
```html
<a href="/reports/performance-dashboard?company={{ company_id }}&coach={{ coach_id }}">
  View Dashboard
</a>
```

### Bookmark with Filters
Save URL with your common filters:
```
/reports/performance-dashboard?company=2338&coach=456
```

### Testing
Use different company/coach combinations to see different programs and data.

## 📚 More Documentation

- `PERFORMANCE_DASHBOARD_DYNAMIC.md` - Complete technical documentation
- `PERFORMANCE_DASHBOARD_BLOCK.md` - Block version documentation
- `PERFORMANCE_DASHBOARD_SETUP.md` - Original setup guide

## 🆘 Need Help?

1. Check browser console (F12) for errors
2. Check Drupal logs: `/admin/reports/dblog`
3. Clear cache: `drush cr`
4. Review database for sample data
5. Check permissions: `/admin/people/permissions`

## 📝 Summary

**Access:** `/reports/performance-dashboard`

**Flow:**
1. Select Company
2. Select Coach
3. Click "View Dashboard"
4. See Program Accordions
5. Expand programs to view dashboards

**That's it!** 🎉

---

**Quick Test:**
```bash
# Clear cache
drush cr

# Visit dashboard
# Select filters
# View results
```

**Enjoy your dynamic performance dashboard!** 🚀


