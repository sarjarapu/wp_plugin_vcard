<?php

namespace Minisite\Infrastructure\Utils;

final class DatabaseHelper
{
    /**
     * Same as $wpdb->get_var() - drop-in replacement
     */
    public static function get_var(string $sql, array $params = []): mixed
    {
        global $wpdb;
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Same as $wpdb->get_row() - drop-in replacement
     */
    public static function get_row(string $sql, array $params = []): mixed
    {
        global $wpdb;
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return $wpdb->get_row($sql, ARRAY_A);
    }
    
    /**
     * Same as $wpdb->get_results() - drop-in replacement
     */
    public static function get_results(string $sql, array $params = []): mixed
    {
        global $wpdb;
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Same as $wpdb->query() - drop-in replacement
     */
    public static function query(string $sql, array $params = []): mixed
    {
        global $wpdb;
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return $wpdb->query($sql);
    }
    
    /**
     * Same as $wpdb->insert() - drop-in replacement
     */
    public static function insert(string $table, array $data, array $format = []): mixed
    {
        global $wpdb;
        return $wpdb->insert($table, $data, $format);
    }
    
    /**
     * Same as $wpdb->update() - drop-in replacement
     */
    public static function update(string $table, array $data, array $where, array $format = [], array $where_format = []): mixed
    {
        global $wpdb;
        return $wpdb->update($table, $data, $where, $format, $where_format);
    }
    
    /**
     * Same as $wpdb->delete() - drop-in replacement
     */
    public static function delete(string $table, array $where, array $where_format = []): mixed
    {
        global $wpdb;
        return $wpdb->delete($table, $where, $where_format);
    }
}

