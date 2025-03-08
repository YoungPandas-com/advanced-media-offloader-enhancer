# Advanced Media Offloader Enhancer

## Description

Advanced Media Offloader Enhancer extends the functionality of the Advanced Media Offloader WordPress plugin, addressing key limitations and adding new features to significantly improve the media offloading experience.

This enhancer plugin provides:

- **Auto-Batch Processing**: Set it and forget it - automatically process all media files without manual intervention
- **Increased File Size Limits**: Upload files up to 50MB (configurable), up from the original 10MB limit
- **Cloudflare CDN Integration**: Automatically purge CDN cache after offloading media files
- **Enhanced Error Handling**: Comprehensive error tracking, reporting, and recovery tools
- **Diagnostic Tools**: System compatibility checks and troubleshooting utilities

## Requirements

- WordPress 5.6 or higher
- PHP 8.1 or higher
- Advanced Media Offloader plugin (3.3.0 or higher)

## Installation

1. Install and activate the Advanced Media Offloader plugin
2. Upload the `advanced-media-offloader-enhancer` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the enhancer settings at Advanced Media Offloader → Enhancer Settings

## Usage

### Auto-Processing Batches

The enhancer plugin adds an "Enable Auto-Processing" checkbox to the Media Overview page. When checked:

1. Click "Offload Now" to start the process
2. The plugin will automatically process all batches without requiring further clicks
3. You'll see real-time progress updates, including estimated completion time
4. Auto-processing will continue even if you navigate away from the page or close your browser
5. You can pause or cancel auto-processing at any time

### Increased File Size Limits

The enhancer increases the default file size limit from 10MB to 50MB. You can:

1. Configure your preferred size limit in the Enhancer Settings
2. Monitor oversized files in the error dashboard
3. Retry failed uploads with a single click

### Cloudflare CDN Integration

If you use Cloudflare as your CDN:

1. Configure your Cloudflare API token and Zone ID in the Enhancer Settings
2. Enable automatic cache purging after offloading
3. Access the Cloudflare Actions page for manual purge options
4. Purge individual file caches directly from the Media Library

### Error Dashboard and Recovery

The enhanced error management system provides:

1. A dedicated Error Log page showing all offload errors
2. Filtering and search options to find specific issues
3. One-click retry for failed offloads
4. Detailed error information and troubleshooting advice
5. Export errors to CSV for further analysis

### Diagnostic Tools

Run system diagnostics to:

1. Check PHP configuration
2. Verify necessary extensions
3. Validate environment compatibility
4. Identify potential issues before they affect performance

## Settings

The Enhancer Settings page allows you to configure:

### General Settings
- **Auto-Process Batches**: Enable/disable automatic batch processing
- **Maximum File Size**: Set the maximum file size (10-200MB)
- **Delete Local Files**: Automatically delete local files after successful offload
- **Detailed Error Logging**: Enable comprehensive error tracking
- **Auto-Resume on Error**: Automatically retry failed uploads
- **Retry Attempts**: Number of retry attempts for failed offloads

### Cloudflare CDN Integration
- **Enable Cloudflare Integration**: Connect to your Cloudflare account
- **API Token**: Your Cloudflare API token
- **Zone ID**: Your Cloudflare Zone ID for this domain
- **Auto Purge Cache**: Automatically purge cache after offloading

## Frequently Asked Questions

### Does this plugin require the original Advanced Media Offloader?
Yes, this is an enhancer plugin that extends the functionality of the original Advanced Media Offloader plugin. It won't work as a standalone plugin.

### Will auto-processing continue if I close my browser?
Yes, once started, the auto-processing feature will continue on the server even if you close your browser or navigate away from the page. You can return to check progress at any time.

### What happens if an error occurs during auto-processing?
The plugin will automatically retry failed uploads based on your settings. If the maximum retry attempts are exceeded, the file will be logged in the Error Dashboard, and auto-processing will continue with the next file.

### Is there a limit to how many files can be processed?
The plugin processes files in batches of up to 50 files. With auto-processing enabled, it will automatically continue processing until all your media files have been offloaded.

### Can I use this plugin without Cloudflare?
Yes, the Cloudflare integration is optional. You can use all other features of the plugin without configuring Cloudflare.

## Support

For support, please create an issue in our [GitHub repository](https://github.com/yourusername/advanced-media-offloader-enhancer) or contact us through our [support form](https://example.com/support).

## License

This plugin is licensed under the GPL v2 or later.

```
Advanced Media Offloader Enhancer
Copyright (C) 2023 Your Name

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
```