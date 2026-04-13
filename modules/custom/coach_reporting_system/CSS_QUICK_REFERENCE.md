# Performance Dashboard CSS - Quick Reference Guide

**Version:** 3.3 | **Last Updated:** November 6, 2025

---

## 🎨 CSS Variables Reference

### Colors

#### Primary Colors
```css
var(--pd-primary)        /* #86f1ff - Main cyan color */
var(--pd-primary-hover)  /* #9ef5ff - Hover state */
var(--pd-primary-active) /* #80e5f2 - Active state */
```

#### Background Colors
```css
var(--pd-bg-darkest)  /* #101723 - Darkest background */
var(--pd-bg-dark)     /* #101729 - Dark background */
var(--pd-bg-medium)   /* #1a1f2e - Medium background */
var(--pd-bg-light)    /* #222736 - Light background */
```

#### Text Colors
```css
var(--pd-text-primary)   /* #ffffff - White text */
var(--pd-text-secondary) /* #a0a0a0 - Gray text */
var(--pd-text-accent)    /* #86f1ff - Cyan text */
```

#### Status Colors
```css
var(--pd-status-stars)    /* Green - 100%+ performance */
var(--pd-status-core)     /* Yellow - 60-99% performance */
var(--pd-status-laggards) /* Red - <60% performance */
var(--pd-status-positive) /* #07883f - Positive change */
var(--pd-status-negative) /* #dc3545 - Negative change */
```

### Spacing

```css
var(--pd-spacing-xs)  /* 0.5rem - 8px */
var(--pd-spacing-sm)  /* 1rem - 16px */
var(--pd-spacing-md)  /* 1.5rem - 24px */
var(--pd-spacing-lg)  /* 2rem - 32px */
var(--pd-spacing-xl)  /* 4rem - 64px */
```

### Typography

```css
var(--pd-font-family)    /* Inter, "Noto Sans", sans-serif */
var(--pd-font-size-sm)   /* 0.875rem - 14px */
var(--pd-font-size-base) /* 1rem - 16px */
var(--pd-font-size-lg)   /* 1.125rem - 18px */
var(--pd-font-size-xl)   /* 1.25rem - 20px */
var(--pd-font-size-2xl)  /* 1.75rem - 28px */
var(--pd-font-size-3xl)  /* 2.5rem - 40px */
```

### Border Radius

```css
var(--pd-radius-sm)   /* 0.25rem - 4px */
var(--pd-radius-md)   /* 0.5rem - 8px */
var(--pd-radius-lg)   /* 0.75rem - 12px */
var(--pd-radius-xl)   /* 1rem - 16px */
var(--pd-radius-pill) /* 1.5rem - 24px */
```

### Transitions

```css
var(--pd-transition-fast)   /* all 0.2s ease */
var(--pd-transition-normal) /* all 0.3s ease */
var(--pd-transition-slow)   /* all 0.35s ease-in-out */
```

---

## 🧩 Component Classes

### Layout Containers

```html
<!-- Main wrapper - must be the outermost container -->
<div class="performance-dashboard-wrapper">
  <div class="dashboard-container">
    <!-- Your content -->
  </div>
</div>
```

### Headers

```html
<h1 class="dashboard-main-title">Performance Dashboard</h1>
<p class="dashboard-subtitle">Subtitle text</p>
<h2 class="section-title">Section Title</h2>
```

### Filter Section

```html
<div class="dashboard-filters-section">
  <h3 class="filters-title">Filters</h3>
  <form class="dashboard-filter-form">
    <div class="filter-row">
      <div class="form-group">
        <label class="filter-label">
          <svg>...</svg>
          Label Text
        </label>
        <select class="dashboard-select">...</select>
      </div>
    </div>
  </form>
</div>
```

### Buttons

```html
<!-- Primary button with icon -->
<button class="btn-submit">
  <svg>...</svg>
  Button Text
</button>

<!-- Secondary button -->
<button class="btn-secondary">Button</button>

<!-- Primary button (alternative) -->
<button class="btn-primary">Button</button>
```

### Metric Cards

```html
<div class="metrics-grid">
  <div class="metric-card">
    <p class="metric-label">Label</p>
    <div class="metric-value">123</div>
    <p class="metric-change positive">+10%</p>
  </div>
</div>
```

### Status Badges

```html
<!-- Green - Completed/Stars -->
<span class="badge status-completed">Completed</span>

<!-- Yellow - In Progress/Core -->
<span class="badge status-in-progress">In Progress</span>

<!-- Red - Not Started/Laggards -->
<span class="badge status-not-started">Not Started</span>
```

### Progress Bars

```html
<div class="progress-container">
  <div class="progress-bar">
    <div class="progress-fill" style="width: 75%"></div>
  </div>
  <span class="progress-value">75</span>
</div>
```

### Accordion

```html
<div class="accordion">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button" data-bs-target="#collapse1">
        <span class="program-icon"><svg>...</svg></span>
        <span class="program-name">Program Name</span>
        <span class="accordion-icon"><svg>...</svg></span>
      </button>
    </h2>
    <div id="collapse1" class="accordion-collapse">
      <div class="accordion-body">
        <!-- Content -->
      </div>
    </div>
  </div>
</div>
```

### Tables

```html
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr><th>Header</th></tr>
    </thead>
    <tbody>
      <tr><td>Data</td></tr>
    </tbody>
  </table>
</div>
```

### Loading Overlay

```html
<div class="loading-overlay">
  <div class="loading-spinner"></div>
  <p>Loading data...</p>
</div>
```

### Empty State

```html
<div class="empty-state">
  <div class="empty-state-icon">
    <svg>...</svg>
  </div>
  <h3 class="empty-state-title">No Data</h3>
  <p class="empty-state-text">Message here</p>
</div>
```

---

## 🎯 Common Use Cases

### 1. Creating a Custom Metric Card

```html
<div class="metric-card">
  <p class="metric-label">Total Users</p>
  <div class="metric-value">1,234</div>
  <p class="metric-change positive">+12.5%</p>
</div>
```

### 2. Adding a Status Badge

```html
<!-- For completion status -->
<span class="badge status-completed">Complete</span>

<!-- For progress status -->
<span class="badge status-in-progress">In Progress</span>

<!-- For not started -->
<span class="badge status-not-started">Pending</span>
```

### 3. Creating a Form Field

```html
<div class="form-group">
  <label class="filter-label">
    <svg><!-- icon --></svg>
    Field Label
  </label>
  <select class="dashboard-select">
    <option value="">Select...</option>
    <option value="1">Option 1</option>
  </select>
</div>
```

### 4. Adding a Progress Indicator

```html
<div class="progress-container">
  <div class="progress-bar">
    <div class="progress-fill" style="width: 85%"></div>
  </div>
  <span class="progress-value">85</span>
</div>
```

---

## 🎨 Customization Examples

### Change Primary Color

```css
:root {
  --pd-primary: #ff6b6b;  /* Change to red */
  --pd-primary-hover: #ff8787;
  --pd-primary-active: #ff5252;
}
```

### Adjust Spacing

```css
:root {
  --pd-spacing-lg: 3rem;  /* Increase large spacing */
}
```

### Modify Font Sizes

```css
:root {
  --pd-font-size-3xl: 3rem;  /* Larger main title */
}
```

---

## 📱 Responsive Breakpoints

```css
/* Mobile phones */
@media (max-width: 480px) { }

/* Tablets */
@media (max-width: 768px) { }

/* Desktops */
@media (max-width: 1200px) { }
```

---

## ♿ Accessibility Classes

### Screen Reader Only

```html
<span class="sr-only">Screen reader only text</span>
```

### Skip to Content

```html
<a href="#main-content" class="skip-to-content">Skip to content</a>
```

---

## 🚀 Performance Tips

### 1. Use CSS Variables
✅ **Good:**
```css
.my-component {
  color: var(--pd-text-primary);
}
```

❌ **Bad:**
```css
.my-component {
  color: #ffffff;
}
```

### 2. Leverage Existing Classes
✅ **Good:**
```html
<button class="btn-submit">Click me</button>
```

❌ **Bad:**
```html
<button style="background: #86f1ff; padding: 1rem;">Click me</button>
```

### 3. Use Semantic HTML
✅ **Good:**
```html
<button type="button" class="btn-submit">Submit</button>
```

❌ **Bad:**
```html
<div onclick="submit()" class="btn-submit">Submit</div>
```

---

## 🔍 Debugging Tips

### Check Variable Values

```javascript
// In browser console
const root = document.documentElement;
const primaryColor = getComputedStyle(root)
  .getPropertyValue('--pd-primary');
console.log(primaryColor); // #86f1ff
```

### Override for Testing

```css
/* Temporarily change in browser DevTools */
:root {
  --pd-primary: red !important;
}
```

---

## 🎭 Animation Classes

### Available Animations

```css
/* Spin (for loaders) */
animation: spin 1s linear infinite;

/* Shimmer (for progress bars) */
animation: shimmer 2s infinite;

/* Fade In (for overlays) */
animation: fadeIn 0.3s ease;

/* Pulse (for loading text) */
animation: pulse 2s ease-in-out infinite;
```

---

## 📊 Status Color Guide

| Status | Class | Color | Use Case |
|--------|-------|-------|----------|
| **Stars** | `.status-completed` | 🟢 Green | 100%+ performance |
| **Core** | `.status-in-progress` | 🟡 Yellow | 60-99% performance |
| **Laggards** | `.status-not-started` | 🔴 Red | <60% performance |

---

## 🛠️ Utility Classes

### Text Alignment
```html
<div style="text-align: center;">Centered</div>
```

### Visibility
```html
<div class="sr-only">Hidden but accessible</div>
```

---

## 💡 Best Practices

### ✅ Do's

1. **Use CSS variables** for colors and spacing
2. **Follow BEM-like naming** for new components
3. **Test on multiple browsers** before deploying
4. **Check accessibility** with keyboard navigation
5. **Use semantic HTML** elements
6. **Keep specificity low** for easier overrides

### ❌ Don'ts

1. **Don't use inline styles** (use classes)
2. **Don't hardcode colors** (use variables)
3. **Don't ignore responsive design**
4. **Don't skip accessibility features**
5. **Don't use !important** unless necessary
6. **Don't create duplicate styles**

---

## 📦 Component Checklist

When creating new components:

- [ ] Uses CSS variables
- [ ] Has hover states
- [ ] Has focus states
- [ ] Is keyboard accessible
- [ ] Works on mobile
- [ ] Supports dark theme
- [ ] Has proper semantics
- [ ] Documented with comments

---

## 🔗 Related Files

```
/modules/custom/coach_reporting_system/
├── css/
│   ├── performance-dashboard.css       ← This file
│   ├── report-result.css
│   └── on-job-performance.css
├── js/
│   └── performance-dashboard.js
├── templates/
│   ├── performance-dashboard.html.twig
│   └── performance-dashboard-accordion.html.twig
└── coach_reporting_system.libraries.yml
```

---

## 📞 Support

### Questions?
- Check the main documentation: `CSS_IMPROVEMENTS_SUMMARY.md`
- Review template files for usage examples
- Test in browser DevTools

### Need Help?
- Check existing components for patterns
- Use CSS variables for consistency
- Follow established naming conventions

---

## 🎓 Learning Resources

### CSS Variables
- [MDN - CSS Custom Properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)

### Flexbox
- [CSS-Tricks - Flexbox Guide](https://css-tricks.com/snippets/css/a-guide-to-flexbox/)

### Grid
- [CSS-Tricks - Grid Guide](https://css-tricks.com/snippets/css/complete-guide-grid/)

### Accessibility
- [WebAIM - Web Accessibility](https://webaim.org/)

---

**Quick Start:** Copy component markup → Adjust text/data → Test responsiveness → Deploy! ✅

---

**Version:** 3.3 | **Status:** Production Ready | **Linting Errors:** 0 ✅











