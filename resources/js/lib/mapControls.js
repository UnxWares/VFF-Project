// Libellés des boutons de MapLibre dans la langue de la page (sinon « Zoom in »…), partagés par la
// carte (RailMap) et la vue ferroviaire (SchematicMap).

/**
 * @param {(key: string) => string} t
 * @param {Record<string, string>} [overrides] libellés propres à une carte (nom de la carte…)
 * @returns {Record<string, string>} option `locale` de maplibregl.Map
 */
export function mapControlsLocale(t, overrides = {}) {
    return {
        'Map.Title': t('maps.controls.map'),
        'NavigationControl.ZoomIn': t('maps.controls.zoom_in'),
        'NavigationControl.ZoomOut': t('maps.controls.zoom_out'),
        'NavigationControl.ResetBearing': t('maps.controls.reset_bearing'),
        'FullscreenControl.Enter': t('maps.controls.fullscreen_enter'),
        'FullscreenControl.Exit': t('maps.controls.fullscreen_exit'),
        'AttributionControl.ToggleAttribution': t('maps.controls.attribution'),
        'AttributionControl.MapFeedback': t('maps.controls.map_feedback'),
        'Popup.Close': t('maps.controls.popup_close'),
        'GeolocateControl.FindMyLocation': t('maps.controls.locate'),
        'GeolocateControl.LocationNotAvailable': t('maps.controls.locate_unavailable'),
        ...overrides,
    };
}
