<?php

// Installable web app (resources/js/pwa): update banner and offline page.
return [
    'update_available' => 'A new version of the site is available.',
    'update_reload' => 'Reload',
    'update_later' => 'Later',

    'offline_document_title' => 'Offline - French Railway Lines',
    'offline_title' => 'You are offline',
    'offline_text' => 'This page has not been saved on this device, so it needs a connection. Check your network, then try again.',
    'offline_retry' => 'Try again',

    // Copy of a page served without a network (Components/OfflineCopyNotice.svelte); :date = date and time of the copy.
    'offline_copy' => 'You are offline. This is the copy of the page saved on this device on :date; it may have changed since.',
    'offline_map_partial' => 'The map has no background and only shows the lines and stations already displayed on this device.',
    'offline_map_basemap' => 'The map only shows what has already been displayed on this device: the lines, the stations and the background of areas already viewed.',
];
