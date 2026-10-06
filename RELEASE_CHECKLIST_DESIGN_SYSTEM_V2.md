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

## LINE Messaging API migration

- [x] Production LINE Notify endpoint removed
- [x] Central `NotificationService` added
- [x] `LineMessagingService` uses Messaging API push endpoint
- [x] Roster events routed through LINE Messaging API
- [x] Leave request events routed through LINE Messaging API
- [x] Swap request events routed through LINE Messaging API
- [x] Settings UI migrated to Channel access token / Channel secret / Target ID
- [x] Signed LINE webhook endpoint added
- [x] Webhook captures the latest user/group/room Source ID

Required setup before enabling LINE delivery:

1. Create or select a LINE Official Account Messaging API channel.
2. Save the Channel access token and Channel secret in Roster Pro Settings.
3. Configure the webhook URL displayed in Roster Pro Settings in LINE Developers Console.
4. Enable webhook delivery in the LINE channel.
5. Send a message to the OA, or add the OA to the intended group and send a message.
6. Confirm that Roster Pro shows a latest Source ID.
7. Copy the intended Source ID into Target ID.
8. Enable LINE Messaging API and the required event toggles.
9. Use the Test Message action and confirm delivery.

Keep the existing in-app notification system enabled even when LINE delivery is disabled.

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

Do not push `main` until the local QA script and manual smoke tests pass, including a LINE Messaging API test when LINE delivery is enabled.
