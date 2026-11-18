import { validateStyleMin } from '../../node_modules/.pnpm/@maplibre+maplibre-gl-style-spec@24.8.5/node_modules/@maplibre/maplibre-gl-style-spec/dist/index.mjs';
import * as rs from '../../resources/js/lib/railStyle.js';
const ACTIVE = rs.ACTIVE_STATUSES;
const w = (base) => ['interpolate', ['linear'], ['zoom'], 4, ['*', base[0], ['case', ['==', ['get', 'is_lgv'], true], 1.7, ['==', ['get', 'status'], 'service_track'], 0.7, 1]], 8, ['*', base[1], 1], 12, ['*', base[2], 1], 16, ['*', base[3], 1]];
const hiddenTraffic = ['freight'];
const activeFilter = ['all', true, ['any',
  ['all', ['==', ['get', 'is_heritage'], true], ['match', ['get', 'status'], ACTIVE, true, false]],
  ['all', ['!=', ['get', 'is_heritage'], true], ['match', ['get', 'status'], ACTIVE, true, false], ['match', ['coalesce', ['get', 'traffic_type'], 'unknown'], hiddenTraffic, false, true]],
  ['==', ['get', 'status'], 'service_track']]];
const style = { version: 8, sources: { l: { type: 'vector', tiles: ['http://x/{z}/{x}/{y}'] } }, layers: [
  { id: 'gone', type: 'line', source: 'l', 'source-layer': 'vff-lines', filter: ['all', ['match', ['get', 'status'], ['dismantled', 'razed'], true, false], true], paint: { 'line-color': ['match', ['get', 'status'], 'razed', rs.STATUS_COLOR.razed, rs.STATUS_COLOR.dismantled], 'line-width': w([1, 1.6, 2.6, 4]), 'line-dasharray': [1, 1.5], 'line-opacity': 0.75 } },
  { id: 'closed', type: 'line', source: 'l', 'source-layer': 'vff-lines', filter: ['==', ['get', 'status'], '__none__'], paint: { 'line-color': rs.STATUS_COLOR.abandoned, 'line-dasharray': [2.5, 1.5] } },
  { id: 'active', type: 'line', source: 'l', 'source-layer': 'vff-lines', filter: activeFilter, layout: { 'line-cap': 'round' }, paint: { 'line-color': rs.segmentColorExpr(), 'line-width': w([1.5, 2.5, 4, 6]), 'line-opacity': 1 } },
] };
const errs = validateStyleMin(style);
console.log(errs.length ? errs.map((e) => e.message).join('\n') : 'style OK');
