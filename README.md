![Banner](./.wordpress-org/banner.webp)

# OneDesign [![Project Status: Active – The project has reached a stable, usable state and is being actively developed.](https://www.repostatus.org/badges/latest/active.svg)](https://www.repostatus.org/#active)

> **OneDesign** is a [OnePress](https://rtcamp.com/onepress/) ecosystem plugin that syncs **block patterns and templates** across WordPress sites. You build them on one governing site and apply them to your brand sites from there.

---

## 💡 Why OneDesign?

When each brand, region or language has its own site, the same layouts tend to get rebuilt on every one of them, and they drift apart over time. **OneDesign** lets you build a pattern or template once and send it to the sites that need it.

### Benefits

- **Efficiency Multiplier:** Save up to 50% of design time by eliminating redundant pattern creation across multiple sites.
- **Brand Integrity Guardian:** Prevent design drift and maintain consistent branding across your entire network.
- **Operational Cost Reduction:** Reduce staff hours and simplify workflows with centralized pattern management.
- **Design Governance:** Control which patterns can be used across sites with granular distribution permissions.
- **Scalable Growth:** Connect unlimited sites without multisite limitations or hosting constraints.
- **Workflow Optimization:** Centralize pattern creation while maintaining individual site autonomy.

---

## Getting Started

See the [Setup Guide](docs/guides/setup.md) for detailed installation and configuration instructions, and the [Usage Guide](docs/guides/usage.md) for sharing patterns and templates with your brand sites.

### Video Demo

https://github.com/user-attachments/assets/d2c4b94f-3720-422d-b996-a102156bb941

## Frequently Asked Questions

#### How are patterns transferred between sites?

Patterns are transferred securely via WordPress REST API, ensuring that all pattern data, including blocks and settings, are properly synchronized.

#### Can I customize which patterns are available to specific sites?

Yes, you can control which patterns are applied to each brand site by managing the selections in the Pattern Library.

#### Are there any limits to how many patterns I can sync?

There are no hard limits on the number of patterns you can sync, but performance may vary depending on your server resources and the complexity of the patterns.

#### Can I also remove patterns from specific sites?

Yes. Open the brand site's tab in the Pattern Library, select the patterns and click 'Remove Patterns'.

#### Can I share complete templates instead of individual patterns?

Yes! OneDesign supports full template sharing. When you share a template, all patterns and template parts within that template are automatically created on the brand site, ensuring complete design consistency.

#### What happens when I update a template on the governing site?

You can re-sync templates after making changes on the governing site. Open the brand site's tab in the Template Library and click 'Sync Shared Templates' to push the latest version of every template shared with that site, including its patterns and template parts.

#### Do I need to manually sync patterns used in templates?

No. When you share a template, OneDesign automatically creates all required patterns and template parts on the brand site. You only need to share the template itself.

#### Troubleshooting

1. **Patterns not showing up in the library**
   - Ensure your governing site is correctly set up
   - Check network connectivity between sites
   - Verify REST API permissions
2. **Search not working correctly**
   - The search only matches pattern titles and descriptions
   - Ensure pattern titles are descriptive and unique
3. **Pattern count incorrect**
   - This may happen if patterns are filtered incorrectly
   - Try clearing your browser cache and refreshing

## Development & Contributing

OneDesign is under active development and maintained by [rtCamp](https://rtcamp.com).

Contributions are welcome and encouraged!

You can join the development and discussions on [GitHub](https://github.com/rtCamp/onedesign). Feel free to report issues, suggest features, or contribute code.

To learn more about contributing to OneDesign, please read the [Contributing Guide](docs/CONTRIBUTING.md).

For development guidelines, please refer to our [Development Guide](docs/DEVELOPMENT.md).
