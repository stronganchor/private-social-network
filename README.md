# littleWORKS of Mercy

WordPress plugin for a private, approval-based parish/community request board.

## What it does

- Creates littleWORKS member and coordinator roles.
- Stores parish/community groups, memberships, requests, responses, and audit entries in custom tables.
- Provides pending registration review by site admins and assigned group coordinators.
- Keeps private requests visible only to approved members of the same group.
- Sends notification emails without exposing private request details in email.
- Lets members control new-request and response email notifications.
- Gives coordinators a roster for the groups they manage.
- Adds admin audit-log visibility.
- Extends remember-me sessions through plugin settings, with a shorter default for coordinators/admins.
- Uses Plugin Update Checker for GitHub-hosted updates.

## Shortcodes

Create normal WordPress pages and place these shortcodes:

- `[lworks_registration]` for public access requests.
- `[lworks_login]` for member login.
- `[lworks_dashboard]` for the private request feed and posting form.
- `[lworks_coordinator]` for frontend coordinator approval.
- `[lworks_profile]` for standalone member notification settings.

Set the registration, dashboard, and coordinator pages in **littleWORKS > Settings** after creating them.

## GitHub Updates

The plugin vendors `yahnis-elsts/plugin-update-checker` and checks:

`https://github.com/stronganchor/private-social-network/`

The checker uses the `main` branch and GitHub releases. For private GitHub repositories, define a token in `wp-config.php`:

```php
define( 'LWORKS_GITHUB_TOKEN', 'github_pat_or_fine_grained_token_here' );
```

Prefer a fine-grained read-only token scoped only to this repository. Do not commit real tokens to this repo.

To release a version:

1. Update the `Version` header in `littleworks-of-mercy.php`.
2. Update `LWORKS_VERSION`.
3. Update `readme.txt` stable tag/changelog.
4. Commit and push.
5. Create a GitHub release/tag for the new version, or let the checker compare the `main` branch plugin header.

## First Setup

1. Install and activate the plugin.
2. Go to **littleWORKS > Groups** and create at least one group.
3. Create pages for registration, login, dashboard, and coordinator review.
4. Go to **littleWORKS > Settings** and select the registration, dashboard, and coordinator pages.
5. Add existing WordPress users as group coordinators from **littleWORKS > Groups**.

Group invite links are shown under **littleWORKS > Groups** after the registration page is selected.

## Privacy Notes

This plugin keeps access checks on the server side. Private request content is never included in notification emails. It is still important to run the site over HTTPS, keep WordPress/plugins/themes updated, restrict admin access, and use MFA for administrators and coordinators.
