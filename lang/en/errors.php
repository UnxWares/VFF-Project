<?php

// Error pages (resources/js/Pages/Error.svelte, resources/views/errors/minimal.blade.php).
return [
    'eyebrow' => 'Error :code',
    'back' => 'Go back',
    'home' => 'Back to the home page',
    'reload' => 'Reload the page',
    'login' => 'Sign in',
    'explore' => 'To carry on browsing',
    'map' => 'Open the map',
    'lines' => 'Browse the lines',
    'stations' => 'Browse the stations',
    'retry_after' => 'You can try again in :seconds seconds.',

    '403' => [
        'title' => 'Access denied',
        'text' => 'You do not have permission to open this page. If you think this is a mistake, sign in with the right account or write to us.',
    ],
    '404' => [
        'title' => 'Page not found',
        'text' => 'The address you asked for does not exist, or the page has moved. Check the address, or start again from the home page, the map or the list of lines.',
    ],
    '405' => [
        'title' => 'Request not accepted',
        'text' => 'This address does not answer this type of request. Go back to the previous page or start again from the home page.',
    ],
    '419' => [
        'title' => 'Session expired',
        'text' => 'The page stayed open too long and your session has expired. Reload the page, then start again: what you had entered was not saved.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'text' => 'You sent too many requests in a short time. Wait a moment before trying again.',
    ],
    '500' => [
        'title' => 'Server error',
        'text' => 'Something went wrong on our side and it has been logged. Try again in a few moments; if it persists, tell us on Discord or GitHub.',
    ],
    '503' => [
        'title' => 'Service temporarily unavailable',
        'text' => 'The site is down for maintenance or very busy. Try again in a few minutes.',
    ],
    'default' => [
        'title' => 'Something went wrong',
        'text' => 'The page could not be displayed. Try again, or start again from the home page.',
    ],
];
