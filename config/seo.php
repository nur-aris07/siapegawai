<?php

/**
 * SEO-PUBLIC-01. This file is deployment policy, never editable via site_settings.
 * An operator must review each route's public content before adding its name to
 * approved_indexable_routes. Empty by default, even when global indexing is on.
 */
return [
    'canonical_origin' => env('SIAPEGAWAI_SEO_CANONICAL_ORIGIN', ''),
    'indexing_enabled' => env('SIAPEGAWAI_SEO_INDEXING_ENABLED', false),
    'approved_indexable_routes' => [],
];
