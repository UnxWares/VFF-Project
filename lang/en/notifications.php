<?php

// Site notifications (/notifications page, account menu) and the emails that accompany them.
return [
    'meta_title' => 'Notifications',
    'title' => 'Notifications',
    'menu' => 'Notifications',
    'unread_count_one' => ':count unread notification',
    'unread_count_other' => ':count unread notifications',
    'all_read' => 'You have read everything.',
    'mark_all' => 'Mark all as read',
    'list_label' => 'Your notifications',
    'unread' => 'Unread',
    'reason' => 'Reason',
    'empty_title' => 'No notifications',
    'empty_text' => 'When one of your contributions is reviewed, you will be told here.',
    'flash' => [
        'all_read' => 'All your notifications are marked as read.',
    ],

    'about' => 'Line: :subject.',
    'reason_line' => 'Reason: :reason',
    'open' => 'View the contribution',
    'kinds' => [
        'awaiting_review' => [
            'title' => 'Contribution no. :id is waiting for your review',
            'body' => 'It will be published once two different members have approved it.',
        ],
        'approved' => [
            'title' => 'Your contribution no. :id has received its first approval',
            'body' => 'It will be published once a second reviewer has approved it.',
        ],
        'applied' => [
            'title' => 'Your contribution no. :id has been published',
            'body' => 'It received both approvals and is now part of the encyclopaedia.',
        ],
        'rejected' => [
            'title' => 'Your contribution no. :id was not accepted',
            'body' => 'A reviewer declined it for the following reason.',
            'body_without_reason' => 'A reviewer declined it without giving a reason. To find out more, write to contact@vff-project.org.',
        ],
    ],
];
