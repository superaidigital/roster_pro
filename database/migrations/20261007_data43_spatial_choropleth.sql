-- Upgrade existing spatial analytics schema to support province-level choropleth.
ALTER TABLE data43_area_metrics
    MODIFY area_level ENUM('CHANGWAT','AMPUR','TAMBON','VILLAGE') NOT NULL;

-- Existing deployments should re-import the target reporting month so CHANGWAT,
-- DM/HT/NCD/ANC/ELDERLY/DISABLED/SERVICE/POPULATION aggregates are generated
-- by the updated importer.
