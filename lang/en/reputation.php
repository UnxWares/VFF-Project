<?php

/*
 * Member reputation: help page, profile, review area.
 * Levels and thresholds live in config/reputation.php (same keys under `levels`).
 * Anything changed here must stay consistent with the privacy policy (privacy.php).
 */

return [
    'meta_title' => 'Member reputation',
    'meta_description' => 'What the reputation figures count on VFF, the threshold of each level and what they change.',

    'hero' => [
        'title' => 'Member reputation',
        'subtitle' => 'Three figures and one level, worked out the same way for everyone. Nothing else is taken into account.',
    ],

    'figures' => [
        'title' => 'What is counted',
        'intro' => 'The figures come from the contributions and reviews recorded on the site. They are recalculated after every review decision.',
        'applied' => [
            'title' => 'Accepted contributions',
            'text' => 'Contributions approved by two validators and then published in the encyclopaedia.',
        ],
        'rejected' => [
            'title' => 'Rejected contributions',
            'text' => 'Contributions a validator has rejected, with their reason. A contribution removed by an administrator (spam, unlawful content) is not counted anywhere.',
        ],
        'reviews' => [
            'title' => 'Reviews done',
            'text' => 'The approvals and rejections decided by a validator. Reviewing your own contribution is not possible.',
        ],
        'rate' => [
            'title' => 'Acceptance rate',
            'text' => 'Accepted contributions divided by those that received a decision (accepted plus rejected), rounded down to a whole number. A contribution still waiting is not included.',
        ],
    ],

    'levels' => [
        'newcomer' => 'New member',
        'contributor' => 'Contributor',
        'regular' => 'Regular contributor',
        'experienced' => 'Experienced contributor',
    ],

    'thresholds' => [
        'title' => 'The levels',
        'intro' => 'Each level has its own thresholds, written in the site configuration. A member has the highest level whose thresholds are all met. The level is a guide for validators. It grants no rights: review is the same for everyone, with two approvals before publication.',
        'everyone' => 'Every member, from sign-up.',
        'requires' => 'Requires :conditions.',
        'applied_one' => 'at least :count accepted contribution',
        'applied_other' => 'at least :count accepted contributions',
        'reviews_one' => 'at least :count review done',
        'reviews_other' => 'at least :count reviews done',
        'acceptance' => 'an acceptance rate of at least :count%',
        'rate_note' => 'The acceptance rate is only checked once a contribution has received a decision.',
    ],

    'rules' => [
        'title' => 'A few details',
        'who_title' => 'Who sees these figures',
        'who_text' => 'You see your own in your profile. Validators see the author\'s, with their level, next to their name when they review a contribution. There is no public profile, and reputation is not part of the API.',
        'suspended_title' => 'Suspended account',
        'suspended_text' => 'A suspended account keeps its figures, but no level is shown for as long as the suspension lasts.',
        'deleted_title' => 'Deleted account',
        'deleted_text' => 'The figures disappear with the account. Published contributions stay, without a name: a validator then sees "Anonymous contributor" and no figures.',
        'recalc_title' => 'Recalculation',
        'recalc_text' => 'The figures are recalculated after every review decision. An administrator can also recalculate everything at once, with the same result: it depends only on the contributions and reviews on record.',
        'export_title' => 'Your data',
        'export_text' => 'Your figures are included in the export of your data from your profile.',
        'privacy_link' => 'Privacy policy',
    ],

    'profile' => [
        'title' => 'Reputation',
        'level' => 'Level',
        'applied' => 'Accepted contributions',
        'rejected' => 'Rejected contributions',
        'reviews' => 'Reviews done',
        'acceptance' => 'Acceptance rate',
        'percent' => ':count%',
        'level_hidden' => 'No level is shown while your account is suspended. Your figures are kept.',
        'next' => 'To reach the “:level” level: :conditions.',
        'missing_applied_one' => ':count more accepted contribution',
        'missing_applied_other' => ':count more accepted contributions',
        'missing_reviews_one' => ':count more review',
        'missing_reviews_other' => ':count more reviews',
        'missing_acceptance' => 'an acceptance rate of at least :count%',
        'help_link' => 'How reputation is calculated',
    ],

    'validate' => [
        'by' => 'by :name',
        'applied_one' => ':count accepted',
        'applied_other' => ':count accepted',
        'rejected_one' => ':count rejected',
        'rejected_other' => ':count rejected',
    ],
];
