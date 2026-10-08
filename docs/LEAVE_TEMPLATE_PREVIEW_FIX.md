# Fix template editor PDF/Word previews

Root cause: unclosed `forEach` JavaScript callback in `views/leave/template_editor.php` produced `Uncaught SyntaxError: missing ) after argument list`, preventing the PDF page renderer from initializing.

Changes:
- Repair editor JavaScript parsing; avoid replacing a dragged DOM element while its pointer capture is active.
- Display an authenticated, same-origin PDF iframe as a **fallback preview only** when PDF.js fails. Dragging and saving are disabled in fallback mode so fields cannot be placed inaccurately.
- Display DOCX paragraphs and tables through a limited PHP ZipArchive + XML DOM reader, escaping text via DOM `textContent`. No Word file uploads to external converters or online document viewers.
- Keep the template replace/delete/versioning features from PR #42. Word preview is **readable content, not pixel-perfect Word rendering**.

## XAMPP tests
1. Ensure PHP extensions `zip`, `dom`/`xml`, `mbstring` are enabled.
2. Run PHP lint on `controllers/LeaveController.php`, `services/LeaveWordPreviewService.php`, `views/leave/template_editor.php`, `views/leave/templates.php`.
3. Open a PDF template. Verify no SyntaxError in DevTools, original PDF renders, page/zoom and drag/resize/save work. Block the PDF.js CDN in DevTools to confirm the iframe fallback.
4. Open a DOCX template. Verify readable paragraphs/tables render and no Word content is interpreted as HTML. Check a file with placeholders, headings, Thai content and a table.
5. Confirm non-admin roles cannot access `template_word_preview` or `template_download` endpoints.
6. Check replacement DOCX/PDF version increments and confirmed delete behavior, including refusal to delete referenced templates.
7. Restart Apache and hard-refresh after switching branches.

The PR has not been validated against the user's actual XAMPP server or private documents.
