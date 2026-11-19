<?php

// Reporting an error from a sheet (line, station, rolling stock, accident) and the list of
// reports in the review area (/validate/reports).
return [
    'prompt' => 'Does something on this page look wrong?',
    'button' => 'Report an error',

    'dialog' => [
        'title' => 'Report an error',
        'intro' => 'The team reads every report and corrects the page when the error is confirmed.',
        'subject' => 'Page',
        'description_label' => 'What is wrong',
        'description_hint' => 'Say which information is wrong and, if you know it, the correct value. 2,000 characters at most.',
        'source_label' => 'Source',
        'source_hint' => 'Link to a document, article or page that supports the correction.',
        'contact_label' => 'Your email address',
        'contact_hint' => 'Only if you are happy for the team to write to you about this report.',
        'optional' => 'optional',
        'honeypot_label' => 'Leave this field empty',
        'privacy' => 'Your message and address are used only to handle this report. They are deleted 12 months after it has been dealt with.',
        'privacy_link' => 'Privacy policy',
        'submit' => 'Send the report',
        'cancel' => 'Cancel',
        'thanks_title' => 'Thank you, your report has been sent',
        'thanks_text' => 'The team will read it. If you left your address, they may write to you to ask for more detail.',
        'close' => 'Close',
    ],

    'errors' => [
        'description_required' => 'Describe the error you found.',
        'description_min' => 'Describe the error in at least :min characters.',
        'description_max' => 'Your message is longer than :max characters.',
        'source_url' => 'Enter a full web address starting with https:// or http://.',
        'contact_email' => 'This email address is not valid.',
        'too_fast' => 'The form was sent too quickly. Wait a few seconds, then send it again.',
        'too_many' => 'You have sent a lot of reports in a short time. Please try again a little later.',
        'subject_missing' => 'This page no longer exists. Please reload.',
        'token' => 'The form could not be prepared. Check your connection, then open it again.',
    ],

    // Site notification sent to validators and administrators.
    'notification' => [
        'title' => 'Report no. :id to review',
        'body' => 'An error has been reported on the page “:subject”.',
    ],

    'review' => [
        'meta_title' => 'Error reports',
        'title' => 'Error reports',
        'subtitle' => 'Errors reported from the sheets by visitors and members.',
        'nav_label' => 'Review area',
        'contributions_link' => 'Contributions to review',
        'reports_link' => 'Error reports',
        'filter_label' => 'Show reports',
        'filters' => [
            'open' => 'Open',
            'resolved' => 'Dealt with',
            'rejected' => 'Rejected',
        ],
        'statuses' => [
            'open' => 'Open',
            'resolved' => 'Dealt with',
            'rejected' => 'Rejected',
        ],
        'subject_types' => [
            'line' => 'Line',
            'station' => 'Station',
            'material' => 'Rolling stock',
            'accident' => 'Accident',
        ],
        'report_number' => 'Report no. :id',
        'open_sheet' => 'View the page',
        'sheet_missing' => 'This page no longer exists.',
        'received' => 'Received on :date',
        'by_member' => 'from :name',
        'by_visitor' => 'from a visitor',
        'source' => 'Source',
        'contact' => 'Contact',
        'page_language' => 'Page language',
        'handled_by' => 'on :date by :name',
        'handled' => 'on :date',
        'note' => 'Team note',
        'note_label' => 'Team note',
        'note_hint' => 'What was corrected, or why the report is rejected (required to reject).',
        'mark_resolved' => 'Mark as dealt with',
        'reject' => 'Reject',
        'empty' => [
            'open' => 'No open reports',
            'resolved' => 'No reports dealt with',
            'rejected' => 'No rejected reports',
        ],
        'empty_text' => 'Errors reported from the sheets arrive here, and every validator receives a notification.',
        'flash' => [
            'resolved' => 'Report no. :id is marked as dealt with.',
            'rejected' => 'Report no. :id has been rejected.',
            'closed' => 'This report has already been dealt with.',
        ],
        'errors' => [
            'note_required' => 'Say why this report is rejected.',
            'note_max' => 'The note is longer than :max characters.',
        ],
    ],
];
