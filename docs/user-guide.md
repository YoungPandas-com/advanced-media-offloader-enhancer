# Advanced Media Offloader Enhancer: User Guide

This comprehensive guide explains how to get the most out of the Advanced Media Offloader Enhancer plugin. Follow these instructions to optimize your WordPress media offloading experience.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Auto-Batch Processing](#auto-batch-processing)
3. [Large File Handling](#large-file-handling)
4. [Cloudflare CDN Integration](#cloudflare-cdn-integration)
5. [Error Management](#error-management)
6. [Diagnostics & Troubleshooting](#diagnostics--troubleshooting)
7. [Best Practices](#best-practices)

## Getting Started

### Installation

1. Ensure you have the Advanced Media Offloader plugin installed and activated
2. Upload and activate the Advanced Media Offloader Enhancer plugin
3. Navigate to "Advanced Media Offloader → Enhancer Settings" to configure the plugin

### Initial Configuration

For optimal performance, we recommend:

1. Setting the maximum file size based on your server capabilities (50MB is a good starting point)
2. Enabling auto-resume on error with 3 retry attempts
3. Enabling detailed error logging for better troubleshooting
4. Setting up Cloudflare integration if you use Cloudflare as your CDN

## Auto-Batch Processing

The auto-batch processing feature allows you to offload all your media files in a single operation, without needing to click after each batch.

### Starting Auto-Processing

1. Go to "Advanced Media Offloader → Media Overview"
2. Check the "Enable Auto-Processing" checkbox
3. Click the "Offload Now" button
4. The system will begin processing the first batch of files
5. When the first batch completes, the system automatically starts the next batch
6. This continues until all media files are processed

### Progress Monitoring

During auto-processing, you'll see:

- Current batch and total batch count
- Number of files processed and total files
- Percentage complete progress bar
- Estimated time remaining
- Error count (if any)

### Control Options

While auto-processing is active, you can:

- **Pause**: Click the "Pause Auto-Processing" button to temporarily pause. The process can be resumed later.
- **Cancel**: Click the "Cancel Auto-Processing" button to stop completely. This cannot be resumed.
- **Resume**: If paused, click "Resume Auto-Processing" to continue from where you left off.

### Auto-Resume Functionality

If you navigate away from the page or close your browser, auto-processing continues on the server. When you return to the Media Overview page, you'll see the current progress and can control the process as needed.

## Large File Handling

The plugin increases the default file size limit from 10MB to a configurable limit (up to 200MB).

### Configuring Size Limits

1. Go to "Advanced Media Offloader → Enhancer Settings"
2. Under "General Settings", find "Maximum File Size (MB)"
3. Enter your desired limit (between 10 and 200MB)
4. Click "Save Changes"

### Handling Oversized Files

If a file exceeds your configured limit:

1. It will be logged in the Error Dashboard
2. The error message will indicate the file is oversized
3. You can either:
   - Increase the maximum file size in settings
   - Manually compress/resize the file
   - Use an alternative method to offload very large files

## Cloudflare CDN Integration

This feature automatically purges your Cloudflare CDN cache after offloading media files.

### Setting Up Cloudflare Integration

1. Log in to your Cloudflare dashboard
2. Go to "My Profile → API Tokens"
3. Create a new API token with "Cache Purge" permissions for your domain
4. Note your Zone ID from the Cloudflare dashboard overview page
5. In WordPress, go to "Advanced Media Offloader → Enhancer Settings"
6. Under "Cloudflare CDN Integration", enter your API Token and Zone ID
7. Enable "Auto Purge Cache" if you want automatic cache purging
8. Click "Save Changes"

### Managing CDN Cache

Once configured, you can:

- **Auto-Purge**: When enabled, the plugin automatically purges the cache for each offloaded file
- **Manual Purge**: Go to "Advanced Media Offloader → Cloudflare Actions" to manually purge all cache or just media files
- **Selective Purge**: In the Media Library, each offloaded file has a "Purge CDN Cache" option in its row actions

## Error Management

The enhanced error management system provides detailed tracking, reporting, and recovery tools.

### Viewing Errors

1. Go to "Advanced Media Offloader → Error Log"
2. See a list of all errors with:
   - Timestamp
   - Error code
   - Error message
   - Affected attachment
   - Available actions

### Error Management Options

From the error dashboard, you can:

- **Filter Errors**: Filter by error code or search for specific errors
- **View Details**: Click "Details" to see full error information, including context data
- **Retry Offload**: Click "Retry Offload" to attempt offloading a failed file again
- **Export Errors**: Export all errors to a CSV file for documentation or analysis
- **Clear Errors**: Clear the error log when issues are resolved

### Automatic Error Recovery

If you've enabled "Auto-Resume on Error" in settings:

1. When an error occurs during offloading, the system automatically retries
2. It will attempt the configured number of retry attempts
3. If still unsuccessful, it logs the error and continues with the next file
4. Auto-processing continues without interruption

## Diagnostics & Troubleshooting

The plugin includes comprehensive diagnostic tools to help identify and resolve issues.

### Running Diagnostics

1. Go to "Advanced Media Offloader → Enhancer Settings"
2. Scroll to the bottom of the page
3. Click "Run Diagnostics"
4. Review the results for any warnings or failures

### Common Issues and Solutions

- **PHP Memory Limit**: If diagnostics show a low memory limit, increase it in your PHP configuration
- **Max Execution Time**: For large files, increase the max execution time in PHP settings
- **Missing cURL Extension**: Ensure the cURL PHP extension is installed and enabled
- **Permission Issues**: Check file and directory permissions in your uploads folder
- **Cloud Provider Configuration**: Verify your cloud provider credentials and settings

## Best Practices

For optimal results with Advanced Media Offloader Enhancer:

### Server Configuration

- Set PHP memory_limit to at least 256M
- Set max_execution_time to at least 300 seconds
- Ensure your server allows uploads of at least the size you've configured
- Configure PHP to not die on timeout for background processes

### Offloading Strategy

- Start with a small batch of files to test your configuration
- For very large libraries (10,000+ files), offload in stages during low-traffic periods
- Consider excluding very large files (100MB+) from bulk processing
- Regularly check the Error Log to address any issues

### Cloudflare Optimization

- Create a dedicated API token with only the permissions needed
- Schedule cache purging during off-peak hours
- Use selective purging rather than purging all when possible
- Set up Page Rules in Cloudflare to optimize caching for media files

### Monitoring and Maintenance

- Periodically run diagnostics to ensure optimal performance
- Clear the error log after resolving issues
- Keep both the original plugin and enhancer updated to the latest version
- Create regular database backups before performing bulk operations

By following these guidelines, you'll get the most out of the Advanced Media Offloader Enhancer and optimize your WordPress media management workflow.