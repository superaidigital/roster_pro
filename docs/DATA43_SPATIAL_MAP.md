# 43-file spatial map — setup and release checklist

## Install
1. Back up the existing MySQL database.
2. Apply migrations in order: `20261007_data43_submissions.sql`, `20261007_data43_spatial_analytics.sql`, then `20261008_data43_spatial_map.sql`.
3. Confirm `data43_area_metrics` already has aggregate rows from uploads. Re-upload data if the spatial schema was installed after earlier submissions (uploads are not retained).
4. Open `index.php?c=data43&a=spatial` as ADMIN/SUPERADMIN and import validated, legitimately sourced administrative GeoJSON separately for PROVINCE, AMPUR and TAMBON.
5. GeoJSON `properties.area_code` must be Thailand administrative area codes with leading zeroes preserved: province 2 digits, district 4 digits and tambon 6 digits. District codes must start with the corresponding province code, tambon codes with district code. For source-specific property names, normalize OFFLINE first.
6. Use the admin assignment form to associate active `hospitals.id` with one or more imported tambon codes. These are **responsibility areas**, not patient locations.
7. Verify source file, metric, report month and facility filter across each drill-down level before release.

## Behavior
- Leaflet/OSM map, hover highlight, persistent selected outline, province → amphoe → tambon drill-down, breadcrumbs, lookup, map legend and KPI.
- Map API returns only aggregate values from the latest *successful (COMPLETE/INCOMPLETE)* hospital monthly submission. It never reads raw individual rows.
- Indicators currently represent **source-file record counts**, **not deduplicated people, prevalence or incidence**. Each indicator explicitly selects ONE `metric_code` + ONE `source_file_code`; do not sum `RECORDS` across 43 files.
- Suppression threshold is 5; 1–4 counts are not returned by API. For a public or broadly distributed deployment, prefer coarse classes only and perform a differencing/privacy review across hierarchy, months and hospital filters.
- GeoJSON boundaries are admin-uploaded; the repository does NOT include verified GIS polygons. Without them the interface shows a missing-master state, not fake geographic features.
- The service links area metrics to the hospital master by `hospital_id`. Facility responsibility links are stored in `data43_hospital_service_areas` and do not modify historical aggregates.

## Pre-production QA (required)
- Run PHP lint and smoke test on PHP 8.2+ and MySQL 8.
- Test access: session absent, authorized admin, facility-scoped user; test invalid hospital/month/metric/level/parent combinations.
- Confirm 43-file area fields use actual two-digit official CHANGWAT, AMPUR, TAMBON administrative codes. Some imports may not have these columns, yielding an unmapped area; do not infer codes from a facility's name.
- Verify no overlap/double-counting for each chosen source metric and geographical level.
- Test edge cases: incomplete imports; import retry; deletion; late uploads; empty month; malformed/big GeoJSON; unknown codes; facility status changes; loading across phone/tablet/desktop.
- Validate boundary source authority, its license and spatial accuracy; do not upload patient-level features.
- Confirm Content Security Policy allows explicitly pinned Leaflet assets and OSM tiles; consider self-hosting pinned Leaflet assets and approved tiles for availability and usage policy compliance.
- Enforce HTTPS, audit admin changes, disaster recovery and role-based access in deployment.
- Do not claim disease-specific NCD indicators until upstream metrics implement validated clinical inclusion/exclusion and population denominators.

## Example normalized feature
```json
{"type":"Feature","properties":{"area_code":"330101","name_th":"ตำบลตัวอย่าง"},"geometry":{"type":"Polygon","coordinates":[[[104,15],[104.01,15],[104.01,15.01],[104,15]]]} }
```
This is an illustrative **schema example**, not an authentic boundary.
