# Automatic PDF generation — Roster Pro Leave Templates

## Workflow
1. Upload a PDF in Leave Template Manager.
2. Open **จัดวางฟิลด์**, drag fields onto the **actual PDF page**, then save.
3. The PDF mapping becomes **READY** after at least one field is saved; turn on the template using the play/pause button.
4. In your leave history click **PDF**. The server reads the chosen PDF template, retrieves the request's leave data, stamps the saved field values, and returns a downloadable PDF.
5. The generated PDF is recorded in the existing **leave_generated_documents** table with SHA-256 and authorization rules. Originals are never overwritten.

The server also retains the DOCX pathway. Selection of a PDF or DOCX template still respects hospital scope and multi-select leave types.

## XAMPP prerequisites

Open Windows Command Prompt in C:\xampp\htdocs\roster_pro:

\`\`\`bat
cd /d C:\xampp\htdocs\roster_pro
composer install
C:\xampp\php\php.exe scripts\qa-leave-pdf.php
C:\xampp\php\php.exe -l services\LeavePdfDocumentService.php
C:\xampp\php\php.exe -l controllers\LeaveController.php
\`\`\`

The PHP CLI used for Composer **must match XAMPP's PHP**, with mbstring, fileinfo, zip extensions enabled. If PHP executable is not on PATH, use Composer's Windows launcher with XAMPP PHP or install dependencies with \`C:\xampp\php\php.exe composer.phar install\`.

Choose a legally installed Thai Unicode TrueType font, e.g. Sarabun-Regular.ttf, and define the environment variable \`LEAVE_PDF_FONT_FILE\` as an **absolute** path to that TTF on the Apache service. Restart Apache when changing environment variables. By default this module tries \`C:/Windows/Fonts/tahoma.ttf\` as a Windows fallback. No fonts or personal data are bundled into the repository.

## Limitations and safety
- FPDI's free parser supports many standard PDFs but **may not accept compressed/object-stream or encrypted PDF files**; sanitize/normalize the PDF template with an authorized PDF tool before uploading. Test real government forms.
- Field coordinates are percent positions. A PDF template should be normal portrait/landscape pages with matching CropBox; test rotated pages separately.
- A field's saved width/height is a text box. Very long content may overflow or shrink; verify exported pages visually and do not sign or distribute incorrect documents.
- **Signature placeholders are intentionally ignored**. Implement an explicitly approved electronic-signature workflow before inserting signatures into generated documents.
- Approval status determines DRAFT vs FINAL file storage; a generated PDF is not an official cryptographic digital signature.
- The FPDI/TCPDF generation runs inside the local PHP server; documents are NOT uploaded to online PDF conversion services.
- Protect \`storage/leave_templates\`, \`storage/leave_documents\` from unauthenticated HTTP access. Existing Apache access settings need deployment verification.
- This feature needs PHP runtime tests, installed packages/font and MySQL/browser end-to-end tests on XAMPP **before calling it production ready**. The GitHub branch itself does not install dependencies on your PC.

## Acceptance tests
1. Generate a DOCX from an existing enabled DOCX template; verify no regression.
2. Generate a PDF from an enabled PDF template, including Thai text and the 3 leave-days fields. Verify content visually after opening the downloaded PDF.
3. Test a PDF with 2 pages, zoom/reposition field then regenerate. Verify no page scaling or offset.
4. Test long fields and alignment, no signature injection, missing font and malformed PDF errors.
5. Verify owner / assigned facility supervisor / admin can download; unrelated staff get HTTP 403.
6. Verify generated document metadata, hash, and correct MIME type PDF vs Word.
7. Verify replacing/deleting unused templates does not break stored generated PDFs.
