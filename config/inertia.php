<?php

return [

    /*
    | Page components live in resources/js/pages (lowercase). Inertia's default
    | is js/Pages, which only works on case-insensitive file systems, so the
    | testing assertions failed on Linux (CI) while passing on Windows/macOS.
    */

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],

];
