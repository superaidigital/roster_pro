# Sisaket administrative GeoJSON

Subset for Si Sa Ket (ADM1_PCODE `TH33`) used by the 43-file spatial analytics dashboard.

Source: https://github.com/piyayut-ch/mapthai
The upstream dataset documents Thailand administrative levels 1-3 and cites Royal Thai Survey Department / UNOCHA source material. See the upstream repository for license and provenance.

Files:
- adm1.geojson — province boundary
- adm2.geojson — district boundaries
- adm3.geojson — subdistrict boundaries

Village polygons are not included in this source. The application renders village-level aggregates as privacy-preserving colored aggregate cells/circles from area centroids, never individual-person pins.
