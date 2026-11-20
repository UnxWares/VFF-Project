"""Extrait Geofabrik (.osm.pbf) → JSON au format Overpass, même sélection que
vff:import-osm-greenways (voies vertes / pistes cyclables + ways ferroviaires,
relations vélo avec leurs membres). Sert quand Overpass est saturé.

Usage : python -I pbf2overpass.py <pyenv> <a.osm.pbf> [<b.osm.pbf> ...] <sortie.json>
Données © contributeurs OpenStreetMap, ODbL.
"""
import json
import sys

sys.path.insert(0, sys.argv[1])
import osmium  # noqa: E402

PATHS = {'path', 'track', 'footway', 'bridleway', 'pedestrian'}
RAILWAY = {'rail', 'narrow_gauge', 'light_rail', 'funicular', 'abandoned', 'disused', 'razed', 'dismantled', 'construction', 'preserved'}
PREFIXES = ('abandoned', 'disused', 'razed', 'removed', 'dismantled', 'demolished', 'was', 'construction', 'preserved')
TRACKS = {'rail', 'narrow_gauge', 'light_rail', 'tram', 'funicular', 'monorail', 'yes', 'preserved', 'abandoned', 'disused', 'razed', 'dismantled', 'construction'}


def keep(tags):
    hw = tags.get('highway')
    if hw == 'cycleway':
        return True
    if hw in PATHS and tags.get('bicycle') in ('designated', 'official'):
        return True
    if tags.get('railway') in RAILWAY or tags.get('historic') == 'railway':
        return True
    return any(tags.get(p + ':railway') in TRACKS for p in PREFIXES)


inputs, out = sys.argv[2:-1], sys.argv[-1]
ways, rels = {}, {}
for path in inputs:
    for o in osmium.FileProcessor(path, osmium.osm.WAY | osmium.osm.RELATION):
        t = dict(o.tags)
        if o.is_way():
            if keep(t):
                ways[o.id] = (t, [n.ref for n in o.nodes])
        elif t.get('type') == 'route' and t.get('route') in ('bicycle', 'mtb'):
            rels[o.id] = (t, [m.ref for m in o.members if m.type == 'w'])

need = set()
for _, refs in ways.values():
    need.update(refs)
coords = {}
for path in inputs:
    for n in osmium.FileProcessor(path, osmium.osm.NODE).with_filter(osmium.filter.IdFilter(need)):
        if n.location.valid():
            coords[n.id] = (n.location.lat, n.location.lon)

els = []
for wid, (t, refs) in ways.items():
    g = [{'lat': coords[r][0], 'lon': coords[r][1]} for r in refs if r in coords]
    if len(g) >= 2:
        els.append({'type': 'way', 'id': wid, 'tags': t, 'geometry': g})
for rid, (t, members) in rels.items():
    m = [{'type': 'way', 'ref': w, 'role': ''} for w in members if w in ways]
    if m:
        els.append({'type': 'relation', 'id': rid, 'tags': t, 'members': m})
with open(out, 'w', encoding='utf-8') as f:
    json.dump({'version': 0.6, 'generator': 'pbf2overpass (Geofabrik)', 'elements': els}, f, ensure_ascii=False)
print(f'{out} : {len(ways)} ways, {len(els) - len(ways)} relations', file=sys.stderr)
