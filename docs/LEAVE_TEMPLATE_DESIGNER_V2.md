# Roster Pro — Leave Form Designer v2

The page `index.php?c=leave&a=template_editor&id=...` now uses a three-panel layout: field palette, PDF document, and selected-field inspector.

## Functional workflow
- PDF is rendered from the authenticated `template_download?preview=1` endpoint with PDF.js. Browser session and administrator authorization are required.
- A PDF's original page dimensions determine the paper ratio; there is no fixed A4 distortion.
- Drag a field onto the page or click it in the left palette. Drag the selected field to move it or its bottom-right handle to resize it.
- Adjust X/Y/width/height in the inspector and navigate pages using the toolbar.
- Coordinates remain relative to the original PDF page (percentages) and persist through zoom.
- Save commits the entire mapping atomically using the existing CSRF-protected `template_fields_save` route and `leave_form_fields` table.
- Word/DOCX templates keep the existing placeholder-based document workflow; no coordinate-based edit is claimed.

## Release checklist
1. Back up MySQL and the template storage directory.
2. Apply `database/migrations/20261007_leave_form_templates.sql` and `database/migrations/20261008_leave_template_multitype.sql`, where not already applied.
3. `C:\xampp\php\php.exe -l views\leave\template_editor.php`
4. Sign in as ADMIN, upload a 2-page PDF, open the editor, verify pages, drag and resize two placeholders, zoom from 75% to 150%, save, reload, and verify stored positioning.
5. Verify server-side permissions: anonymous users and non-ADMIN roles must be unable to open PDF source or save mappings.
6. Test in a narrow/mobile viewport. Verify DOM/CSP/network console errors.
7. Production CSP currently permits the jsDelivr CDN for scripts. Better availability/security: package and self-host a reviewed/pinned version of PDF.js, with worker configured on the same origin.

**Important limitation:** The PDF editor saves layout metadata, but cannot yet generate a populated PDF or apply digital signatures to the rendered PDF. Do not describe PDF generation as ready. No browser/XAMPP integration tests have run from ChatGPT.
