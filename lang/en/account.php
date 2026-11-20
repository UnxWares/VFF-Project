<?php

/*
 * Account data: export, deletion, inactive accounts, notices to members.
 * The French file is the reference.
 */

return [
    'anonymous_contributor' => 'Anonymous contributor',

    'data' => [
        'title' => 'My data',
        'intro' => 'You can download everything French Railway Lines keeps about your account, or delete your account.',
        'privacy_link' => 'The privacy policy explains your rights.',
        'export_title' => 'Download my data',
        'export_text' => 'A JSON file with your profile, your contributions, your approvals and your reviews.',
        'export_button' => 'Download my data',
        'delete_title' => 'Delete my account',
        'delete_text' => 'Your profile (name, email address, bio, avatar) is erased. Your published contributions stay in the encyclopaedia, credited to “Anonymous contributor”. Your pending or rejected contributions are deleted. This cannot be undone.',
        'delete_button' => 'Delete my account',
        'delete_confirm_title' => 'Delete your account permanently?',
        'delete_confirm_text' => 'Enter your password to confirm. Remember to download your data first: it cannot be recovered afterwards.',
        'delete_password_label' => 'Password',
        'delete_password_required' => 'Enter your password to confirm the deletion.',
        'delete_confirm_button' => 'Delete permanently',
        'delete_cancel' => 'Cancel',
    ],

    'flash' => [
        'deleted' => 'Your account has been deleted. Your published contributions stay in the encyclopaedia, without your name.',
        'contribution_removed' => 'The contribution has been removed and its author informed.',
    ],

    'errors' => [
        'last_admin' => 'You are the only administrator: give this role to another member before deleting your account.',
        'removal_published' => 'This contribution is already published: correct the data with a new contribution instead of removing it.',
    ],

    'mail' => [
        'greeting' => 'Hello :name,',
        'salutation' => 'The French Railway Lines team',

        'inactive' => [
            'subject' => 'Your French Railway Lines account will be deleted on :date',
            'intro' => 'You have not signed in to French Railway Lines for almost 3 years.',
            'deletion' => 'As stated in the privacy policy, an account left unused for 3 years is deleted. Yours will be deleted on :date.',
            'keep' => 'To keep it, sign in before that date.',
            'action' => 'Sign in',
            'contributions' => 'If your account is deleted, your published contributions will stay in the encyclopaedia, without your name. The others will be deleted.',
        ],
        'last_admin' => [
            'subject' => 'The last French Railway Lines administrator is inactive',
            'greeting' => 'Hello,',
            'intro' => 'Account no. :id, the only administrator of the site, has not been used since :date.',
            'kept' => 'Accounts unused for 3 years are deleted, but not this one: without it, nobody could assign roles any more.',
            'next' => 'Appoint another administrator (php artisan vff:make-admin) or sign in again with this account.',
        ],

        'removed' => [
            'subject' => 'Your contribution no. :id has been removed',
            'intro' => 'Your contribution no. :id, sent on :date, has been removed by the French Railway Lines team.',
            'reason' => 'Reason: :reason',
            'contest' => 'To contest this decision, write to contact@vff-project.org: it will be reviewed again, where possible by someone other than the person who made it.',
            'action' => 'Read the terms of use',
        ],

        'terms' => [
            'use-conditions' => [
                'subject' => 'Changes to the French Railway Lines terms of use',
                'intro' => 'The French Railway Lines terms of use are changing. The new version comes into force on :date.',
            ],
            'privacy' => [
                'subject' => 'Changes to the French Railway Lines privacy policy',
                'intro' => 'The French Railway Lines privacy policy is changing. The new version comes into force on :date.',
            ],
            'read' => 'Please read it before that date.',
            'action' => 'Read the new version',
            'acceptance' => 'Continuing to use the service after that date means you accept it. If you do not accept it, you can delete your account from your profile.',
            'rights' => 'You can download your data or delete your account from your profile at any time.',
        ],
    ],
];
