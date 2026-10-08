# GISTDA GeoJSON Import — Data43 Thailand Map

Source: GISTDA ArcGIS REST service: https://gistdaportal.gistda.or.th/arcgis/rest/services/ข้อมูลเขตการปกครอง/MapServer

Verified layers: province (2), amphoe (3), tambon (4). Official layer fields: P_code, A_code, T_code, P_Name_T, A_Name_T, T_Name_T. REST layer service reference CRS 32647; importer explicitly requests output CRS 4326.

## XAMPP Windows

```bat
cd /d C:\xampp\htdocs\roster_pro
C:\xampp\php\php.exe scripts\import_gistda_geojson.php --province=all
C:\xampp\php\php.exe scripts\import_gistda_geojson.php --province=all --write
```

The first call is DRY RUN: download+normalize+validate all three levels without changes. The second writes validated data to:
- `assets/geojson/thailand/provinces.geojson`
- `assets/geojson/thailand/amphoes.geojson`
- `assets/geojson/thailand/tambons.geojson`

Existing files get timestamped backups. A `gis-import-manifest.json` records source, UTC date, feature counts and SHA-256 hashes.

**Temporary province-only test:** Replace `--province=all` with `--province=33` for Si Sa Ket. Running this mode publishes ONLY province 33 geometry: repeat with `all` before nationwide launch.

## Mandatory release steps
1. Enable CLI PHP cURL and JSON extensions, install SSL CA roots.
2. Review official source provenance, licence/reuse permission and administrative version. An accessible ArcGIS service alone does not guarantee redistribution rights.
3. Confirm that province/amphoe/tambon codes and Thai names match the reporting period of your Data43 ZIP aggregates.
4. On PHP CLI dry-run, ensure all three layers validate and counts are plausible. Do not bypass incomplete-coverage or orphan-area errors.
5. Run with `--write`; hard-refresh `index.php?c=data43&a=thailand_map`.
6. Compare a selection of codes against the hospital master; GeoJSON only provides borders, not hospital-to-service-area mappings.
7. Deploy a cached/partitioned national map for production scale; a nationwide tambon GeoJSON may be too large for some devices.
8. The map's medical indicators still require independently validated aggregates and privacy review.

**Status:** GISTDA map layers were verified via public REST metadata, but live nationwide download and DB installation could not be executed from the assistant environment. This PR provides the deterministic importer for your XAMPP environment; no official boundary files are claimed to have been imported into your PC yet.
