# HOSPCODE9 coverage remediation (MOPH)

The system-health warning “ยังขาด HOSPCODE9 3/3 หน่วยบริการ” is calculated from active `hospitals` records, not from the map. The previous hospital edit modal did not include `hospital_code9` even though the controller expected it; its JavaScript function also had a parameter mismatch.

## Official code types
The Thai MOPH facility-code registry distinguishes:
- Old 9-digit code: **nine numeric digits** (`hospital_code9`).
- New 9-character code: **two uppercase letters followed by seven numeric digits** (`hospital_code9_new`), introduced in 2024 and used for newly issued codes since April 1, 2025.
- These are assigned identifiers and **must not be guessed or padded from the 5-digit identifier**.
Registry: https://hcode.moph.go.th/about/ and https://hcode.moph.go.th/code/

## XAMPP deployment
1. Back up the MySQL database.
2. Apply `database/migrations/20261007_data43_hospital_code9.sql` if `hospital_code9` is missing.
3. Apply `database/migrations/20261008_hospital_code9_new.sql`.
4. Open `index.php?c=hospitals` and update each facility using the code(s) verified from MOPH.
5. Check `index.php?c=data43&a=health` again. The coverage is now satisfied by a valid old OR new nine-character code.
6. Review `HOSPCODE9` export contracts with receiving systems before selecting new vs old: the form default now favors the new assigned code if present.

## Caveats
This update validates *format*, not actual issuance or facility identity. Do not mark the warning fixed without checking against the authoritative registry. Before merge, test on XAMPP and inspect for unintended edits on the old hospital listing and existing CSV import logic. Existing hospital-management endpoints have legacy CSRF concerns and should be hardened before broad internet deployment.
