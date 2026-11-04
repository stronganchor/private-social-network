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
- Adds built-in registration anti-spam checks plus optional hCaptcha/reCAPTCHA settings.
- Lets coordinators create and email secure invite links for automatic group approval.
- Extends remember-me sessions through plugin settings, with a shorter default for coordinators/admins.
- Uses Plugin Update Checker for GitHub-hosted updates.

## Shortcodes

Create normal WordPress pages and place these shortcodes:

- `[lworks_registration]` for public access requests.
- `[lworks_member_links]` for session-aware request/sign-in links or logged-in dashboard links.
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

## Registration Anti-Spam

The registration form includes a hidden honeypot field and signed form-age checks. Admins can also enable hCaptcha or Google reCAPTCHA v2 from **littleWORKS > Settings** after adding the provider keys.

Public signup attempts, including invite registrations, are limited to **5 per socket IP per hour, 20 per IP per day, 30 site-wide per hour, and 100 site-wide per day**. These are fixed UTC hour/day windows. Attempts are charged before CAPTCHA or password/account work and are not refunded when validation or account creation fails. Shared networks therefore share an allowance; forwarded IP headers are deliberately ignored. Input is limited to 32 KiB with smaller field limits, including 256-byte passwords and 2,000-byte connection notes.

One non-autoloaded database option stores a bounded map of salted peer hashes; expired peer entries are pruned on the next signup attempt. A site-scoped MySQL named lock serializes reservations across workers; lock, read, write, readback, or malformed-state failures deny signup rather than reset its allowance. MySQL/MariaDB named-lock support is required. No pending accounts are automatically deleted.

Each accepted public registration notifies at most **five staff recipients** (at most 150 recipient deliveries/hour or 500/day under the signup budgets). Groups with up to five coordinators keep their normal recipients. Larger rosters notify the configured notification contact first, then coordinators in email-address order up to the limit; all coordinators can still review every pending account in the approval screen. Secure-invite automatic approval sends its usual single account-approval notice instead. Existing manual approval and other member mail are unchanged.

Offline regressions: `php tests/registration-admission-test.php` and `php tests/disabled-group-access-test.php`. Deployment verification should confirm named-lock support and that legitimate registration, shared-network limits, invite approval, and controlled staff mail work with the site's database and mail stack.

Developers can add custom anti-spam fields with the `lworks_registration_antispam_fields` action and reject submissions with the `lworks_registration_antispam_result` filter.

## Privacy Notes

This plugin keeps access checks on the server side. Private request content is never included in notification emails. It is still important to run the site over HTTPS, keep WordPress/plugins/themes updated, restrict admin access, and use MFA for administrators and coordinators.
