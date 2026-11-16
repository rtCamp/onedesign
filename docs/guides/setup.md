# OneDesign - Setup Guide

This guide will walk you through the steps to set up OneDesign for your WordPress site network, including plugin installation and connecting your brand sites to the governing site. Once your sites are connected, see the [Usage Guide](usage.md) to start sharing patterns and templates.

## Prerequisites

- WordPress 6.9 or later, and PHP 8.2 or later.
- Multiple WordPress sites, either as a WordPress multisite network or multiple standalone installations.
- Administrator access to the network and individual sites.
- The same block theme on every site, with the same style variables and variations.
- Every block your patterns and templates use available on the sites you apply them to.

## Installation

1. Download the latest OneDesign plugin from the [GitHub releases](https://github.com/rtCamp/onedesign/releases) and install it on the governing site and brand sites.
2. Activate the plugin.
   For **multisite installations**, make sure to `Network Activate` the plugin.

Stored API keys are encrypted with a key derived from your WordPress salts, so rotating the salts empties them. To keep them when you rotate salts, define `ONEPRESS_ENCRYPTION_KEY` and `ONEPRESS_ENCRYPTION_SALT` in `wp-config.php`. Define them before you add any keys, or enter the keys again after you do.

## Setting Up OneDesign

### Plugin Setup

1. From the `OneDesign > Settings` page, set the Site Type to either 'Governing Site' or 'Brand Site'. This is a one-time selection.
   - **Governing Site:** The central site that shares its patterns and templates with the brand sites it connects.
   - **Brand Site:** The individual sites that connect to a governing site and use the patterns and templates it shares.
2. On the brand site, navigate to `OneDesign > Settings` to get the API key.
3. On the governing site, navigate to `OneDesign > Settings`, and add the brand site by entering the Site Name, Site URL, Site Logo and the API Key obtained from the brand site.

### Multisite Setup

On a multisite network, you can set up every site from `Network Admin > Settings > OneDesign` instead:

1. Under 'Add a Governing Site', choose the site and click 'Make Governing Site'.
2. Click 'Add Brand Sites', choose the governing site, then choose the sites on the network to connect to it.

A governing site on the network can also add sites from the same network with 'Add Brand Sites From This Network' on its `OneDesign > Settings` page.

## Next Steps

See the [Usage Guide](usage.md) to start sharing patterns and templates with your brand sites.
