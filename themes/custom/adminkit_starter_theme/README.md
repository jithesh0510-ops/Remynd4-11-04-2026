# AdminKit Starter Theme

A comprehensive Drupal 10 theme based on AdminKit Bootstrap 5 with enhanced Select2 integration and custom styling.

## Features

- **Bootstrap 5 Integration**: Modern, responsive design framework
- **Enhanced Select2 Styling**: Custom CSS and JavaScript for improved Select2 widgets
- **Dark/Light Mode**: Built-in theme switching capability
- **Responsive Design**: Mobile-first approach with breakpoint management
- **Accessibility**: WCAG 2.1 compliant with proper ARIA labels
- **Performance Optimized**: Minified assets and efficient loading

## Installation

1. Place the theme in `/themes/custom/adminkit_starter_theme/`
2. Enable the theme via Drupal admin or Drush:
   ```bash
   ddev drush theme:enable adminkit
   ddev drush config:set system.theme default adminkit
   ```

## Dependencies

- Drupal 10+
- Select2 module
- jQuery
- Bootstrap 5 (included)

## Libraries

### Global Styling
- Main theme CSS
- Dark mode CSS
- Custom Select2 CSS
- FontAwesome icons
- Google Fonts (Inter)

### JavaScript
- Theme toggle functionality
- Select2 initialization
- Responsive behavior
- Form enhancements

## Configuration

The theme includes several configuration options:

- **Theme Color**: Primary color scheme (#86f1ff)
- **Sidebar**: Collapsible navigation
- **Dark Mode**: Automatic/manual switching
- **Font Family**: Inter font family
- **Font Size**: Small/Medium/Large options
- **Border Radius**: Rounded corner preferences

## Select2 Enhancements

The theme includes comprehensive Select2 styling and functionality:

- **Custom CSS**: Enhanced visual appearance
- **Responsive Design**: Mobile-optimized dropdowns
- **Accessibility**: Proper ARIA labels and keyboard navigation
- **Theme Integration**: Consistent with AdminKit design
- **Performance**: Optimized loading and initialization

## Customization

### CSS Variables
Override theme colors by modifying CSS custom properties in `css/style.css`:

```css
:root {
  --primary-color: #86f1ff;
  --secondary-color: #4030ad;
  --background-color: #ffffff;
  --text-color: #374151;
}
```

### JavaScript Extensions
Add custom functionality by extending the theme's JavaScript behaviors in `js/select2-init.js`.

## File Structure

```
adminkit_starter_theme/
├── css/
│   ├── style.css              # Main theme styles
│   ├── dark.css               # Dark mode styles
│   └── select2-custom.css     # Select2 enhancements
├── js/
│   ├── app.js                 # Main theme JavaScript
│   ├── theme-toggle.js        # Dark mode toggle
│   └── select2-init.js        # Select2 initialization
├── templates/                 # Twig templates
├── config/
│   ├── install/              # Default configuration
│   └── schema/               # Configuration schema
├── adminkit.info.yml         # Theme definition
├── adminkit.libraries.yml    # Asset libraries
└── adminkit.theme           # Theme functions
```

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Performance

- **CSS**: Minified and optimized
- **JavaScript**: Lazy loading and efficient initialization
- **Images**: Optimized and responsive
- **Fonts**: Google Fonts with display=swap

## Accessibility

- WCAG 2.1 AA compliant
- Keyboard navigation support
- Screen reader compatible
- High contrast mode support
- Reduced motion preferences

## Support

For issues and feature requests, please refer to the project documentation or contact the development team.

## License

This theme is part of the coaching reporting system and follows the project's licensing terms.



