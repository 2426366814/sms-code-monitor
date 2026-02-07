<?php
// 测试PDO MySQL扩展

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Loaded Config: " . php_ini_loaded_file() . "\n\n";

echo "Extensions:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo "  - $ext\n";
}

echo "\nPDO Drivers:\n";
if (class_exists('PDO')) {
    echo "  Available drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
} else {
    echo "  PDO not available\n";
}

echo "\nMySQL Test:\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', '');
    echo "  Connected to MySQL successfully!\n";
} catch (PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
