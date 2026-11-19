<?php

/*
 * Account moderation: suspension by an administrator, lifting, messages to the member.
 * Commitments of the terms of use (“Validation and moderation”) and of the privacy policy:
 * any change must stay consistent with them. The French file is the reference.
 */

return [
    'errors' => [
        'suspended' => 'Your account is suspended: you can no longer propose contributions, review them or report errors. Your profile stays available, with the reason and your data.',
        'suspended_until' => 'Your account is suspended until :date inclusive: you can no longer propose contributions, review them or report errors. Your profile stays available, with the reason and your data.',
        'self' => 'You cannot suspend your own account.',
        'last_admin' => 'This account is the last administrator able to act: suspending it would leave the site with nobody to assign roles or lift a suspension.',
        'already_suspended' => 'This account is already suspended. Lift the current suspension before setting a new one.',
        'not_suspended' => 'This account is not suspended.',
        'reason_required' => 'Give the reason for the suspension (at least :min characters): it is shown to the member.',
        'reason_max' => 'The reason cannot be longer than :max characters.',
        'until_format' => 'The end date is not valid.',
        'until_future' => 'The end date must be after today.',
    ],

    'flash' => [
        'suspended' => ':name’s account is suspended. The member has been told on the site and by email.',
        'lifted' => 'The suspension of :name’s account is lifted. The member has been told.',
    ],

    'notification' => [
        'suspended' => [
            'title' => 'Your account is suspended',
            'indefinite' => 'An administrator has suspended your account. The suspension lasts until they lift it.',
            'until' => 'An administrator has suspended your account until :date inclusive.',
            'effect' => 'You can no longer propose contributions, review them or report errors. You can still browse the site, download your data and delete your account from your profile.',
        ],
        'lifted' => [
            'title' => 'The suspension of your account is lifted',
            'body' => 'You can propose contributions and report errors again, and review them if your role allows it.',
        ],
        'contest' => 'To contest this decision, write to contact@vff-project.org: it will be looked at again, as far as possible by someone other than the person who made it.',
    ],
    'mail' => [
        'action' => 'Open my profile',
    ],

    'profile' => [
        'title' => 'Your account is suspended',
        'indefinite' => 'The suspension lasts until an administrator lifts it.',
        'until' => 'The suspension lasts until :date inclusive.',
        'since' => 'It began on :date.',
        'reason' => 'Reason: :reason',
        'effect' => 'You can no longer propose contributions, review them or report errors. Your data stays available below: you can download it or delete your account.',
        'contest' => 'To contest this decision, write to contact@vff-project.org.',
    ],

    'report' => [
        'suspended' => 'Your account is suspended: you cannot send a report. The reason is in your profile.',
        'profile_link' => 'Open my profile',
    ],

    'admin' => [
        'meta_title' => 'Accounts',
        'title' => 'Accounts',
        'subtitle' => 'Suspend an account that breaks the terms of use, or lift a suspension. The member receives the reason on the site and by email.',
        'nav_link' => 'Accounts',
        'search_label' => 'Search for an account',
        'search_hint' => 'Name or email address.',
        'search_button' => 'Search',
        'filter_label' => 'Show accounts',
        'filters' => [
            'all' => 'All accounts',
            'suspended' => 'Suspended',
        ],
        'list_label' => 'Accounts',
        'empty' => [
            'all' => 'No account matches this search.',
            'suspended' => 'No account is suspended.',
        ],
        'member_since' => 'Joined on :date',
        'contributions' => 'Contributions: :count',
        'badge_suspended' => 'Suspended',
        'suspended_since' => 'Suspended on :date',
        'until' => 'until :date inclusive',
        'indefinite' => 'until lifted',
        'by' => 'by :name',
        'reason' => 'Reason',
        'lift' => 'Lift the suspension',
        'suspend_open' => 'Suspend this account',
        'reason_label' => 'Reason for the suspension',
        'reason_hint' => 'Shown to the member, on the site and by email. At least :min characters.',
        'until_label' => 'Last day of the suspension',
        'until_hint' => 'Optional. Without a date, the suspension lasts until you lift it.',
        'submit' => 'Suspend the account',
        'self_note' => 'Your account',
    ],
];
