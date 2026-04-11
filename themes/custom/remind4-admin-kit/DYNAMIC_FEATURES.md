# Remind4 Admin Kit - Dynamic Features

## ✅ All Features Are Now Dynamic

The theme has been updated to make all content dynamic instead of static. Here's what's been implemented:

## 🔄 Dynamic Components

### 1. **Sidebar Navigation**
- ✅ Uses Drupal menu system (`main` menu or `sidebar_first` region)
- ✅ Supports nested menus with collapsible submenus
- ✅ Active trail highlighting
- ✅ Menu template: `templates/menu/menu--main.html.twig`

### 2. **User Profile**
- ✅ Dynamic user data from Drupal user entity
- ✅ User picture from user profile field
- ✅ User name and email from account
- ✅ Profile links generated dynamically
- ✅ Admin links shown only for administrators

### 3. **Site Logo & Branding**
- ✅ Logo from theme settings or default
- ✅ Site name from Drupal configuration
- ✅ Dynamic logo path resolution

### 4. **Search Form**
- ✅ Drupal search block integration
- ✅ Default search form if no block placed
- ✅ Search form template: `templates/form/form--search-form.html.twig`
- ✅ Styled with AdminKit classes

### 5. **Notifications**
- ✅ Notification count from Drupal system messages
- ✅ Dynamic notification dropdown with actual messages
- ✅ Link to Drupal log viewer
- ✅ Hook for modules to add custom notifications: `hook_remind4_admin_kit_notification_count_alter()`

### 6. **User Menu**
- ✅ Dynamic account menu
- ✅ Profile link based on user ID
- ✅ Admin link for administrators
- ✅ Edit account link
- ✅ Logout link
- ✅ Account menu template: `templates/menu/menu--account.html.twig`

### 7. **Page Title**
- ✅ Dynamic from Drupal page title
- ✅ Falls back to node title or route title

### 8. **Breadcrumbs**
- ✅ Dynamic from Drupal breadcrumb system
- ✅ Rendered in breadcrumb region

### 9. **Messages**
- ✅ System messages from Drupal
- ✅ Status messages (success, error, warning)
- ✅ Highlighted region for important messages

### 10. **Footer**
- ✅ Multiple footer regions (footer_first, footer_second, footer_third, footer_fourth)
- ✅ Dynamic content from blocks
- ✅ Fallback to site name and copyright
- ✅ Current year generated dynamically

### 11. **Content**
- ✅ All content from Drupal page system
- ✅ Dynamic regions
- ✅ Block system integration

## 🎯 Preprocess Functions

The theme includes comprehensive preprocess functions:

- `remind4_admin_kit_preprocess_html()` - HTML-level variables
- `remind4_admin_kit_preprocess_page()` - Page-level variables (user data, site info, notifications)
- `remind4_admin_kit_preprocess_block()` - Block-level classes
- `remind4_admin_kit_preprocess_menu()` - Menu classes
- `remind4_admin_kit_preprocess_menu_link()` - Menu link classes and icons
- `remind4_admin_kit_preprocess_form()` - Form styling
- `remind4_admin_kit_preprocess_form_element()` - Form element styling

## 🔌 Hooks Available

### For Other Modules

```php
/**
 * Alter notification count for AdminKit theme.
 *
 * @param int $count
 *   The notification count to alter.
 */
function mymodule_remind4_admin_kit_notification_count_alter(&$count) {
  // Add custom notifications.
  $count += mymodule_get_custom_notification_count();
}
```

## 📋 Configuration

### Menu Setup
1. Create a menu named "Main" in Drupal
2. Add menu items
3. Place menu block in `sidebar_first` region
4. Or use `primary_menu` region

### Search Setup
1. Enable Search module
2. Place "Search form" block in `header` region
3. Or use default search form

### Notifications
- Automatically counts Drupal system messages
- Modules can extend via hook
- Link to `/admin/reports/dblog` for all logs

### User Profile
- Uses Drupal user entity
- Shows user picture if available
- Falls back to default avatar

## 🎨 Customization

All dynamic content can be customized:

1. **Override templates** in your subtheme
2. **Use preprocess hooks** to add custom variables
3. **Place blocks** in regions for custom content
4. **Extend hooks** for additional functionality

## ✨ Benefits

- ✅ No hardcoded content
- ✅ Fully integrated with Drupal
- ✅ Easy to customize
- ✅ Extensible via hooks
- ✅ Follows Drupal best practices
- ✅ Accessible and SEO-friendly

## 📝 Summary

Everything in the theme is now dynamic:
- Menus from Drupal menu system
- User data from Drupal user entities
- Content from Drupal blocks and regions
- Messages from Drupal system
- Search from Drupal search module
- Notifications from Drupal messages
- Footer from Drupal blocks

No static content remains - everything is driven by Drupal's content management system!

