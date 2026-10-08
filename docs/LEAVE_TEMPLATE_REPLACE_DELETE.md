# Template replace and delete — Roster Pro

## What changed
- Each template row now includes **upload replacement** and **delete** buttons.
- Replacement creates a new immutable template ID and increments its version, preserving the original file and any generated document references.
- READY DOCX replacement becomes active and archives its predecessor. PDF and DOCX without placeholders remain inactive/pending, leaving the previous template undisturbed.
- Replacement inherits the old template's leave-type selection, hospital, notes and ordering.
- Permanent deletion requires ADMIN/SUPERADMIN, POST + CSRF, explicit `DELETE` confirmation and *zero* references in `leave_generated_documents`. Mapping rows are removed by foreign-key cascade. Referenced templates must be archived, not deleted.
- All changes use the existing LogsController audit mechanism. Deletion never deletes an unrelated file; file cleanup is limited to the template-storage directory.

## XAMPP QA checklist
1. Back up MySQL and `storage/leave_templates`.
2. Ensure original template migrations and multi-type migration are installed.
3. Run `C:\xampp\php\php.exe -l controllers\LeaveController.php`, `-l models\LeaveTemplateModel.php`, and `-l views\leave\templates.php`.
4. Sign in as ADMIN: replace a working DOCX containing placeholders; verify the new version is active, old is archived and historical generated docs are still downloadable.
5. Replace with PDF or DOCX without placeholders; verify the prior READY template remains available, and the new version is pending.
6. Delete an unused template; verify DB record and stored file cleanup. Try deleting a template referenced by a generated leave document; deletion must be refused.
7. Verify non-admin and unauthenticated users cannot POST to either endpoint. Check CSRF, invalid file type/size, PDF header, malformed DOCX, duplicate version, storage permission failures, and browser error messages.
8. Run `scripts\qa-release.cmd` and browser workflow tests before merging to production.

**Not yet runtime-tested** on the user's XAMPP installation; the GitHub PR is a proposed change, not a deployed release.
