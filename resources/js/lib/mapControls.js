// Textes de MapLibre dans la langue de la page (sinon « Zoom in », « Use two fingers to move the map »…).
// `./maplibre.js` les donne à toutes les cartes du site ; une carte peut encore en remplacer quelques-uns
// (nom de la carte…) par son option `locale`.

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
        'CooperativeGesturesHandler.WindowsHelpText': t('maps.controls.gestures_windows'),
        'CooperativeGesturesHandler.MacHelpText': t('maps.controls.gestures_mac'),
        'CooperativeGesturesHandler.MobileHelpText': t('maps.controls.gestures_mobile'),
        'Marker.Title': t('maps.controls.marker'),
        'LogoControl.Title': t('maps.controls.logo'),
        'GlobeControl.Enable': t('maps.controls.globe_enable'),
        'GlobeControl.Disable': t('maps.controls.globe_disable'),
        'TerrainControl.Enable': t('maps.controls.terrain_enable'),
        'TerrainControl.Disable': t('maps.controls.terrain_disable'),
        ...overrides,
    };
}

/**
 * Options d'une carte avec les textes de la page : ceux que la carte donne elle-même (`options.locale`)
 * l'emportent.
 *
 * @template {{ locale?: Record<string, string> }} T
 * @param {T} options options de maplibregl.Map
 * @param {(key: string) => string} t
 * @returns {T}
 */
export function withPageLocale(options, t) {
    return { ...options, locale: mapControlsLocale(t, options?.locale ?? {}) };
}
