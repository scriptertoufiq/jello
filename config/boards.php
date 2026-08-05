<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Board Backgrounds
    |--------------------------------------------------------------------------
    |
    | The background swatches a board may use. The key is stored on the board
    | and the value is the Tailwind gradient applied to the board header. Keys
    | are validated on create and update, so adding a swatch here is all that
    | is needed to offer it in the UI.
    |
    */

    'backgrounds' => [
        'slate' => 'from-slate-500 to-slate-700',
        'blue' => 'from-sky-500 to-blue-700',
        'green' => 'from-emerald-500 to-green-700',
        'amber' => 'from-amber-400 to-orange-600',
        'rose' => 'from-rose-500 to-pink-700',
        'violet' => 'from-violet-500 to-purple-700',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Lists
    |--------------------------------------------------------------------------
    |
    | The columns created alongside a new board so it is usable immediately
    | rather than starting empty.
    |
    */

    'default_lists' => [
        'To Do',
        'In Progress',
        'Done',
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Colors
    |--------------------------------------------------------------------------
    |
    | The colour keys a label may use, mapped to the Tailwind classes used to
    | render the swatch. Keys are validated when labels are created.
    |
    */

    'label_colors' => [
        'green' => 'bg-emerald-500',
        'yellow' => 'bg-amber-400',
        'orange' => 'bg-orange-500',
        'red' => 'bg-rose-500',
        'purple' => 'bg-violet-500',
        'blue' => 'bg-sky-500',
        'sky' => 'bg-cyan-400',
        'lime' => 'bg-lime-400',
        'pink' => 'bg-pink-500',
        'gray' => 'bg-zinc-500',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Labels
    |--------------------------------------------------------------------------
    |
    | Labels created alongside a new board.
    |
    */

    'default_labels' => [
        ['name' => 'Bug', 'color' => 'red'],
        ['name' => 'Feature', 'color' => 'green'],
        ['name' => 'Chore', 'color' => 'blue'],
        ['name' => 'Urgent', 'color' => 'orange'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    /*
    | Attachments are stored on a private disk and streamed through the
    | authorized download/preview routes. Putting them on the `public` disk
    | would make every file readable by anyone holding the URL, including on
    | private boards. The disk is recorded per attachment, so files uploaded
    | under an older setting keep resolving against the disk they were
    | written to.
    */

    'attachments' => [
        'disk' => env('BOARD_ATTACHMENT_DISK', 'local'),
        'max_kilobytes' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Board Thumbnails
    |--------------------------------------------------------------------------
    |
    | The optional cover image shown on the board card and board header. When
    | no thumbnail is set the board falls back to its background gradient.
    | The disk must be publicly readable; the default `public` disk needs
    | `php artisan storage:link` to have been run.
    |
    */

    'thumbnails' => [
        'disk' => env('BOARD_THUMBNAIL_DISK', 'public'),
        'max_kilobytes' => 4096,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'max_dimension' => 4000,
    ],

];
