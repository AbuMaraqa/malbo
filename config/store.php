<?php

return [

    /*
    | Storefront fonts: Arabic + Latin Google Fonts. family = the css2 `family=` value. `malbo` is the
    | brand guidelines' pair: KO Sans for Arabic (self-hosted, `local_fonts`) and Readex Pro for Latin.
    */
    'default_font' => 'malbo',

    'fonts' => [
        'malbo' => ['name' => 'KO Sans + Readex Pro (MALBO)', 'family' => 'Readex+Pro:wght@300;400;500;600;700', 'stack' => "'KO Sans', 'Readex Pro', system-ui, sans-serif"],
        'tajawal' => ['name' => 'Tajawal', 'family' => 'Tajawal:wght@400;500;700', 'stack' => "'Tajawal', system-ui, sans-serif"],
        'cairo' => ['name' => 'Cairo', 'family' => 'Cairo:wght@400;600;700', 'stack' => "'Cairo', system-ui, sans-serif"],
        'almarai' => ['name' => 'Almarai', 'family' => 'Almarai:wght@400;700', 'stack' => "'Almarai', system-ui, sans-serif"],
        'ibm-plex-sans-arabic' => ['name' => 'IBM Plex Sans Arabic', 'family' => 'IBM+Plex+Sans+Arabic:wght@400;500;700', 'stack' => "'IBM Plex Sans Arabic', system-ui, sans-serif"],
        'noto-kufi-arabic' => ['name' => 'Noto Kufi Arabic', 'family' => 'Noto+Kufi+Arabic:wght@400;600;700', 'stack' => "'Noto Kufi Arabic', system-ui, sans-serif"],
        'readex-pro' => ['name' => 'Readex Pro', 'family' => 'Readex+Pro:wght@300;400;500;600;700', 'stack' => "'Readex Pro', system-ui, sans-serif"],
        'changa' => ['name' => 'Changa', 'family' => 'Changa:wght@400;600;700', 'stack' => "'Changa', system-ui, sans-serif"],
        'el-messiri' => ['name' => 'El Messiri', 'family' => 'El+Messiri:wght@400;600;700', 'stack' => "'El Messiri', system-ui, serif"],
        'rubik' => ['name' => 'Rubik', 'family' => 'Rubik:wght@400;500;700', 'stack' => "'Rubik', system-ui, sans-serif"],
        'noto-sans-arabic' => ['name' => 'Noto Sans Arabic', 'family' => 'Noto+Sans+Arabic:wght@400;600;700', 'stack' => "'Noto Sans Arabic', system-ui, sans-serif"],
    ],

    /*
    | Fonts served from public/ (FontCatalog::fontFaces), weight => file name without its extension:
    | the first of .woff2, .woff, .otf or .ttf that is there is used, and a weight with none is
    | skipped. KO Sans (Kotype, ko-type.com; a commercial font, licensed per store) draws the Arabic
    | letters only: Latin falls to Readex Pro.
    */
    'local_fonts' => [
        'KO Sans' => [
            'unicode_range' => 'U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF,U+200C-200F',
            'files' => [
                300 => 'fonts/ko-sans/ko-sans-light',
                400 => 'fonts/ko-sans/ko-sans-regular',
                500 => 'fonts/ko-sans/ko-sans-medium',
                600 => 'fonts/ko-sans/ko-sans-semibold',
                700 => 'fonts/ko-sans/ko-sans-bold',
            ],
        ],
    ],

    /*
    | The store's color, from the MALBO brand guidelines only: the surfaces its white logo may sit on
    | (maroon, the Pantone maroon, the near-black). All readable with white text.
    */
    'default_color' => '#6D1C2F',

    'color_presets' => [
        'malbo' => '#6D1C2F',
        'malbo-spot' => '#73232A',
        'malbo-black' => '#231F20',
    ],

    /*
    | The fonts the storefront may use (keys of `fonts`): the brand guidelines' pair, or Readex Pro
    | alone. The admin panel chooses from the whole list.
    */
    'storefront_fonts' => ['malbo', 'readex-pro'],

    /*
    | The MALBO identity (public/brand, drawn from the brand guidelines): shown while the owner has not
    | uploaded a logo or a favicon of their own. logo_light is the version for maroon surfaces.
    */
    'brand' => [
        'logo' => 'brand/malbo-logo.svg',
        'logo_light' => 'brand/malbo-logo-light.svg',
        'mark' => 'brand/malbo-mark.svg',
        'favicon' => 'brand/favicon.svg',
    ],

];
