<?php

// Conservative engineering defaults; must be reviewed against FIG-P0 V-01
// and target hosting before applying. FIG-P1 registers no upload endpoint.
return [
    'max_bytes' => 5 * 1024 * 1024,
    'max_dimension' => 4096,
    'max_pixels' => 12000000,
    'max_position' => 8,
];
