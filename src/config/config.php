<?php

return [
    'image' => [
        'extra_formats'  => [
//            ['format' => 'avif', 'quality' => 70],
            ['format' => 'webp', 'quality' => 90],
        ],
        'version'        => true, // Add ?v=timestamp to image url
        'legacy_libwebp' => false,
    ]
];
