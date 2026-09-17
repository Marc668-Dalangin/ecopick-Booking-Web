<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!Auth::check() || Auth::userRole() !== 'admin') {
    http_response_code(403);
    exit('Admin access required.');
}

$pdo = Database::getInstance()->getPDO();
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
// Keep TIMESTAMP values in UTC while they are read and restored.
$pdo->exec("SET time_zone = '+00:00'");

$quoteIdentifier = static function (string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
};

$formatValue = static function ($value, string $columnType) use ($pdo): string {
    if ($value === null) {
        return 'NULL';
    }

    $numericType = preg_match(
        '/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|bit|year)/i',
        $columnType
    ) === 1;
    if ($numericType && is_numeric($value)) {
        return (string) $value;
    }

    $quotedValue = $pdo->quote((string) $value);
    if ($quotedValue === false) {
        throw new RuntimeException('Unable to quote a database value.');
    }

    return $quotedValue;
};

try {
    $sqlOutput = [
        '-- EcoPick Complete Database Backup',
        'SET FOREIGN_KEY_CHECKS = 0;',
        'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";',
        'SET time_zone = "+00:00";',
        'SET NAMES utf8mb4;',
        '',
    ];

    $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($views as $viewName) {
        $sqlOutput[] = 'DROP VIEW IF EXISTS ' . $quoteIdentifier((string) $viewName) . ';';
    }
    if ($views !== []) {
        $sqlOutput[] = '';
    }

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $tableName) {
        $tableName = (string) $tableName;
        $quotedTable = $quoteIdentifier($tableName);
        $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_ASSOC);
        $createSql = (string) ($createRow['Create Table'] ?? '');
        if ($createSql === '') {
            continue;
        }

        $sqlOutput[] = '-- Table: ' . $tableName;
        $sqlOutput[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';
        $sqlOutput[] = rtrim($createSql, " ;\t\r\n") . ';';

        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM ' . $quotedTable)->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[] = [
                'name' => (string) ($column['Field'] ?? ''),
                'type' => (string) ($column['Type'] ?? ''),
            ];
        }
        $columnList = implode(', ', array_map(static fn (array $column): string => $quoteIdentifier($column['name']), $columns));
        $rows = $pdo->query('SELECT * FROM ' . $quotedTable);
        $valueBatch = [];
        while (($row = $rows->fetch(PDO::FETCH_NUM)) !== false) {
            $values = [];
            foreach ($columns as $index => $column) {
                $values[] = $formatValue($row[$index] ?? null, $column['type']);
            }
            $valueBatch[] = '(' . implode(', ', $values) . ')';
            if (count($valueBatch) >= 50) {
                $sqlOutput[] = 'INSERT INTO ' . $quotedTable . ' (' . $columnList . ') VALUES ' . implode(",\n", $valueBatch) . ';';
                $valueBatch = [];
            }
        }
        if ($valueBatch !== []) {
            $sqlOutput[] = 'INSERT INTO ' . $quotedTable . ' (' . $columnList . ') VALUES ' . implode(",\n", $valueBatch) . ';';
        }

        $autoIncrement = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($tableName))->fetchColumn();
        if ($autoIncrement !== false && $autoIncrement !== null) {
            $sqlOutput[] = 'ALTER TABLE ' . $quotedTable . ' AUTO_INCREMENT = ' . (int) $autoIncrement . ';';
        }
        $sqlOutput[] = '';
    }

    foreach ($views as $viewName) {
        $quotedView = $quoteIdentifier((string) $viewName);
        $createRow = $pdo->query('SHOW CREATE VIEW ' . $quotedView)->fetch(PDO::FETCH_ASSOC);
        $createSql = (string) ($createRow['Create View'] ?? '');
        if ($createSql === '') {
            continue;
        }
        $sqlOutput[] = '-- View: ' . $viewName;
        $sqlOutput[] = 'DROP VIEW IF EXISTS ' . $quotedView . ';';
        $sqlOutput[] = rtrim($createSql, " ;\t\r\n") . ';';
        $sqlOutput[] = '';
    }

    $sqlOutput[] = 'SET FOREIGN_KEY_CHECKS = 1;';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="backup_' . gmdate('Y-m-d_H-i') . '.sql"');
echo implode("\n", $sqlOutput) . "\n";

} finally {
    $pdo = null;
}
