# Senior Design Plugin

## Description

Created as a shortcode plugin for the CREOL WordPress website to display the CPT Senior Design Projects on the CREOL senior design page. WordPress admins also have the ability to bulk add/update the CPT by uploading a ZIP file according to the plugin instructions.

## Documentation

Visit our [Senior Design Wiki](https://github.com/UCF/Senior-Design/wiki) for in-depth information, installation instructions, and more.

## Changelog

### 2.0.0 - UI/UX Overhaul
- Introduced advanced filtering and search capabilities using AJAX to update project listings without a page reload.
- Projects are now grouped by semester and sorted using a custom taxonomy term meta ("semester_date") for improved readability.
- Implemented transient caching to boost performance on project queries.
- Enhanced user interface with multi-select dropdowns for academic years and semesters.
- Attached important files and student contributors directly to project cards.

### 1.5.0 - CSV to CPT Pipeline
- Added a pipeline for WP admins to upload a ZIP folder to bulk add/update post of the CPT Senior Design Projects
- Instructions have been created for the professor and web dev to understand how to format the ZIP folder and CSV file

### 1.0.0 - Initial Release
- Created search functionality with page reload.
- Display Senior Design projects as cards with links to their WP post.
- Implemented pagination.

## Development Setup

Compiled, minified CSS and JS files are included. Changes to these assets should be tracked with git so that installations from the repository are fully functional out-of-the-box.

For debugging during development, enable [debug mode in wp-config.php](https://codex.wordpress.org/Debugging_in_WordPress).

### Requirements
- Node v16+
- gulp-cli

### Instructions
1. Clone the Senior Design repository into your WordPress installation's plugins directory:
    `git clone https://github.com/UCF/Senior-Design.git`
2. Change into the new directory with `cd Senior-Design` and run `npm install` to install required packages.
3. (Optional) To enable BrowserSync:
    - Copy `gulp-config.template.json` to `gulp-config.json`.
    - Set `"sync": true` and adjust `"syncTarget"` to the URL of your local WordPress site (e.g., `http://localhost/wordpress/my-site/`).
    - Consult `gulpfile.js` for all configurable options.
4. Run `gulp default` to compile assets.
5. Set up your WordPress site and install any necessary plugin dependencies. See [Installation Requirements](https://github.com/UCF/Senior-Design/wiki/Installation#installation-requirements) for details.
6. Activate the plugin from the WordPress admin area.
7. Configure plugin settings through the designated admin menu.
8. Use `gulp watch` to monitor changes to CSS/JS files. If BrowserSync is enabled, your browser will automatically refresh upon file changes.

## Contributing

To report bugs or suggest features, please review our [Contributing Guidelines](https://github.com/UCF/Senior-Design/blob/master/CONTRIBUTING.md). We welcome your input!
