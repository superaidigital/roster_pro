# Roster Pro: release after removing the 43-file module

This release removes the health-file import/dashboards/map and patient drill-down module from Roster Pro, while keeping roster, leave, swap, staff, hospital/facility master, reports, LINE messaging, settings, and signatures.

## Safe Windows XAMPP deployment
1. Back up the MySQL database, the entire app directory, uploaded documents and keys/settings outside web-accessible directories.
2. Run \`git status\` and commit/stash user changes before switching branches.
3. Switch to \`refactor/remove-data43-restore-roster-core\` and \`git pull --ff-only\`.
4. Run \`C:\xampp\php\php.exe scripts\qa-roster-core.php\` and \`scripts\qa-release.cmd\`. The latter is a Windows batch script, not a PHP script.
5. Restart Apache/OPcache, hard-refresh the browser; verify old URLs like \`index.php?c=data43&a=index\` return 404.
6. Test login/logout, dashboard, roster edit/approval, leave/approval, swap, facility CRUD, staff/users/roles, signatures, reports, LINE webhook, notifications, and responsive UI under each role.

## Important: clinical data and backups
- **No local MySQL data is modified or dropped.** Existing data43_* tables may remain. Data disposal requires a separate approved retention/privacy decision and verified backups.
- GitHub history still contains the retired module. Removing code from the current branch does not purge Git history or legacy server-side files that are untracked.
- SQL backup copies committed under \`roster_pro_db.sql\`, \`roster_pro_monthly_*.sql\` and \`public/uploads/Backup/*.sql\` may contain confidential data. Audit and remove/relocate them securely as a separate project.
- Root \`.htaccess\` blocks HTTP access to SQL backups and private implementation directories **only when Apache allows overrides**. Test it by trying an unauthenticated HTTP request to a SQL file; expected 403. Otherwise configure virtual host restrictions or move private directories outside DocumentRoot.

## Compatibility
- \`hospitals.hospital_code9\` and \`hospital_code9_new\` remain general facility-master fields; they are not personal health records. A neutral migration retains the legacy column for fresh deployments.
- Database migration history for the removed module has been retired from the current branch; existing installed data/tables are not automatically changed.

## Quality gate
- GitHub Actions performs PHP syntax lint and static core integrity checks.
- This is not equivalent to end-to-end, live MySQL testing: do not claim production readiness until staging/browser tests and security verification pass.
