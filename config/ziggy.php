<?php

// What each part of the site may know about routes: the storefront never lists admin URLs, nor the
// wholesale portal's; the portal knows its own and the store's.
return [
    'groups' => [
        'storefront' => ['home', 'store.*', '!store.wholesale.*', 'robots'],
        'wholesale' => ['home', 'store.*', 'robots'],
    ],
];
