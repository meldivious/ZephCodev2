# Zephora Logistic Systems

A comprehensive WordPress plugin for logistics services, providing secure frontend logistics platform with KYC-gated SHIP FOR ME & BUY FOR ME workflows.

## Features

- **Ship For Me**: Send packages from US warehouses to Nigeria
- **Buy For Me**: Purchase items in the US and ship to Nigeria
- **KYC Verification**: Secure customer verification system
- **Payment Integration**: Paystack and Flutterwave support
- **Admin Dashboard**: Complete management interface
- **PDF Invoicing**: Professional invoice generation
- **WhatsApp Notifications**: Automated customer notifications
- **Audit Logging**: Complete activity tracking
- **GDPR Compliance**: Data protection features

## Requirements

- WordPress 6.4+
- PHP 7.4+
- MySQL 5.6+
- Composer (for dependency management)

## Installation

### Method 1: Manual Installation

1. Download the plugin zip file
2. Extract to `wp-content/plugins/zephora-logistics/`
3. Install dependencies: `composer install`
4. Activate the plugin through WordPress admin
5. Configure settings in **Zephora Logistics > Settings**

### Method 2: Composer Installation

```bash
composer require zephora/logistics
```

## Activation & Setup

When the plugin is activated, it will automatically:

1. **Create necessary directories**:
   - `wp-content/uploads/zls-invoices/` - PDF invoices
   - `wp-content/uploads/zls-kyc-documents/` - KYC uploads
   - `wp-content/zls-logs/` - Error and audit logs

2. **Create essential pages**:
   - **My Dashboard** (`/my-dashboard/`) - User dashboard with [zls_dashboard] shortcode
   - **KYC Verification** (`/kyc-verification/`) - KYC verification page
   - **Ship For Me** (`/ship-for-me/`) - Shipping request form with [zls_ship_for_me] shortcode
   - **Buy For Me** (`/buy-for-me/`) - Purchase request form with [zls_buy_for_me] shortcode
   - **Login** (`/login/`) - Custom login page (redirects to WordPress login)

3. **Set default configuration options**
4. **Create a default US warehouse address**
5. **Register custom post types**
6. **Log the activation event**

### Important Notes:
- Pages are created only if they don't already exist
- Existing pages with the same slugs will not be overwritten
- You can customize page content and settings after activation
- All pages are created as published with comments disabled

## Configuration

### Basic Setup

1. Go to **WordPress Admin > Zephora Logistics > Settings**
2. Configure general settings:
   - Enable/disable payment gateways
   - Enable WhatsApp notifications
   - Set default tax rates

### Payment Gateways

#### Paystack
1. Get API keys from [Paystack Dashboard](https://dashboard.paystack.com/)
2. Enter Public and Secret keys in Settings > API Keys
3. Set webhook URL: `https://yoursite.com/wp-admin/admin-ajax.php?action=zls_webhook_paystack`

#### Flutterwave
1. Get API keys from [Flutterwave Dashboard](https://dashboard.flutterwave.com/)
2. Enter Public and Secret keys in Settings > API Keys
3. Set webhook URL: `https://yoursite.com/wp-admin/admin-ajax.php?action=zls_webhook_flutterwave`

### WhatsApp Integration

1. Sign up for [Termii](https://termii.com/)
2. Get API key and sender ID
3. Enter credentials in Settings > API Keys
4. Enable WhatsApp notifications in General settings

### Exchange Rate Configuration

The plugin automatically fetches current USD to NGN exchange rates using multiple APIs:

**Automatic Rate Updates:**
- Rates are cached for 1 hour to minimize API calls
- Daily scheduled updates ensure fresh rates
- Falls back to hardcoded rate (1500) if all APIs fail

**API Options (in priority order):**
1. Open Exchange Rates (requires API key)
2. exchangerate-api.com (requires API key)
3. Free tier API (no key required)

**Manual Rate Setting:**
1. Go to **Settings > General**
2. Enter a manual USD to NGN rate
3. This rate will be used instead of fetching from APIs
4. Leave blank to use automatic rate fetching

### US Warehouse Addresses

1. Go to **Settings > US Addresses**
2. Add multiple warehouse locations
3. Mark addresses as active/inactive
4. Include contact information for each location

## Usage

### For Customers

#### Registration & KYC
1. User registers on your site
2. Completes KYC verification form
3. Admin reviews and approves KYC
4. User gains access to logistics services

#### Ship For Me
1. User logs into dashboard
2. Fills out shipping form with package details
3. Selects US warehouse address
4. Provides recipient information
5. Submits and pays for service

#### Buy For Me
1. User provides product details and budget
2. System calculates estimated costs
3. User approves quote and pays
4. Zephora team purchases and ships item

### For Administrators

#### Managing Requests
- View all ship/buy requests in admin dashboard
- Update request statuses
- Generate and send quotes
- Process payments
- Track shipments

#### KYC Management
- Review pending KYC applications
- Approve or deny applications
- View submitted documents
- Manage user access

#### Settings Management
- Configure payment gateways
- Manage warehouse addresses
- Set pricing and tax rates
- Configure notifications

## Shortcodes

The plugin provides the following shortcodes for displaying forms and content:

- `[zls_dashboard]` - User dashboard (shows on My Dashboard page)
- `[zls_ship_for_me]` - Ship For Me request form (shows on Ship For Me page)
- `[zls_buy_for_me]` - Buy For Me request form (shows on Buy For Me page)

### Usage Examples:

```php
// Add to any page or post
[zls_dashboard]
[zls_ship_for_me]
[zls_buy_for_me]
```

## Admin Features

### Bulk Operations

Admins can perform bulk actions on multiple requests from the Ship/Buy requests list:

- **Approve & Send Quote** - Send quotes to multiple customers
- **Mark as Paid** - Bulk update status to paid
- **Mark as Shipped** - Bulk update status to shipped
- **Mark as Delivered** - Bulk update status to delivered
- **Send Notification** - Send status notifications to multiple customers

**How to Use:**
1. Go to **Zephora Logistics > Ship Requests** or **Buy Requests**
2. Select multiple requests using checkboxes
3. Choose action from the "Bulk Actions" dropdown
4. Click "Apply"

### Export & Analytics

Generate comprehensive reports and export data:

**Export Options:**
- **Export Requests** - Download all requests as CSV or Excel
- **Export Analytics** - Generate detailed reports as CSV or PDF

**Report Types:**
- Summary report with totals and statistics
- Revenue breakdown by status
- Revenue breakdown by request type
- Top customers analysis
- Daily breakdown analytics

**Filter Options:**
- Date range (from/to)
- Request status
- Request type (Ship For Me/Buy For Me)

**How to Export:**
1. Go to **Zephora Logistics > Dashboard**
2. Use the export tools in the admin panel
3. Select filters and format (CSV/PDF)
4. Download generated file

## API Endpoints

### AJAX Endpoints
- `wp_ajax_download_zls_invoice` - Download PDF invoices
- `wp_ajax_fetch_shipment_details` - Get shipment information
- `wp_ajax_zls_delete_request` - Delete pending requests
- `wp_ajax_load_recent_activity` - Load paginated activity
- `wp_ajax_confirm_payment` - Confirm manual payments
- `wp_ajax_zls_update_rates` - Manually update exchange rates (admin only)
- `wp_ajax_zls_export_requests` - Export requests to CSV/Excel (admin only)
- `wp_ajax_zls_export_report` - Export analytics reports (admin only)

### Webhooks
- `wp_ajax_nopriv_zls_webhook_paystack` - Paystack payment webhooks
- `wp_ajax_nopriv_zls_webhook_flutterwave` - Flutterwave payment webhooks

## File Structure

```
zephora-logistics/
├── zephora-logistics.php          # Main plugin file
├── composer.json                  # Dependencies
├── README.md                      # This file
├── assets/
│   ├── css/
│   │   ├── admin.css             # Admin styles
│   │   ├── frontend.css          # Frontend styles
│   │   └── kyc-frontend.css      # KYC form styles
│   └── js/
│       ├── admin.js              # Admin scripts
│       ├── frontend.js           # Frontend scripts
│       └── settings.js           # Settings scripts
├── includes/
│   ├── class-core.php            # Core functionality
│   ├── class-dashboard.php       # User dashboard
│   ├── class-ship-for-me.php     # Ship For Me handler
│   ├── class-buy-for-me.php      # Buy For Me handler
│   ├── class-kyc-manager.php     # KYC management
│   ├── class-payments.php        # Payment processing
│   ├── class-pdf.php             # PDF generation
│   ├── class-notifications.php   # Notifications
│   ├── class-currency-converter.php # Exchange rate conversion
│   ├── class-bulk-operations.php # Bulk admin actions
│   ├── class-export-manager.php  # Reports and exports
│   ├── class-audit-logger.php    # Audit logging
│   ├── class-admin-ui.php        # Admin interface
│   ├── class-settings.php        # Settings management
│   ├── class-security.php        # Security features
│   ├── class-gdpr.php            # GDPR compliance
│   └── class-post-types.php      # Custom post types
├── templates/
│   └── pdf-invoice.php           # Invoice template
└── languages/
    └── zls-en_US.mo              # Translation files
```

## Dependencies

This plugin requires the following PHP packages (managed via Composer):

- `dompdf/dompdf`: PDF generation
- `psr/log`: Logging interface (optional)

Install with:
```bash
composer install
```

## Error Handling & Logging

The plugin includes comprehensive error handling and logging:

- **Error Handler**: Catches PHP errors, exceptions, and fatal errors
- **Application Logging**: Logs application-specific errors and events
- **Audit Logging**: Tracks all user actions and system events
- **Log Files**: Located in `wp-content/zls-logs/`
- **Admin Access**: Error logs can be viewed in admin for debugging

## Security

- All forms include nonce verification
- Input sanitization and validation
- User capability checks
- Secure file uploads for KYC documents
- Audit logging for all actions
- GDPR compliance features
- Comprehensive error handling prevents information leakage

## Developer Guide

### Currency Conversion Functions

```php
// Get current USD to NGN rate
$rate = ZLS_Currency_Converter::get_usd_to_ngn();

// Convert USD to NGN
$ngn_amount = ZLS_Currency_Converter::convert_usd_to_ngn(100); // Returns ₦150,000

// Convert NGN to USD
$usd_amount = ZLS_Currency_Converter::convert_ngn_to_usd(150000); // Returns 100

// Get rate history
$history = ZLS_Currency_Converter::get_rate_history(30); // Last 30 rates
```

### Bulk Operations Functions

```php
// Get bulk actions summary for multiple requests
$post_ids = array(123, 124, 125);
$summary = ZLS_Bulk_Operations::get_bulk_actions_summary($post_ids);
// Returns: total_requests, total_value_usd, total_value_ngn, status_counts, type_counts
```

### Export Manager Functions

```php
// Generate analytics report programmatically
$report = ZLS_Export_Manager::generate_report('summary', 30); // last 30 days
// Returns comprehensive analytics data

// Prepare data for export
$posts = get_posts(array('post_type' => array('zls_ship', 'zls_buy'), 'numberposts' => -1));
$export_data = self::prepare_export_data($posts);
```

## Changelog

### Version 1.0.17
- Initial release
- Ship For Me and Buy For Me workflows
- KYC verification system
- Payment gateway integration
- PDF invoice generation
- WhatsApp notifications
- Admin management interface

### Version 1.0.18 (Latest)
**New Features:**
- **Automated Currency Conversion** - Real-time USD to NGN exchange rates with multiple API providers
- **Bulk Operations** - Approve, update, and notify multiple requests at once
- **Export & Analytics** - Generate detailed reports and export data to CSV/PDF
- **Enhanced Error Handling** - Comprehensive logging and debugging system
- **Automatic Page Creation** - Essential pages auto-created on activation

**Improvements:**
- Proper activation/deactivation hooks
- Directory auto-creation on activation
- Default configuration setup
- Enhanced security and error handling

## Support

For support, please contact:
- Email: support@zephora.logistics
- Website: https://zephora.logistics

## License

GPL-3.0-only - See LICENSE file for details

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Credits

Developed by Zephora Engineering