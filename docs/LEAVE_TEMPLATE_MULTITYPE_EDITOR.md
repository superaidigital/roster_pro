# Leave template manager: drag/drop and multiple leave types

## Install
1. Back up DB and uploaded template documents.
2. Apply original `database/migrations/20261007_leave_form_templates.sql` if necessary.
3. Apply `database/migrations/20261008_leave_template_multitype.sql` once.
4. Test in `index.php?c=leave&a=templates` as ADMIN or SUPERADMIN.
5. Run `C:\xampp\php\php.exe -l controllers\LeaveController.php`, `-l models\LeaveTemplateModel.php`, `-l views\leave\templates.php`, `-l views\leave\template_editor.php`, then full `scripts\qa-release.cmd`.

## Supported
- Drop DOCX/PDF file onto upload panel, or browse local file.
- Select multiple leave types for one template; empty selection means all types.
- Change template display order by dragging rows. Ordering influences tie-breaking in automatic DOCX selection, after hospital specificity and leave-type specificity.
- Edit metadata and type assignment without uploading an identical file.
- PDF layout editor previews a password/session-protected PDF inline and lets users drag fields from a palette, reposition, and save coordinates by page.
- DOCX templates continue using the existing `{{placeholder}}` mechanism. The editor explicitly explains that DOCX does not support visual coordinate placement.

## Known limitations / production test checklist
- PDF field positions are persisted only. Actual PDF text injection/export is **not implemented** and PDF templates remain `PENDING`; do not advertise PDF generation as ready.
- No client-side PDF.js vendoring or offline fallback. The editor uses CDN pdf.js 4.10.38 and requires allowed CSP, HTTPS and working internet.
- Validate hospital and leave-type assignments, mixed-category template precedence, legacy single-type migration, empty/all selection, drag reorder, archive, and edit.
- Confirm `storage/leave_templates` is not accessible directly over HTTP. Only role-checked `template_download` should serve files.
- The original DOCX render pipeline is unchanged. Existing generated document associations remain attached to their template versions.
- SQL migrations have NOT been executed by ChatGPT; PHP runtime QA and MySQL integration tests still need to pass in XAMPP.
