<a href="https://codecheck.org.uk/"><img src="https://codecheck.org.uk/img/codecheck_logo.svg" alt="Logo" width="150"/></a>

# <a href="https://codecheck.org.uk/"><img src="https://avatars.githubusercontent.com/u/51200812?s=48&v=4" alt="Logo" width="25"/></a>  ojs-codecheck

[![repo status](https://www.repostatus.org/badges/latest/wip.svg)](https://www.repostatus.org/#wip)
[![License](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![Contributions - welcome](https://img.shields.io/badge/Contributions-welcome-blueviolet)](https://github.com/codecheckers/ojs-codecheck/blob/main/CONTRIBUTING.md)
<br />

An [OJS Plugin](https://docs.pkp.sfu.ca/dev/plugin-guide/en/) to streamline codechecking of submissions and display of [CODECHECK](https://codecheck.org.uk/) certificates.

## About

This plugin integrates the [CODECHECK](https://codecheck.org.uk/) process into the submission and review workflows within Open Journal Systems (OJS), allowing journals to streamline code and computational reproducibility checking of scholarly submissions. The plugin provides tools for metadata creation and certificate deposition, displaying certificates, ensuring computational transparency in published research, as well as certificate and metadata publication. Therefore the plugin connects seamlessly with the CODECHECK infrastructure.

The ojs-codecheck plugin development was started as part of the [CHECK-PUB](https://codecheck.org.uk/pub/) project with support from TU Delft Library.

## Features

- **Submission integration**: Seamless integration with the OJS submission and review workflow
- **CODECHECK metadata**: Built-in tools for creation and publication of CODECHECK metadata
<!--
- **Certificate Creation**: Built-in workflow to create CODECHECK certificates from metadata
- **Certificate Verification**: Built-in tools for verifying CODECHECK certificates
-->
- **Certificate display**: Automatically display CODECHECK certificates for verified submissions
- **Customizable settings**: Configure CODECHECK workflow and display preferences

## Installation

1. Download the plugin from the [releases page](https://github.com/codecheckers/ojs-codecheck/releases)
2. Extract the plugin to your OJS `plugins/generic/` directory
3. Navigate to **Settings → Website → Plugins** in your OJS admin panel
4. Find "CODECHECK" and click **Enable**
5. Configure the plugin settings as needed

## Changelog

If you are interested in the changes made to this project and the different versions, feel free to view the projects [Changelog](CHANGELOG.md).

### Version compatibility

The `1.y.z` versions of this plugin are compatible with OJS `3.5.x`.

For the full features of each version, feel free to look into the [Changelog](#changelog).

| Plugin Version | OJS Version | Status             |
|----------------|-------------|--------------------|
| `Unreleased`   | `3.5.0+`    | Active Development |
| `1.y.z`        | `3.5.0+`    | Active Development |

## Color scheme

This plugin follows the CODECHECK brand guidelines and integrates with OJS design patterns.

### Primary colors

| Color | Hex Code | Usage | Source |
|-------|----------|-------|---------|
| **CODECHECK Main Green** | `#008033` | Primary brand color, certificates, badges | [CODECHECK brand](https://github.com/codecheckers/codecheckers.github.io#logo-and-badge) |
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

### Color usage guidelines

- **Primary Green (`#008033`)**: Use for all CODECHECK-specific elements (certificates, badges, primary actions)
- **Secondary Colors**: Use for supporting UI elements that need to integrate with OJS design
- **Gradients**: Combine primary green variants for certificate backgrounds and special elements
- **Accessibility**: All color combinations meet WCAG 2.1 AA contrast requirements

## Usage

### For codecheckers

1. **Metadata creation**: Assistance for creating a CODECHECK metadata file `codecheck.yml`
2. **Metadata import**: If `codecheck.yml` already exists, you can also use it instead
3. **Manage CODECHECKs**: The plugin enables you to manage your different ongoing CODECHECK tasks

### For journal managers and editors

1. **Manage the plugin**: Activate through the plugin management interface and set up display preferences and workflow options
3. **Workflow integration**: The plugin automatically integrates with your submission workflow
4. **Monitor certificates**: View and manage CODECHECK certificates through the admin interface

### For authors

<!-- 1. **Submit Code**: Include computational materials with your submission -->
2. **CODECHECK Process**: Work with codecheckers to verify your computational work
3. **Certificate Integration**: Certificates are automatically displayed once verification is complete

### For Readers

1. **View certificates**: Explore CODECHECK certificates on published articles
2. **Access materials**: Links to computational materials and repositories

## CODECHECK Status System

The plugin tracks CODECHECK progress through a status system displayed in the review workflow.

### Status Levels

| Status | Badge Color | Criteria | Description |
|--------|------------|----------|-------------|
| **Pending** | Gray | No metadata exists | CODECHECK process has not started |
| **In Progress** | Yellow/Warning | Metadata exists but incomplete | Codechecker is working on verification |
| **Complete** | Green/Success | Certificate ID and check time both present | CODECHECK verification is finished |

### Status Implementation

The status is determined in `CodecheckReviewDisplay.vue` using the following logic:
```javascript
function getStatus() {
  if (metadata.value.certificate && metadata.value.checkTime) {
    return 'complete';
  } else if (hasMetadata.value) {
    return 'in-progress';
  }
  return 'pending';
}
```

## Development

### Requirements

- OJS 3.5.0 or later
- PHP 8.2.0 or later
- Node.js 16+ (for frontend development)
- npm or yarn

### Frontend Development

This plugin uses **Vite** for building Vue.js components.

#### Install dependencies
```bash
npm install
```

#### Build for production
```bash
npm run build
```

This compiles Vue components and JavaScript into the `public/` directory.

#### Watch mode (development)
```bash
npm run dev
```

Use during development to automatically rebuild on file changes.

### Frontend Structure
```bash
├── resources/
│   └── js/
│       ├── Components/*    # Vue 3 components
│       └── main.js         # Entry point
├── css/*                   # Minimal plugin CSS stylesheets
└── public/
    └── build/              # Compiled assets (generated by Vite)
        ├── build.iife.js
        └── build.css
```

### Creating a Release

1. Install dependencies: `npm install`
2. Build the frontend: `npm run build`
3. Ensure that:
   - `public/build/` exists (**ignored by git**)
   - and contains the compiled files (`build.iife.js` and `build.css`)
4. [Test](https://github.com/codecheckers/ojs-codecheck/?tab=readme-ov-file#testing) the plugin ([Frontend Component Tests](https://github.com/codecheckers/ojs-codecheck/?tab=readme-ov-file#frontend-component-tests) and [PHP Unit Tests](https://github.com/codecheckers/ojs-codecheck/?tab=readme-ov-file#frontend-component-tests))
5. Create release tag
6. Package plugin:
   - **Include**: `public/build/`, all PHP files, templates, locale
   - **Exclude**: `node_modules/`, `resources/` (source files), `.env`

### File Structure

```bash
codecheck/
├── CHANGELOG.md               # The projects Changelog with details for each version
├── CONTRIBUTING.md            # Contibution guidelines for this repo
├── CodecheckPlugin.php        # Main plugin class
├── LICENSE                    # License file
├── README.md                  # This documentation
├── api/*                      # API related classes (e.g. CodecheckApiHandler)
├── assets/*                   # Assets (e.g. images)
├── classes/*                  # Plugin classes
├── composer.json              # composer json-file
├── composer.lock              # composer lock-file
├── css/*                      # CODECHECK CSS stylesheets
├── cypress/tests/functional/  # End-to-end testing
│   └── CodecheckPlugin.cy.js  # Cypress test suite
├── locale/*                   # Internationalization (language localization strings)
├── package-lock.json
├── package.json
├── public/build/*             # NPM realese build files
├── resources/js/*             # The Vue.js Components
├── schema.xml                 # CODECHECK metadata table schema file
├── templates/*                # HTML templates
├── tests/*                    # The ojs-codecheck plugin unit tests
├── version.xml                # Plugin metadata and version info
└── vite.config.js             # The config file for Vite.js

```

### Contributing

If you want to contribute to this project, we kindly ask you to follow our [contribution guidelines](CONTRIBUTING.md).

### Api

If you want to add a new Api Endpoint, please first register it inside the constructor of the CODECHECK Api Handler like this:

```php
$this->endpoints = [
  'Your method (e.g. GET, POST, ...)' => [
      [
          'route' => 'your endpoint route',
          'handler' => [$this, 'yourFunction'],
          'roles' => $this->roles,
      ],
  ],
];
```

Then define what `yourFunction()` should do when your Endpoint is called. It is important, that the function creates a JSON response.

```php
private function yourFunction(): void
{   
    /* Do some calculations */

    // Serve your Api endpoint route
    // success should be true or false along with a matching HTML response code like 200 or 404
    $this->response->response([
        'success' => true,
        'payload' => $test,
    ], 200);
}
```

## License

Copyright (c) 2025 CODECHECK Initiative

This program is free software; you can redistribute it and/or modify it under the terms of the Apache License Version 2.0, see file [LICENSE](LICENSE).

## Support

- **Documentation**: [CODECHECK Guide](https://codecheck.org.uk/guide/)
- **Issues**: [GitHub Issues](https://github.com/codecheckers/ojs-codecheck/issues)
- **Community**: [CODECHECK Community](https://codecheck.org.uk/get-involved/)
- **Email**: For sensitive issues, contact the CODECHECK team directly at [team@cdchck.science](mailto:team@cdchck.science)

## Acknowledgments

The [CHECK-PUB](https://codecheck.org.uk/pub/) project (2025-2026) is empored by [TU Delft Library](https://www.tudelft.nl/en/library/).

<img src="https://codecheck.org.uk/img/TUDelft_logo_rgb.png" alt="TU Delft Library Logo" width="240">

## Related Projects

- [CODECHECK](https://codecheck.org.uk/)
- [OJS](https://pkp.sfu.ca/software/ojs/) by [PKP](https://pkp.sfu.ca/)
