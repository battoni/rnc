<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachment disk
    |--------------------------------------------------------------------------
    |
    | Where a screenshot attached to an idea is written. It defaults to the
    | `public` disk — local storage behind `php artisan storage:link` — because
    | that needs no credentials and the board is small.
    |
    | It is configurable so that it can move. The droplet is 24 GB shared with
    | six projects and has filled to zero once; the day attachments matter more
    | than they cost, this becomes an S3-compatible disk and nothing else has to
    | change. Each attachment row records the disk it was written to, so rows
    | written before that day keep resolving to where their file actually is.
    |
    */

    'attachment_disk' => env('IDEA_ATTACHMENT_DISK', 'public'),

];
