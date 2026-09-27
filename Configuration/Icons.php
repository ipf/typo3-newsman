<?php

declare(strict_types=1);

/**
 * Register extension icons.
 *
 * Returning the icon definitions keeps registration lazy: building the icon
 * registry here would touch the container while it is still being assembled.
 */

return [
    'newsman-subscribe' => [
        'source' => 'EXT:newsman/Resources/Public/Icons/Extension.svg',
    ],
];
