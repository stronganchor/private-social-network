=== littleWORKS of Mercy ===
Contributors: stronganchor
Tags: private community, parish, charity, members, requests
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private parish/community request board with approved registrations, group-scoped content, coordinator moderation, and GitHub-hosted updates.

== Description ==

littleWORKS of Mercy provides a narrow private member area for approved parish or community users. Members can share prayer requests or practical needs with their approved group. Other approved members can pray, respond, or offer help.

The plugin creates custom tables for groups, memberships, requests, responses, and audit entries. It includes admin group management, pending registration review, frontend coordinator review, and a member dashboard.

Private request content is not included in notification emails.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/private-social-network`.
2. Activate the plugin.
3. Create WordPress pages for registration, login, dashboard, and coordinator review.
4. Add the plugin shortcodes to those pages.
5. Configure groups and settings under the littleWORKS admin menu.

== Shortcodes ==

`[lworks_registration]` - Public access request form.

`[lworks_member_links]` - Visitor access links or logged-in dashboard/coordinator links.

`[lworks_login]` - Login form with remember-me checked by default.

`[lworks_dashboard]` - Private member dashboard and request feed.

`[lworks_coordinator]` - Coordinator approval screen.

`[lworks_profile]` - Standalone member notification settings.

== GitHub Updates ==

This plugin uses Plugin Update Checker and checks `https://github.com/stronganchor/private-social-network/`.

For a private GitHub repository, define `LWORKS_GITHUB_TOKEN` in `wp-config.php` or provide a token through the `lworks_github_token` filter.

== Changelog ==

= 0.3.1 =
* Added `[lworks_member_links]` for session-aware public/member navigation.
* Added dashboard links to logged-in registration and login shortcode messages.

= 0.3.0 =
* Added registration honeypot and signed form-age anti-spam checks.
* Added optional hCaptcha and Google reCAPTCHA v2 settings.
* Added `lworks_registration_antispam_fields` and `lworks_registration_antispam_result` hooks for custom anti-spam integrations.

= 0.2.0 =
* Added registration-page invite links and invite-code prefill.
* Added member notification preferences.
* Added coordinator group roster.
* Added admin audit log screen.

= 0.1.0 =
* Initial implementation.
