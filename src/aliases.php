<?php

// Existing fields store `studioespresso\navigate\fields\NavigateField` in the database and project config.
// PHP class names are case-insensitive, but Composer won't find src/fields/ on a case-sensitive filesystem,
// so load the moved class whenever the old spelling is requested. PHP blocks a nested autoload of the same
// (case-insensitive) name, hence the require instead of class_exists().
spl_autoload_register(function (string $class): void {
    if (strcasecmp($class, 'studioespresso\navigate\Fields\NavigateField') === 0) {
        require_once __DIR__.'/Fields/NavigateField.php';
    }
});
