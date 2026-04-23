<?php
/**
 * Copy this file to config.php and adjust the values.
 * config.php is loaded by presets.php.
 */

return [
    // Shared secret. Required for POST/DELETE (saving/deleting). GET is open.
    // Generate something like: php -r "echo bin2hex(random_bytes(16));"
    'token' => 'ENTERSUPERSECRETPASSWORDHERE',

    // Directory where preset files (NAME.json + NAME.svg) are stored.
    // The web server user must be able to write here.
    'presetsDir' => __DIR__ . '/presets',

    // CORS. Use '*' while developing, later restrict to your own origin.
    'allowedOrigin' => '*',
];
