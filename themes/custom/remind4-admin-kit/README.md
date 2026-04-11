# Remind4 Admin Kit Theme

A Drupal 10 theme based on [AdminKit](https://adminkit.io/) Bootstrap 5 dashboard template.

## Features

- **Bootstrap 5 Integration**: Modern, responsive design framework
- **AdminKit Components**: Full AdminKit dashboard components and styling
- **Sidebar Navigation**: Collapsible sidebar with user profile
- **Responsive Design**: Mobile-first approach with breakpoint management
- **Accessibility**: WCAG 2.1 compliant with proper ARIA labels
- **Performance Optimized**: Minified assets and efficient loading

## Installation

1. Place the theme in `/themes/custom/remind4-admin-kit/`
2. Enable the theme via Drupal admin or Drush:
   ```bash
   ddev drush theme:enable remind4_admin_kit
   ddev drush config:set system.theme default remind4_admin_kit
   ```
3. Clear cache:
   ```bash
   ddev drush cr
   ```

## Dependencies

- Drupal 10+
- Bootstrap 5 (included via AdminKit)
- jQuery (Drupal core)
- Feather Icons (included in AdminKit JS)

## Libraries

### global-styling
- Main AdminKit CSS (`css/app.css`)
- Google Fonts (Inter)
- Main AdminKit JavaScript (`js/app.js`)

## Configuration

The theme uses AdminKit's default configuration. You can customize:

- **Sidebar**: Collapsible navigation sidebar
- **Navbar**: Top navigation bar with user menu
- **Layout**: Fluid container layout
- **Colors**: AdminKit default color scheme

## File Structure

```
remind4-admin-kit/
├── css/
│   └── app.css              # Compiled AdminKit CSS
├── js/
│   └── app.js               # Compiled AdminKit JavaScript
├── img/                      # Images and avatars
├── fonts/                    # Font files
├── templates/
│   └── page.html.twig       # Main page template
├── config/
│   ├── install/            # Default configuration
│   └── schema/             # Configuration schema
├── remind4_admin_kit.info.yml    # Theme definition
├── remind4_admin_kit.libraries.yml # Asset libraries
└── remind4_admin_kit.theme       # Theme functions
```

## Regions

The theme provides the following regions:

- **logo**: Logo area
- **site_name**: Site name
- **site_slogan**: Site slogan
- **header**: Header region (top navbar)
- **primary_menu**: Primary navigation menu
- **secondary_menu**: Secondary navigation menu
- **breadcrumb**: Breadcrumb navigation
- **highlighted**: Highlighted content
- **content**: Main content area
- **sidebar_first**: Sidebar navigation
- **sidebar_second**: Secondary sidebar
- **footer_first**: Footer first column
- **footer_second**: Footer second column
- **footer_third**: Footer third column
- **footer_fourth**: Footer fourth column
- **footer**: Footer region

## Customization

### CSS Variables
Override theme colors by modifying CSS custom properties in `css/app.css` or create a custom CSS file.

### JavaScript
AdminKit JavaScript is automatically initialized. The main functionality includes:
- Sidebar toggle
- Feather icons initialization
- Bootstrap components
- Theme switching (if enabled)

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Performance

- **CSS**: Minified and optimized
- **JavaScript**: Optimized and efficient
- **Images**: Optimized and responsive
- **Fonts**: Google Fonts with display=swap

## Accessibility

- WCAG 2.1 AA compliant
- Keyboard navigation support
- Screen reader compatible
- High contrast mode support

## Source

This theme is based on AdminKit v3.4.0, converted for Drupal 10.

**AdminKit**: https://adminkit.io/  
**License**: MIT (AdminKit) + Drupal GPL-2.0+

## Support

For issues and feature requests, please refer to the project documentation or contact the development team.

