<?php

// API webhooks (profile section, App\Http\Controllers\WebhookSubscriptionController,
// App\Services\Webhooks).
return [
    'title' => 'Webhooks',
    'intro' => 'With a webhook, French Railway Lines sends a short signed JSON message to the HTTPS address of your choice whenever an event you follow happens. Your program no longer needs to poll the API at regular intervals.',
    'docs_link' => 'Message format and signature verification',

    'events' => [
        'contribution_applied' => [
            'label' => 'Contribution published',
            'hint' => 'An approved contribution has just been applied to a line or a station.',
        ],
        'current_era_refreshed' => [
            'label' => 'Today’s network recalculated',
            'hint' => 'Lines or stations have changed in the current era (daily recalculation).',
        ],
        'accident_published' => [
            'label' => 'New accident',
            'hint' => 'An accident record is published once its sources have been checked.',
        ],
        'material_published' => [
            'label' => 'New rolling stock',
            'hint' => 'An import has just published a rolling stock record.',
        ],
    ],

    'list_title' => 'Your subscriptions',
    'count' => ':count of :max',
    'empty' => 'You have no subscriptions yet.',
    'created_at' => 'Created on :date',
    'active' => 'Active',
    'disabled' => 'Disabled',
    'disabled_failures' => 'Disabled on :date after :count failed deliveries in a row. Check that your address answers with a 2xx code, then re-enable the subscription.',
    'failing' => 'Failed deliveries in a row: :count (disabled at :max).',
    'events_followed' => 'Events followed: :list',

    'log_title' => 'Latest deliveries',
    'log_empty' => 'No deliveries yet.',
    'log_caption' => 'Latest deliveries for this subscription, newest first',
    'log_date' => 'Date',
    'log_event' => 'Event',
    'log_result' => 'Result',
    'log_attempts' => 'Attempts: :count',
    'log_http' => 'HTTP :code',
    'statuses' => [
        'pending' => 'Pending',
        'succeeded' => 'Delivered',
        'failed' => 'Failed',
        'skipped' => 'Not sent',
    ],
    'delivery_errors' => [
        'timeout' => 'timed out',
        'connection_failed' => 'could not connect',
        'http_status' => 'response code not accepted',
        'redirect' => 'redirect (never followed)',
        'address_refused' => 'address refused',
        'dns_failed' => 'name not found',
        'internal' => 'error on our side',
    ],

    'enable' => 'Re-enable',
    'enable_label' => 'Re-enable the subscription to :url',
    'delete' => 'Delete',
    'delete_label' => 'Delete the subscription to :url',
    'delete_confirm_title' => 'Delete this subscription?',
    'delete_confirm_text' => 'Nothing more will be sent to :url. The delivery log is deleted with the subscription.',
    'delete_confirm_button' => 'Delete the subscription',
    'cancel' => 'Cancel',

    'create_title' => 'Add a subscription',
    'url_label' => 'Receiving address',
    'url_hint' => 'A public HTTPS address on port 443, for example https://example.org/vff/webhook. Local and private addresses are refused.',
    'events_legend' => 'Events to receive',
    'create_button' => 'Add the subscription',
    'blocked_suspended' => 'Your account is suspended: you cannot add a subscription, and nothing is sent to your addresses until the suspension ends.',
    'blocked_max' => 'You have :max subscriptions, the maximum. Delete one to add another.',
    'rules' => 'Each message goes through the queue. If delivery fails, it is attempted :attempts times in all, from 1 minute to 2 hours apart. After :max failed deliveries in a row, the subscription is disabled and you are notified on the site.',

    'new_title' => 'Copy your signing secret now',
    'new_text' => 'It will not be shown again. Your program uses it to check that each message really comes from French Railway Lines.',
    'new_label' => 'Signing secret',
    'copy' => 'Copy the secret',
    'copied' => 'Secret copied.',
    'copy_failed' => 'Copying failed: select the secret and copy it by hand.',

    'errors' => [
        'url_required' => 'Enter the receiving address.',
        'events_required' => 'Choose at least one event.',
        'suspended' => 'Your account is suspended: you cannot add a subscription.',
        'too_many' => 'You already have :max subscription, the maximum.|You already have :max subscriptions, the maximum.',
        'url' => [
            'invalid' => 'This address is not valid.',
            'not_https' => 'The address must start with https://.',
            'credentials' => 'The address must not contain a user name or password.',
            'port' => 'Only the standard HTTPS port (443) is accepted.',
            'host' => 'This name points to a local or internal network. Enter a public address.',
            'private_address' => 'This address leads to a private, local or reserved network. Enter a public address.',
            'unresolved' => 'This domain name cannot be found.',
        ],
    ],

    'flash' => [
        'created' => 'The subscription has been added.',
        'deleted' => 'The subscription has been deleted.',
        'enabled' => 'The subscription has been re-enabled.',
    ],

    'notification' => [
        'title' => 'A webhook subscription has been disabled',
        'body' => 'Delivery to :url failed :count time in a row. Nothing more is sent there. You can re-enable the subscription from your profile.|Deliveries to :url failed :count times in a row. Nothing more is sent there. You can re-enable the subscription from your profile.',
    ],
];
