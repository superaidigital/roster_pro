# Roster Pro Design System v2 — Release Checklist

Branch: `ui/design-system-v2`

## Merge status

- [x] Branch is based on current `main`
- [x] Branch is ahead of `main` and not behind
- [x] Router/front controller restored
- [x] Design System v2 loaded globally
- [x] Dashboard redesigned
- [x] Roster desktop + mobile day view redesigned
- [x] Leave + approval workflow redesigned
- [x] Shift swap workflow redesigned
- [x] Users / Reports / Settings redesigned
- [x] WCAG-oriented accessibility layer added
- [x] Session ID regenerated after successful login
- [x] Notification mutations changed to POST + CSRF
- [x] Staff mutations changed to POST + CSRF
- [x] Roster AJAX mutations protected by CSRF
- [x] Settings POST actions protected by CSRF
- [x] Backup direct web access blocked
- [x] Backup downloads routed through authenticated controller
- [x] Cron backup secret removed from source code
- [x] TLS peer/host verification enabled for external HTTP calls

## Production blocker

### LINE Notify is no longer available

The current codebase still contains LINE Notify integration using:

- `https://notify-api.line.me/api/notify`
- `line_notify_token`
- LINE Notify settings UI

LINE Notify was terminated on 2025-03-31. Before production release, migrate notification delivery to LINE Official Account Messaging API.

Recommended replacement configuration:

- `line_channel_access_token`
- `line_channel_secret`
- Messaging API push/multicast/broadcast strategy
- Mapping between Roster Pro users and LINE user IDs (or group/chat target strategy)
- Delivery logging and retry handling

Do not treat the existing LINE Notify test button as a valid production readiness test.

## Required environment configuration

### Cron backup secret

Configure one of:

1. Environment variable:
   `ROSTER_PRO_CRON_KEY=<strong-random-secret>`

2. Or `system_settings`:
   `cron_backup_key=<strong-random-secret>`

Never commit the actual secret to Git.

## Required local QA before merge

Run from the repository root on the XAMPP machine:

```cmd
scripts\qa-release.cmd
```

Then manually test:

- Login / logout
- Admin Dashboard
- Staff Dashboard
- Roster: create/edit/delete shift
- Roster: auto schedule
- Roster: submit / approve / return for edit
- Roster mobile day view
- Leave request
- Leave medical certificate upload/download
- Leave approve/reject/cancel
- Shift swap: requester -> target -> manager approval
- Users add/edit/suspend/delete
- Staff add/edit/delete
- Reports overview + print
- Settings update
- Holiday add/sync/delete
- Backup create/download/delete
- Factory Reset only on disposable test database
- Keyboard navigation
- Modal focus
- Mobile viewport 360px / 390px / 768px / desktop

## Database backup before merge/deploy

Before applying this release to a real database:

1. Create a full SQL backup.
2. Verify the backup can be downloaded through the authenticated backup controller.
3. Keep a copy outside the web root.
4. Do not test Factory Reset against production data.

## Suggested merge sequence

```cmd
git fetch origin
git switch ui/design-system-v2
git pull --ff-only origin ui/design-system-v2
scripts\qa-release.cmd
```

If QA passes:

```cmd
git switch main
git pull --ff-only origin main
git merge --no-ff ui/design-system-v2
git status
```

Do not push `main` until the LINE Messaging API blocker is resolved or notification delivery is intentionally disabled and documented.
