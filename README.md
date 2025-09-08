# ojs-codecheck

An OJS Plugin to streamline codechecking of submissions and display CODECHECK certificates.

## About

This plugin integrates the [CODECHECK](https://codecheck.org.uk/) process into Open Journal Systems (OJS), allowing journals to streamline the code and computational reproducibility checking of scholarly submissions. The plugin provides tools for managing codechecks, displaying certificates, and ensuring computational transparency in published research.

## Features

- **Certificate Display**: Automatically display CODECHECK certificates for verified submissions
- **Submission Integration**: Seamless integration with OJS submission workflow
- **Certificate Verification**: Built-in tools for verifying CODECHECK certificates
- **Customizable Settings**: Configure CODECHECK display preferences and requirements

## Installation

1. Download the plugin from the [releases page](https://github.com/codecheckers/ojs-codecheck/releases)
2. Extract the plugin to your OJS `plugins/generic/` directory
3. Navigate to **Settings → Website → Plugins** in your OJS admin panel
4. Find "CODECHECK" and click **Enable**
5. Configure the plugin settings as needed

## Version Compatibility

The `1.x.y.z` versions of this plugin are compatible with OJS 3.5.x.

| Plugin Version | OJS Version | Status |
|----------------|-------------|---------|
| 1.0.0.0        | 3.5.0+      | Active Development |

## Color Scheme

This plugin follows the official CODECHECK brand guidelines and integrates with OJS design patterns.

### Primary Colors

| Color | Hex Code | Usage | Source |
|-------|----------|-------|---------|
| **CODECHECK Main Green** | `#008033` | Primary brand color, certificates, badges | [Official CODECHECK brand](https://github.com/codecheckers/codecheckers.github.io#logo-and-badge) |
| **CODECHECK Dark Green** | `#006629` | Hover states, borders, emphasis | Derived from main green (80% brightness) |
| **CODECHECK Light Green** | `#e8f5e8` | Certificate backgrounds, success states | Derived from main green (95% lightness) |

### Secondary Colors

| Color | Hex Code | Usage | Source |
|-------|----------|-------|---------|
| **Info Background** | `#d1ecf1` | Information boxes, notices | Bootstrap info (OJS compatibility) |
| **Info Border** | `#d4edda` | Information box borders | Bootstrap info (OJS compatibility) |
| **Info Text** | `#0c5460` | Information text, labels | Bootstrap info (OJS compatibility) |
| **Details Text** | `#495057` | Secondary text, descriptions | Bootstrap neutral (OJS compatibility) |
| **Form Borders** | `#ced4da` | Input borders, form elements | Bootstrap neutral (OJS compatibility) |
| **Background Light** | `#f8fff9` to `#e8f5e8` | Light backgrounds, certificate gradients | Custom light green variants |

### Color Usage Guidelines

- **Primary Green (`#008033`)**: Use for all CODECHECK-specific elements (certificates, badges, primary actions)
- **Secondary Colors**: Use for supporting UI elements that need to integrate with OJS design
- **Gradients**: Combine primary green variants for certificate backgrounds and special elements
- **Accessibility**: All color combinations meet WCAG 2.1 AA contrast requirements

## Usage

### For Journal Managers

1. **Enable the Plugin**: Activate through the plugin management interface
2. **Configure Settings**: Set up display preferences and verification options
3. **Workflow Integration**: The plugin automatically integrates with your submission workflow
4. **Monitor Certificates**: View and manage CODECHECK certificates through the admin interface

### For Authors

1. **Submit Code**: Include computational materials with your submission
2. **CODECHECK Process**: Work with codecheckers to verify your computational work
3. **Certificate Integration**: Certificates are automatically displayed once verification is complete

### For Readers

1. **View Certificates**: See CODECHECK certificates on published articles
2. **Verify Authenticity**: Click verification buttons to confirm certificate validity
3. **Access Materials**: Links to computational materials and reproduction repositories

## Development

### Requirements

- OJS 3.5.0 or later
- PHP 8.2.0 or later

### File Structure

```
codecheck/
├── classes/                    # Plugin classes
│   ├── Constants.php           # Plugin constants and definitions
│   ├── FrontEnd/               # Frontend display components
│   │   └── ArticleDetails.php  # Article page integration
│   └── Settings/               # Settings management
│       ├── Actions.php         # Settings actions and handlers
│       ├── Manage.php          # Settings management logic
│       └── SettingsForm.php    # Settings form handling
├── CodecheckPlugin.php         # Main plugin class
├── cypress/                    # End-to-end testing
│   └── tests/
│       └── functional/
│           └── CodecheckPlugin.cy.js  # Cypress test suite
├── LICENSE                     # License file
├── locale/                     # Internationalization
│   └── en/
│       └── locale.po          # English localization strings
├── README.md                  # This documentation
├── templates/                 # Template files
│   ├── codecheck-assets.tpl  # CSS/JS assets and styling
│   └── settings.tpl          # Plugin settings form template
└── version.xml               # Plugin metadata and version info
```

### Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes following the coding standards
4. Update tests and documentation
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Coding Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use meaningful variable and function names
- Document all public methods and classes
- Maintain backward compatibility within major versions

## License

Copyright (c) 2025 CODECHECK Initiative

This program is free software; you can redistribute it and/or modify it under the terms of the Apache License Version 2.0, see file [LICENSE](LICENSE).

## Support

- **Documentation**: [CODECHECK Guide](https://codecheck.org.uk/guide/)
- **Issues**: [GitHub Issues](https://github.com/codecheckers/ojs-codecheck/issues)
- **Community**: [CODECHECK Community](https://codecheck.org.uk/get-involved/)
- **Email**: For sensitive issues, contact the CODECHECK team directly at [team@cdchck.science](mailto:team@cdchck.science)

## Changelog

### Version 1.0.0.0 (Development)

- Initial plugin structure
- OJS 3.5.x compatibility
- Color scheme documentation

## Acknowledgments

The [CHECK-PUB](https://codecheck.org.uk/pub/) project (2025-2026) is empored by [TU Delft Library](https://www.tudelft.nl/en/library/).

<img src="https://codecheck.org.uk/img/TUDelft_logo_rgb.png" alt="TU Delft Library Logo" width="240">

## Related Projects

- [CODECHECK](https://codecheck.org.uk/)
- [OJS](https://pkp.sfu.ca/software/ojs/) by [PKP](https://pkp.sfu.ca/)
