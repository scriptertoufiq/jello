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

];
