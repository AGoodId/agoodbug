# AGoodBug

Visual feedback and bug reporting widget for WordPress with screenshot capture.

## Features

- Floating feedback button with three display styles
- Screenshot capture with area selection and visual marking
- Falls back gracefully to text-only feedback when screenshot fails
- Multiple destinations: WordPress CPT, Email, Slack, Checkvist, AGoodApp, Asana
- Role-based access control
- Rate limiting
- Multisite / network activation support
- Auto-updates from GitHub releases

## Installation

1. Download the latest ZIP from [Releases](https://github.com/AGoodId/agoodbug/releases)
2. Upload via **Plugins → Add New → Upload Plugin**
3. Activate the plugin (or network-activate on multisite)
4. Configure under **Settings → AGoodBug** (or **Network Admin → AGoodBug** on multisite)

## Configuration

### General

| Setting | Description |
|---|---|
| Enable Widget | Show/hide the feedback button |
| Show in wp-admin | Also show on WordPress admin pages |
| Button Style | Sticky button, tab at bottom-right, or tab on right edge |
| Tab Label | Text on the tab (default: "Tyck till") |
| Allow Anonymous | Let non-logged-in visitors submit feedback |
| Allowed Roles | Which roles can see the button |
| Rate Limit | Max reports per user per hour (0 = unlimited) |

### Destinations

Reports can be sent to one or more destinations simultaneously:

- **WordPress** — always saved as a Custom Post Type (`agoodbug_report`)
- **Email** — sent to configured recipients (falls back to site admin email)
- **Slack** — posted via Incoming Webhook with screenshot preview
- **Checkvist** — creates a task in a checklist
- **AGoodApp** — sends to an AGoodApp project
- **Asana** — creates a Ybug-like task with metadata and screenshot attachment

### Integration Setup

#### Email
Enter one email address per line in **Email Recipients**. Leave empty to use each site's admin email.

#### Slack
1. Go to [api.slack.com/apps](https://api.slack.com/apps) and create a new app
2. Enable **Incoming Webhooks** and add a webhook to your workspace
3. Paste the webhook URL (`https://hooks.slack.com/services/...`) into settings
4. Make sure **Slack** is checked under Destinations

#### Checkvist
1. Get your API key from [checkvist.com/auth/profile](https://checkvist.com/auth/profile)
2. Enter your username, API key and List ID
3. Enable Checkvist and add it to Destinations

#### AGoodApp
1. Enter your API token and Project ID
2. Enable AGoodApp and add it to Destinations

#### Asana
1. Create an Asana Personal Access Token
2. Enter Workspace GID, Project GID, and optionally Section GID and Assignee GID
3. Set Task Prefix, for example `SWE3`, to create titles like `[SWE3] #123 Feedback summary`
4. Enable Asana and add it to Destinations

## Multisite

AGoodBug supports WordPress multisite. Network-activate the plugin to enable it across all sites.

**Network Admin → AGoodBug** lets you configure defaults for the entire network (destinations, email, Slack, Checkvist, AGoodApp, Asana, roles, rate limit etc.). Individual sites can override any setting via their own **Settings → AGoodBug** page.

When a new site is added to the network it automatically inherits the network defaults.

## Usage

1. Log in as a user with an allowed role (default: Administrator, Editor)
2. Visit any page on your site
3. Click the feedback button (bottom-right corner)
4. Draw a rectangle around the problem area
5. Add a description and click **Send**

If screenshot capture fails (e.g. on sites using modern CSS color spaces), the modal falls back to a text-only feedback form with a notice.

## Report Data

Each report stores:

- Screenshot (as WordPress attachment)
- Page URL
- Viewport size and device info (browser, device type, screen resolution, pixel ratio)
- Color scheme and language
- User who submitted (or email for anonymous reports)
- Delivery status per destination

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Credits

- [html2canvas](https://html2canvas.hertzen.com/) — screenshot capture
- [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) — auto-updates
- Built by [AGoodId](https://agoodid.se)

## License

GPL-2.0+
