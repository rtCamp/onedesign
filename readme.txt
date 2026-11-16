=== OneDesign ===
Contributors: rtcamp, parthnvaswani, up1512001, singhakanshu00, danish17, aviral-mittal, vaishaliagola27, rishavjeet, vishal4669, iamimmanuelraj, vishalkakadiya
Tags: OnePress, block patterns, templates, pattern sync, multisite
Requires at least: 6.9
Tested up to: 7.1
<!-- x-release-please-start-version: -->
Stable tag: 1.1.3
<!-- x-release-please-end: -->
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync patterns across multiple WordPress sites and manage them from a single dashboard.

== Description ==

OneDesign is a powerful pattern synchronization solution designed for enterprises managing multiple WordPress sites or multisite networks. It enables you to define block patterns on a Governing site and distribute them to brand sites, maintaining design consistency across your entire network.

**Why OneDesign?**

Managing multiple websites—whether for different brands, regions, or languages shouldn't mean reinventing the wheel each time. Instead of designing layouts from scratch for each site, OneDesign lets you create once and deploy anywhere, in just one click.

Built for enterprise teams, OneDesign unifies your design, editorial, and development processes across all web properties. The result? A shared design system that dramatically cuts down on development, decision-making, and opportunity costs—saving you hundreds of thousands of dollars.

**Key Benefits:**

* **50% Time Savings:** Eliminate redundant pattern creation across multiple sites
* **Brand Integrity Guardian:** Prevent design drift and maintain consistent branding
* **Cost Reduction:** Reduce operational costs through centralized pattern management
* **Design Governance:** Control which patterns can be used across sites
* **Scalable Growth:** Connect unlimited sites without multisite limitations

**Core Features:**

* **Dual Architecture Support:** Works with WordPress multisite networks and standalone installations
* **Secure API Integration:** REST API with unique authentication keys for safe pattern transfer
* **Intuitive Pattern Browser:** Full-screen admin interface with search and filtering functionality
* **Batch Operations:** Deploy multiple patterns across multiple sites simultaneously
* **Pattern Status Monitoring:** Track deployment success and synchronization across connected sites
* **Pattern Management:** View, apply, and remove patterns from the centralized dashboard

**Pattern Management Actions:**

* Browse and preview all available patterns from Governing site
* Search and filter patterns by categories and names
* Apply selected patterns to specific brand sites
* Remove patterns from specific sites
* Bulk operations for multiple pattern deployment
* Real-time pattern synchronization status tracking

**Perfect for:**

* Enterprise WordPress deployments with multiple brands or regions
* Agencies managing multiple client sites
* Organizations requiring consistent design standards
* WordPress multisite networks
* Teams using Full Site Editing (FSE) themes

== Installation ==

1. Download the latest OneDesign plugin from the GitHub releases and install it on your WordPress sites
2. Activate the plugin through the 'Plugins' screen in WordPress
   * For multisite installations, make sure to Network Activate the plugin
   * Stored API keys are encrypted with a key derived from your WordPress salts, so rotating the salts empties them. Define `ONEPRESS_ENCRYPTION_KEY` and `ONEPRESS_ENCRYPTION_SALT` in wp-config.php to keep them when you rotate salts. Define them before you add any keys, or enter the keys again after you do
3. **Choose Site Type (One-time selection):**
   * **Governing Site:** Central site that shares its patterns and templates with the brand sites it connects
   * **Brand Site:** Individual sites that connect to a governing site and use the patterns and templates it shares
4. **For Brand Sites:** Navigate to OneDesign → Settings to get the API key
5. **For Governing Site:** Go to OneDesign → Settings and add Brand Sites by entering Site Name, Site URL, Site Logo, and the API Key obtained from each Brand Site

On a multisite network, you can instead choose the governing site and add brand sites from Network Admin → Settings → OneDesign.

For detailed installation and configuration instructions, please refer to the [Setup Guide](https://github.com/rtCamp/onedesign/blob/main/docs/guides/setup.md). To learn how to share patterns and templates, see the [Usage Guide](https://github.com/rtCamp/onedesign/blob/main/docs/guides/usage.md).

== Frequently Asked Questions ==

= How are patterns transferred between sites? =

Patterns are transferred securely via WordPress REST API with unique authentication keys, ensuring that all pattern data, including blocks and settings, are properly synchronized.

= Can I customize which patterns are available to specific sites? =

Yes, you can control which patterns are applied to each brand site by managing the selections in the Pattern Library interface.

= Are there any limits to how many patterns I can sync? =

There are no hard limits on the number of patterns you can sync, but performance may vary depending on your server resources and the complexity of the patterns.

= Can I remove patterns from specific sites? =

Yes, you can remove patterns from specific sites directly through the dashboard interface by accessing the applied patterns list for each site.

= What themes are supported? =

OneDesign works with Full Site Editing (FSE) compatible themes. All sites should use the same theme with consistent variables and variations for optimal results.

= Does this work with WordPress multisite? =

Yes, OneDesign supports both WordPress multisite networks and standalone WordPress installations connected via API.

= What happens if a block used in a pattern isn't available on the target site? =

All blocks used in the patterns must be available on all target sites. Ensure consistent block availability across your network for proper pattern synchronization.

== Screenshots ==

1. OneDesign Dashboard - Centralized pattern management interface
2. Pattern Browser - Search and filter patterns with live previews
3. Site Registration - Configure Governing and Brand sites
4. Bulk Operations - Select and apply patterns to multiple sites
5. Disabled Sites - Which is already having currently selected patterns

== Upgrade Notice ==

= 2.0.0 =
This major update rebuilds the plugin and changes how patterns and templates are shared and stored. It is strongly recommended to uninstall 1.x before installing 2.0.0, or to turn on 'Delete plugin data on deactivation' after updating, then deactivate, reactivate and configure it afresh.

== Changelog ==

See [CHANGELOG.md](https://github.com/rtCamp/onedesign/blob/main/CHANGELOG.md) for the detailed changelog.

== Requirements ==

* Full Site Editing (FSE) compatible theme across all sites
* Same theme variables and variations across network
* All blocks used in patterns must be available on all sites
* REST API enabled on all sites

== Support ==

For support, feature requests, and bug reports, please visit our [GitHub repository](https://github.com/rtCamp/onedesign).

== Contributing ==

OneDesign is open source and welcomes contributions. Visit our [GitHub repository](https://github.com/rtCamp/onedesign) to contribute code, report issues, or suggest features.

Development guidelines and contributing information can be found in our repository documentation.
