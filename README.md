# Gravity Forms Drip Add-On

A WordPress plugin that integrates Gravity Forms with the Drip email marketing platform, allowing you to automatically add form submissions as subscribers to your Drip account.

## Features

- **Easy Integration**: Seamlessly connect Gravity Forms with Drip using the official Drip API
- **Field Mapping**: Map form fields to Drip subscriber fields including standard and custom fields
- **Multiple Feeds**: Support for multiple feeds per form with different settings
- **Conditional Logic**: Send subscribers to Drip only when specific conditions are met
- **Tag Support**: Automatically apply tags to subscribers in Drip
- **Double Opt-In**: Optional double opt-in support for compliance
- **Error Handling**: Comprehensive error logging and graceful failure handling
- **Security**: Follows WordPress and Gravity Forms security best practices

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Gravity Forms 2.5 or higher
- Drip account with API access

## Installation

1. Download or clone this repository
2. Upload the `gravity-forms-drip` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Forms → Settings → Drip to configure your API credentials

## Configuration

### Getting Your Drip API Credentials

1. **API Token**: 
   - Log in to your Drip account
   - Go to Settings → User Settings → API Token
   - Copy your API token

2. **Account ID**:
   - Your Account ID can be found in your Drip account URL
   - Example: `https://www.getdrip.com/{account_id}/`
   - Copy the account ID from the URL

### Plugin Settings

1. Navigate to **Forms → Settings → Drip**
2. Enter your **API Token** and **Account ID**
3. Click **Test Connection** to verify your credentials
4. Save settings

### Creating a Feed

1. Go to **Forms → [Your Form] → Settings → Drip**
2. Click **Add New** to create a new feed
3. Configure the feed:
   - **Feed Name**: Give your feed a descriptive name
   - **Email Address**: Map to a form field containing an email address (required)
   - **Standard Drip Fields**: Map form fields to standard Drip fields (first name, last name, phone, address, etc.)
   - **Custom Fields**: Map form fields to Drip custom fields
   - **Tags**: Enter comma-separated tags to apply to subscribers
   - **Double Opt-In**: Enable if you want subscribers to confirm via email
   - **Conditional Logic**: Set conditions for when to send to Drip
4. Save the feed

## Field Mapping

### Required Fields
- **Email**: Must be mapped to a Gravity Forms email field

### Standard Drip Fields
- First Name
- Last Name
- Phone
- Address
- City
- State
- ZIP Code
- Country

### Custom Fields
You can map any Gravity Forms field to Drip custom fields. Simply specify the custom field name in Drip and select the corresponding form field.

## API Documentation

This plugin uses the Drip API v2. For more information, visit:
- [Drip API Documentation](https://developer.drip.com/)
- [Subscribers Endpoint](https://developer.drip.com/#subscribers)

## Security

- All inputs are sanitized
- All outputs are escaped
- WordPress nonces are used for form submissions
- API credentials are stored securely in WordPress options
- Follows WordPress and Gravity Forms coding standards

## Error Handling

- API errors are logged using Gravity Forms logging system
- Form submissions continue even if Drip API is unavailable
- Admin notices are displayed for connection failures
- Detailed error messages help with troubleshooting

## Support

For issues, questions, or contributions, please open an issue on GitHub.

## License

GPL-2.0+

## Changelog

### 1.0.0
- Initial release
- Basic integration with Drip API
- Field mapping support
- Conditional logic support
- Tag support
- Double opt-in support

