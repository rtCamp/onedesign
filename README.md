![Banner V3](./.wordpress-org/banner.webp)

# OneDesign

Contributors: [rtcamp](https://profiles.wordpress.org/rtcamp), [parthnvaswani](https://github.com/parthnvaswani), [up1512001](https://github.com/up1512001), [singhakanshu00](https://github.com/singhakanshu00), [danish17](https://github.com/danish17), [aviral-mittal](https://github.com/aviral-mittal), [vaishaliagola27](https://github.com/vaishaliagola27), [rishavjeet](https://github.com/rishavjeet), [vishal4669](https://github.com/vishal4669), [iamimmanuelraj](https://github.com/iamimmanuelraj) [vishalkakadiya](https://github.com/vishalkakadiya)

Tags: OnePress, Pattern distribution, Pattern sync, OneDesign, WordPress multisite, WordPress network, Gutenberg, WordPress Site Editor, Block Patterns, Pattern management, WordPress plugin, Design consistency, Pattern library

This plugin is licensed under the GPL v2 or later.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](http://www.gnu.org/licenses/gpl-2.0.html)

This tool enables synchronization of block patterns and templates across multiple sites in a WordPress multisite network.

## Description

OneDesign allows you to define patterns and templates on a Governing site and apply them to brand sites, maintaining consistency across your network. The plugin provides an intuitive interface for browsing, searching, and applying patterns and templates, making it easy to maintain design consistency across all your network sites.

## Why OneDesign?

Managing multiple websites—whether for different brands, regions, or languages shouldn't mean reinventing the wheel each time. Instead of designing layouts from scratch for each site, OneDesign lets you create once and deploy anywhere, in just one click.

Built for enterprise teams, OneDesign unifies your design, editorial, and development processes across all web properties. The result? A shared design system that dramatically cuts down on development, decision-making, and opportunity costs—saving you hundreds of thousands of dollars.

### Benefits

- **Efficiency Multiplier**: Save up to 50% of design time by eliminating redundant pattern creation across multiple sites

- **Brand Integrity Guardian**: Prevent design drift and maintain consistent branding across your entire network

- **Operational Cost Reduction**: Reduce staff hours and simplify workflows with centralized pattern management

- **Design Governance**: Control which patterns can be used across sites with granular distribution permissions

- **Scalable Growth**: Connect unlimited sites without multisite limitations or hosting constraints.

- **Workflow Optimization**: Centralize pattern creation while maintaining individual site autonomy

### Key Features

- **Dual Architecture Support**: Works with both WordPress multisite networks and standalone WordPress installations

- **Secure API Integration**: REST API with unique authentication keys for safe cross-site pattern transfer

- **Intuitive Pattern Browser**: Full-screen admin interface with search, filtering, and live pattern previews

- **Batch Operations**: Deploy multiple patterns across multiple sites simultaneously with bulk controls

- **Pattern Status Monitoring**: Track deployment success and synchronization across all connected sites

- **Multisite Pattern Sync**: Define patterns on the governing site and apply to multiple brand sites

- **Template Sharing & Sync**: Share complete templates between sites with automatic pattern and template part synchronization

- **Intelligent Template Sync**: Templates automatically resync when updated on the governing site, ensuring brand sites stay current

- **Pattern Management**: View and manage already applied patterns

## Requirements

| Requirement   | Version                                                                                                                                                                                        |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| WordPress     | >= 6.8                                                                                                                                                                                         |
| PHP           | >= 8.0                                                                                                                                                                                         |
| Tested Up to  | >= 6.9                                                                                                                                                                                         |
| Prerequisites | <ul><li>A FSE (Full Site Editing) compatible theme across all sites. With same variables and variations.</li><li>All the blocks used in the patterns must be available on all sites.</li></ul> |

## Installation

1. Download the OneDesign plugin ZIP from Releases of GitHub Repository.
2. Upload the `OneDesign` directory to the `/wp-content/plugins/` directory
3. For multisite installations, network activate the plugin through the 'Plugins' menu in WordPress
4. For single site installations, activate the plugin through the 'Plugins' menu in WordPress

## How It Works

### Setting Up Governing and Brand Sites

1. Install and activate the OneDesign plugin on all sites in your network
2. From the OneDesign settings, designate one site as the "Governing Site" (source of patterns)
3. Designate all other sites as "Brand Sites" (where patterns will be applied)
4. Copy the API keys generated for each Brand Site from their respective settings pages
5. In the Governing Site settings, register each Brand Site by adding:
   - Site name
   - URL
   - Logo
   - API key

### Accessing the Pattern Library

1. On your Governing Site, access the Pattern Library from the sidebar menu
2. This opens a full-page interface showing all available patterns
3. Patterns are organized by categories with vertical tabs for your registered sites

### Browsing and Applying Patterns

1. **Browsing Patterns:**
   - Navigate through pattern categories in the main view
   - Use the vertical tabs to switch between different sites
   - Use the search functionality to find specific patterns
2. **Applying Patterns:**
   - Select the patterns you want to sync by clicking on them
   - Click "Apply to Sites" to open the site selection modal
   - Choose the destination sites from the modal
   - Click "Apply Patterns" to distribute the selected patterns
3. **Removing Patterns:**
   - Access the list of applied patterns by selecting the site's tab
   - Select the patterns you want to remove
   - Click "Remove Selected Patterns"
   - Confirm the removal, and the patterns will be deleted from that site

### Working with Templates

OneDesign extends beyond patterns to support full template synchronization across your network.

1. **Sharing Templates:**
   - Navigate to the Template Library from the OneDesign menu
   - Select the templates you want to share with brand sites
   - Click "Share Templates" to distribute them across your network

2. **Automatic Pattern & Template Part Creation:**
   - When you share a template, all patterns and template parts used within it are automatically created on the brand site
   - This ensures complete design consistency without manual pattern-by-pattern synchronization

3. **Template Updates & Re-sync:**
   - If you modify a template on the governing site after it's been shared, simply re-sync it
   - Go to particular brand site tab on which you need to re-sync and click "Sync Shared Templates"
   - All changes, including updated patterns and template parts, will be pushed to connected brand sites
   - Brand sites automatically receive the latest version, maintaining consistency across your network

### Video Demo

https://github.com/user-attachments/assets/d2c4b94f-3720-422d-b996-a102156bb941

## Development & Contributing

[OneDesign](https://github.com/rtCamp/onedesign) is under active development and maintained by [rtCamp](https://rtcamp.com/).

Contributions are **Welcome** and **encouraged!** To learn more about contributing to OneDesign, please read the [Contributing Guide](./docs/CONTRIBUTING.md).

For development guidelines, please refer to our [Development Guide](./docs/DEVELOPMENT.md).

## Frequently Asked Questions

### How are patterns transferred between sites?

Patterns are transferred securely via WordPress REST API, ensuring that all pattern data, including blocks and settings, are properly synchronized.

### Can I customize which patterns are available to specific sites?

Yes, you can control which patterns are applied to each brand site by managing the selections in the Pattern Library.

### Are there any limits to how many patterns I can sync?

There are no hard limits on the number of patterns you can sync, but performance may vary depending on your server resources and the complexity of the patterns.

### Can I also remove patterns from specific sites?

Yes. You are able to do that from the dashboard itself.

### Can I share complete templates instead of individual patterns?

Yes! OneDesign supports full template sharing. When you share a template, all patterns and template parts within that template are automatically created on the brand site, ensuring complete design consistency.

### What happens when I update a template on the governing site?

You can re-sync templates after making changes on the governing site. Simply select the template and use the sync function to push all updates, including modified patterns and template parts, to all connected brand sites.

### Do I need to manually sync patterns used in templates?

No. When you share a template, OneDesign automatically creates all required patterns and template parts on the brand site. You only need to share the template itself.

### Troubleshooting

1. **Patterns not showing up in the library**
   - Ensure your governing site is correctly set up
   - Check network connectivity between sites
   - Verify REST API permissions
2. **Search not working correctly**
   - The search functionality only searches pattern names
   - Ensure pattern names are descriptive and unique
3. **Pattern count incorrect**
   - This may happen if patterns are filtered incorrectly
   - Try clearing your browser cache and refreshing

## Get Involved

You can join the development and discussions on [GitHub](https://github.com/rtCamp/onedesign). Feel free to report issues, suggest features, or contribute code.
