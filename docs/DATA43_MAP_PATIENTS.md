# Patient drill-down from the Data43 choropleth

**This is restricted clinical/authorized public-health functionality, not public map data.**
The original \`map_data\` still returns aggregate cells only. This feature reads encrypted manual \`PERSON\` + \`ADDRESS\` registry records by facility; 43-file ZIP aggregates cannot be reverse-converted to patient names.

## Install
1. Apply \`database/migrations/20261007_data43_registry.sql\` if not yet installed.
2. Apply \`database/migrations/20261008_data43_map_patients.sql\` in phpMyAdmin.
3. Configure \`DATA43_RECORD_KEY\` as before; **do not rotate or lose existing encryption key**.
4. Grant access **only to a verified workforce member who needs specific facility records for care or public-health duties**. No role implicitly authorizes disclosure.
5. Backfill ADDRESS area codes for **each eligible hospital**, using XAMPP command prompt:
\`\`\`bat
cd /d C:\xampp\htdocs\roster_pro
C:\xampp\php\php.exe scripts\reindex_data43_patient_areas.php --hospital=1
C:\xampp\php\php.exe scripts\reindex_data43_patient_areas.php --hospital=1 --write
\`\`\`
Hospital ID 1 is an **example**; use verified real \`hospitals.id\`.

## Example grant (DBA-only, after verifying users and hospital IDs)
\`\`\`sql
INSERT INTO data43_patient_view_grants
(user_id, hospital_id, purpose_code, granted_by, revoked_at)
VALUES (123, 5, 'CARE', 1, NULL)
ON DUPLICATE KEY UPDATE revoked_at=NULL, granted_by=VALUES(granted_by), granted_at=NOW();
\`\`\`
**Example IDs only; never execute with placeholder IDs without checking.**
Revocation:
\`\`\`sql
UPDATE data43_patient_view_grants SET revoked_at=NOW()
WHERE user_id=123 AND hospital_id=5 AND purpose_code='CARE';
\`\`\`
Grant/revoke operations should themselves be added to a separately audited admin workflow before broad production rollout.

## Controls
- Explicit facility-specific, purpose-specific grant required, even for admins.
- Non-admins must also belong to the chosen hospital in their session.
- Anti-CSRF POST-only patient list and profile access.
- Area code validation (2/4/6 digits), same-hospital PERSON/ADDRESS join.
- Read access audit for area list and profile open; 50 result cap; no patient data in map API.
- No CSV export or patient pins through the map.

## Production caveats
- The map uses aggregate ZIP data whereas patient details require separately captured PERSON/ADDRESS records; the two sources may not match.
- The ADDRESS index is updated on new registry saves, deletes and backfills; independent SQL import/update paths must synchronize it as well.
- Historical data may contain multiple addresses; determine primary/current address under clinical policy before production.
- Restrict other existing registry routes consistently with the new permission model. This new map route cannot itself secure every pre-existing clinical screen.
- Conduct Thai PDPA/health-data purpose, retention, workforce authorization, field minimization, security, audit and incident-response review before deployment.
- Test PHP syntax and MySQL schema on an isolated/staging system before enabling patient access.
