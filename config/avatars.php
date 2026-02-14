<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Valid avatar filenames
    |--------------------------------------------------------------------------
    |
    | Avatar images are stored in public/images/avatars/ with naming
    | pattern portrait-no-border{N}.png
    |
    */
    'valid' => array_map(
        fn (int $i) => "portrait-no-border{$i}.png",
        range(1, 200)
    ),
];
