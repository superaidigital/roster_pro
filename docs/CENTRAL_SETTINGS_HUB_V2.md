# Central Settings Hub v2 — Roster Pro

## What was added
- Readiness dashboard: PHP version/extensions, Composer PDF vendor, storage and LINE configuration.
- General settings: system name, organization/contact details, announcement toggle/text, custom maintenance notice.
- LINE Messaging API credential UX: write-only password inputs; blank retains existing value; explicit checkbox clears; existing tokens/secrets never appear in rendered HTML.
- Audited settings changes, with old/new values *redacted* for LINE token, secret and target ID. A transactional change history table is required.
- Reuses the existing restricted ADMIN/SUPERADMIN settings controller, CSRF POST verification, and application LogsController.
- Renders active announcement safely as escaped text on application pages.

## Database setup
Back up MySQL, then apply `database/migrations/20261008_central_settings_audit.sql` via phpMyAdmin.

## Windows XAMPP commands
```bat
cd /d C:\xampp\htdocs\roster_pro
C:\xampp\php\php.exe -l controllers\SettingsController.php
C:\xampp\php\php.exe -l services\CentralSettingsService.php
C:\xampp\php\php.exe -l views\settings\system.php
C:\xampp\php\php.exe -l views\layouts\header.php
```

## Regression checklist
1. ADMIN can edit general settings; unauthorized roles get denied.
2. A blank LINE token/secret field retains the previous credential; explicit clear erases it.
3. HTML page source and audit history do not include the LINE token/secret.
4. Test CSRF missing/invalid and invalid input, including malformed LINE Target ID.
5. Verify announcements are shown as text and an injected HTML tag is escaped.
6. Enable maintenance using an admin account; test regular-user access is blocked and admin retains access.
7. Verify audit logs contain old/new only for non-sensitive keys; check rollback on an audit-table failure.
8. Verify installation on the user's PHP 8.0 test instance; Composer PDF check warns until the separate PDF dependencies are compatible and installed.
9. Smoke-test LINE send and the existing roster/leave functionality.

## Follow-up architecture recommendations
- Move LINE credentials from plaintext system_settings to application-managed encrypted secrets (requiring a well-managed encryption key and migration compatibility).
- Isolate privileged security policy changes and credential rotations to SUPERADMIN with reauthentication.
- Provide transactional backup checks, scheduled integrity checks, environment-specific configuration, and automated integration tests.
- Restrict and log high-risk Factory Reset and data disposal activities.
- Do not interpret readiness checks as proof of deployment security or functional production readiness.

**This PR has not been deployed to XAMPP nor run against the real MySQL database.**
