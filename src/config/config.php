<?php

return [
    'image' => [
        'extra_formats'  => [
            ['format' => 'webp', 'quality' => 90],
        ],
        'version'        => true, // Add ?v=timestamp to image url
        'legacy_libwebp' => false,
    ],

    'video' => [
        'version' => true, // Add ?v=timestamp to video url
    ]
];
