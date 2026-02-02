# AGoodBug

Visual feedback and bug reporting widget for WordPress with screenshot capture.

## Features

- 🐛 Floating feedback button for logged-in users
- 📸 Screenshot capture with area selection
- ✏️ Visual marking of problem areas
- 📧 Multiple destinations: Email, AGoodApp, Checkvist, GitHub Issues
- 💾 Saves all reports as Custom Post Type in WordPress
- 🔒 Role-based access control
- 🛡️ Rate limiting protection

## Installation

1. Download or clone this repository to `/wp-content/plugins/agoodbug/`
2. Activate the plugin in WordPress admin
3. Go to **Settings → AGoodBug** to configure

## Configuration

### General Settings

- **Enable Widget**: Show/hide the feedback button on the frontend
- **Allowed Roles**: Select which user roles can see the feedback button

### Destinations

Choose where to send bug reports:

- ✅ **WordPress (Bug Reports)**: Always enabled - saves to CPT
- 📧 **Email**: Send to configured recipients
- 🚀 **AGoodApp**: Create issues in AGoodApp
- ✓ **Checkvist**: Create tasks in Checkvist
- 🐙 **GitHub**: Create issues in a GitHub repository

### Integration Setup

#### Email
Enter comma-separated email addresses to receive reports.

#### AGoodApp
1. Get your API URL and JWT token from AGoodApp
2. Enter your Organization ID
3. Enable the integration

#### Checkvist
1. Get your API key from [Checkvist Settings](https://checkvist.com/auth/profile)
2. Enter the List ID where tasks should be created
3. Enable the integration

#### GitHub
1. Create a [Personal Access Token](https://github.com/settings/tokens) with `repo` scope
2. Enter the repository in `owner/repo` format
3. Enable the integration

## Usage

1. Log in as a user with an allowed role (default: Administrator, Editor)
2. Visit any page on your site
3. Click the 🐛 button in the bottom-right corner
4. Draw a rectangle around the problem area
5. Describe the issue in the modal
6. Click "Send Report"

## Screenshots

Reports are stored with:
- Screenshot image (as WordPress attachment)
- Page URL
- Viewport size
- Browser information
- User who submitted
- Destination delivery status

## Hooks & Filters

### Modify allowed user roles
```php
add_filter('agoodbug_allowed_roles', function($roles) {
    $roles[] = 'author';
    return $roles;
});
```

### Custom notification after submission
```php
add_action('agoodbug_feedback_submitted', function($post_id, $data) {
    // Your custom logic
}, 10, 2);
```

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Users must be logged in to report bugs

## Credits

- [html2canvas](https://html2canvas.hertzen.com/) - Screenshot capture
- Built by [AGoodId](https://agoodid.se)

## License

GPL-2.0+
