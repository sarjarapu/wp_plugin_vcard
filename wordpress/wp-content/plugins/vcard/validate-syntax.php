<?php
/**
 * Simple syntax validation for PHP files
 */

$files = [
    'includes/class-template-customizer.php',
    'includes/class-template-engine.php',
    'tests/test-template-customizer.php'
];

foreach ($files as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($filepath), $output, $return_var);
        
        if ($return_var === 0) {
            echo "✓ $file - Syntax OK\n";
        } else {
            echo "✗ $file - Syntax Error:\n";
            echo implode("\n", $output) . "\n";
        }
    } else {
        echo "✗ $file - File not found\n";
    }
}

echo "\nValidation complete.\n";
?>