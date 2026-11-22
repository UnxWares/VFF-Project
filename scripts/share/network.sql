-- Réseau en service aujourd'hui (dernière ère de la carte), simplifié pour l'image de partage de l'accueil.
-- Lecture seule. Deux classes : lignes à grande vitesse (lines.is_lgv) et reste du réseau.
-- Sortie : un objet JSON {"lgv": MultiLineString, "reseau": MultiLineString}, coordonnées à 3 décimales.
SELECT json_build_object(
    'lgv', ST_AsGeoJSON(ST_SimplifyPreserveTopology(ST_LineMerge(ST_Collect(s.geom) FILTER (WHERE l.is_lgv)), 0.012), 3)::json,
    'reseau', ST_AsGeoJSON(ST_SimplifyPreserveTopology(ST_LineMerge(ST_Collect(s.geom) FILTER (WHERE NOT l.is_lgv)), 0.012), 3)::json
)
FROM line_segments s
JOIN lines l ON l.id = s.line_id
WHERE s.era_id = (SELECT max(id) FROM eras)
  AND s.status = 'in_service'
  AND ST_XMin(s.geom) > -5.5 AND ST_XMax(s.geom) < 10 AND ST_YMin(s.geom) > 41 AND ST_YMax(s.geom) < 51.5;
