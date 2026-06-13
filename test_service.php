<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

try {
    // Attempt to test the external function
    require_once(__DIR__ . '/classes/external/save_progress.php');
    echo "Class loaded successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
