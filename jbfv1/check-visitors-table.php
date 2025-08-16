<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query('DESCRIBE visitors');
    $columns = $stmt->fetchAll();
    foreach ($columns as $column) {
        echo $column['Field'] . ' ' . $column['Type'] . ' ' . $column['Key'] . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>