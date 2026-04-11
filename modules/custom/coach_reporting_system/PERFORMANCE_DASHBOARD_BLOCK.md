# Performance Dashboard Block

## Overview
The Performance Dashboard Block is a Drupal block that displays comprehensive coaching performance metrics, charts, and reports. It can be placed anywhere on your Drupal site through the Block Layout interface.

## Features

### 1. **Key Metrics Cards**
- Number of Users Coached
- Coaching Sessions
- Behavioral Progress
- On the Job Progress
- ROI (Return on Investment)

Each metric includes the current value and percentage change.

### 2. **Interactive Charts (Google Charts)**
Four interactive charts powered by Google Charts API:
- **Overview of Coaching Results** - Line chart showing coaching progress over time
- **Competency Trends** - Line chart displaying competency development
- **Stars, Core and Laggards** - Bar chart showing department distribution
- **Coaching Sessions** - Bar chart showing sessions by age group

### 3. **Action Report Table**
Searchable table displaying:
- Competencies being developed
- Status (In Progress, Completed, Not Started)
- Due dates
- Progress bars

### 4. **Users Report Table**
Comprehensive user performance table with:
- User details
- Performance comparison
- Coach assignment
- Latest and previous performance metrics
- Session dates (next, last, first)
- Pagination support

### 5. **Date Range Filter**
Collapsible date range selector to filter dashboard data by time period.

## Installation

### 1. Enable the Module
If not already enabled, enable the Coach Reporting System module:
```bash
drush en coach_reporting_system
```

### 2. Clear Caches
After installation, clear Drupal caches:
```bash
drush cr
```

## Usage

### Adding the Block to Your Site

1. **Navigate to Block Layout**
   - Go to `Structure` → `Block layout` in your Drupal admin menu
   - Or visit: `/admin/structure/block`

2. **Choose a Region**
   - Click `Place block` in the region where you want the dashboard to appear
   - Common regions: Content, Sidebar, Header, Footer

3. **Find the Block**
   - Search for "Performance Dashboard" in the block list
   - Or browse the "Coach Reporting System" category

4. **Configure the Block**
   - Click `Place block` next to "Performance Dashboard"
   - Configure block settings:
     - **Title**: Keep default or customize
     - **Display title**: Check/uncheck to show/hide block title
     - **Visibility**: Configure which pages show the block
     - **Roles**: Restrict access by user role if needed

5. **Save**
   - Click `Save block` to add it to your site

### Block Placement Examples

#### Example 1: Full-Width Dashboard on Dedicated Page
1. Create a new page or use existing page
2. Place the block in the "Content" region
3. Set visibility to show only on `/dashboard` path

#### Example 2: Sidebar Widget
1. Place in "Sidebar First" or "Sidebar Second" region
2. Configure to show on multiple pages
3. Restrict to specific user roles (e.g., Coach, Company)

#### Example 3: Admin Dashboard
1. Place in admin theme's main content area
2. Set visibility to admin paths only
3. Restrict to admin roles

## Customization

### Connecting to Real Data

The block currently displays sample data. To connect to your actual database:

1. Open the block file:
   ```
   modules/custom/coach_reporting_system/src/Plugin/Block/PerformanceDashboardBlock.php
   ```

2. Modify these methods with your database queries:
   - `getMetrics()` - Update with real metrics calculations
   - `getChartData()` - Query actual chart data
   - `getActionReport()` - Pull from competencies table
   - `getUsersReport()` - Query user performance data

### Example Database Query
```php
protected function getMetrics() {
  $users_coached = $this->database->query(
    "SELECT COUNT(DISTINCT user_id) FROM {coaching_sessions}"
  )->fetchField();

  return [
    'users_coached' => [
      'value' => $users_coached,
      'change' => $this->calculateChange($users_coached),
    ],
    // ... other metrics
  ];
}
```

### Styling Customization

The dashboard uses a dark theme by default. To customize:

1. Edit the CSS file:
   ```
   modules/custom/coach_reporting_system/css/performance-dashboard.css
   ```

2. Key color variables to change:
   - Background: `#101723`
   - Primary color: `#00bff2`
   - Card background: `#101729`
   - Text color: `#ffffff`

### JavaScript Customization

To modify chart behavior:

1. Edit the JavaScript file:
   ```
   modules/custom/coach_reporting_system/js/performance-dashboard.js
   ```

2. Customize chart options in `drawLineChart()` and `drawBarChart()` functions

## Chart Data Format

### Line Charts (Overview & Competency)
```javascript
[
  ['Month', 'Score'],
  ['Jan', 70],
  ['Feb', 75],
  // ... more data
]
```

### Bar Charts (Department & Sessions)
```javascript
[
  ['Category', 'Value'],
  ['Engineering', 75],
  ['Sales', 30],
  // ... more data
]
```

## Permissions

The block respects Drupal's permission system. To control access:

1. Go to `People` → `Permissions`
2. Find "Coach Reporting System" permissions
3. Configure which roles can view the block

## Caching

The block is cached for 1 hour (3600 seconds) by default. To change:

1. Edit `PerformanceDashboardBlock.php`
2. Modify the `getCacheMaxAge()` method:
```php
public function getCacheMaxAge() {
  // Return seconds (e.g., 7200 = 2 hours)
  return 7200;
}
```

To disable caching entirely:
```php
public function getCacheMaxAge() {
  return 0;
}
```

## Troubleshooting

### Block Not Appearing
1. Clear Drupal cache: `drush cr`
2. Check block is placed in correct region
3. Verify visibility settings
4. Check user permissions

### Charts Not Loading
1. Check browser console for JavaScript errors
2. Verify Google Charts API is loading (check network tab)
3. Ensure `performance-dashboard.js` is loading
4. Clear browser cache

### Styling Issues
1. Clear Drupal cache
2. Check CSS file is loading
3. Verify no theme CSS conflicts
4. Check browser console for CSS errors

### Database Errors
1. Check database connection
2. Verify table names in queries
3. Check user permissions for database access
4. Review Drupal logs: `Reports` → `Recent log messages`

## Browser Compatibility

The dashboard is compatible with:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

Google Charts requires JavaScript to be enabled.

## Dependencies

- Drupal Core 9.x or 10.x
- PHP 7.4+
- Internet connection (for Google Charts API)

## File Structure

```
coach_reporting_system/
├── src/
│   └── Plugin/
│       └── Block/
│           └── PerformanceDashboardBlock.php
├── templates/
│   └── performance-dashboard.html.twig
├── css/
│   └── performance-dashboard.css
├── js/
│   └── performance-dashboard.js
├── coach_reporting_system.libraries.yml
├── coach_reporting_system.module
└── PERFORMANCE_DASHBOARD_BLOCK.md
```

## Support

For issues or questions:
1. Check Drupal logs: `Reports` → `Recent log messages`
2. Review this documentation
3. Check the module's issue queue
4. Contact your system administrator

## Future Enhancements

Possible improvements:
- Export functionality (PDF, CSV)
- Email reports
- Advanced filtering options
- Custom date ranges
- Real-time data updates
- More chart types
- Drill-down capabilities
- Mobile-optimized view

## License

This module is licensed under the same terms as Drupal core.


