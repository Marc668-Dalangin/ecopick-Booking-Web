<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!Auth::check() || Auth::userRole() !== 'admin') {
    http_response_code(403);
    exit('Admin access required.');
}

$database = Database::getInstance();
$pdo = $database->getPDO();
$quoteIdentifier = static function (string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
};

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d') . '.sql"');

echo "-- EcoPick database backup generated " . gmdate('c') . "\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$tableStatement = $pdo->query('SHOW TABLES');
while (($tableRow = $tableStatement->fetch(PDO::FETCH_NUM)) !== false) {
    $tableName = (string) ($tableRow[0] ?? '');
    if ($tableName === '') {
        continue;
    }

    $quotedTable = $quoteIdentifier($tableName);
    $schemaStatement = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
    $schemaRow = $schemaStatement->fetch(PDO::FETCH_ASSOC);
    $createSql = (string) ($schemaRow['Create Table'] ?? $schemaRow['Create View'] ?? '');
    if ($createSql === '') {
        continue;
    }

    echo "-- Table: {$tableName}\n";
    echo "DROP TABLE IF EXISTS {$quotedTable};\n";
    echo rtrim($createSql, "; \t\r\n") . ";\n";

    $rows = $pdo->query('SELECT * FROM ' . $quotedTable);
    $columnCount = $rows->columnCount();
    $valueBatch = [];
    while (($row = $rows->fetch(PDO::FETCH_NUM)) !== false) {
        $values = [];
        foreach ($row as $value) {
            $values[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
        }
        if (count($values) !== $columnCount) {
            continue;
        }
        $valueBatch[] = '(' . implode(', ', $values) . ')';
        if (count($valueBatch) >= 100) {
            echo "INSERT INTO {$quotedTable} VALUES " . implode(",\n", $valueBatch) . ";\n";
            $valueBatch = [];
        }
    }
    if ($valueBatch !== []) {
        echo "INSERT INTO {$quotedTable} VALUES " . implode(",\n", $valueBatch) . ";\n";
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
$pdo = null;