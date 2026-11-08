<?php

// What each part of the site may know about routes: the storefront never lists admin URLs.
return [
    'groups' => [
        'storefront' => ['home', 'store.*', 'robots'],
    ],
];
