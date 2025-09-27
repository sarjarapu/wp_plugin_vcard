<?php
namespace Tests\Support;

use PDO;

class FakeWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public int $rows_affected = 0;
    public int $insert_id = 0;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function prepare($query, ...$args)
    {
        foreach ($args as $a) {
            $query = preg_replace('/%[df]/', (string)(0 + $a), $query, 1);
            if (preg_match('/%s/', $query)) {
                $query = preg_replace('/%s/', "'" . addslashes((string)$a) . "'", $query, 1);
            }
        }
        return $query;
    }

    public function get_row($query, $output = null)
    {
        $stmt = $this->pdo->query($query);
        if (!$stmt) return null;
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function get_results($query, $output = null)
    {
        $stmt = $this->pdo->query($query);
        if (!$stmt) return [];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function query($query)
    {
        $result = $this->pdo->exec($query);
        $this->rows_affected = (int)($result === false ? 0 : $result);
        $this->insert_id = (int)$this->pdo->lastInsertId();
        return $result;
    }

    public function insert($table, $data, $format = [])
    {
        $cols = array_keys($data);
        $vals = array_values($data);
        $placeholders = array_map(fn($v) => is_numeric($v) ? (string)$v : "'" . addslashes((string)$v) . "'", $vals);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $res = $this->query($sql);
        return $res;
    }

    public function update($table, $data, $where, $format = [], $where_format = [])
    {
        $sets = [];
        foreach ($data as $k => $v) {
            $sets[] = sprintf("%s=%s", $k, is_numeric($v) ? (string)$v : "'" . addslashes((string)$v) . "'");
        }
        $conds = [];
        foreach ($where as $k => $v) {
            $conds[] = sprintf("%s=%s", $k, is_numeric($v) ? (string)$v : "'" . addslashes((string)$v) . "'");
        }
        $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE " . implode(' AND ', $conds);
        return $this->query($sql);
    }
}
