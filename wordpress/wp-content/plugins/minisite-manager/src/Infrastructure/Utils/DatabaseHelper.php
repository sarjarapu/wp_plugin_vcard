<?php

/**
 * @codingStandardsIgnoreFile
 * DatabaseHelper class - method names intentionally match $wpdb interface for drop-in replacement
 */

namespace Minisite\Infrastructure\Utils;

final class DatabaseHelper
{
    /**
     * Same as $wpdb->get_var() - drop-in replacement
     */
    // phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps -- Method name matches $wpdb interface
    public static function get_var(string $sql, array $params = array()): mixed
    {
        global $wpdb;

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL -- $sql is already prepared above, WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->get_var($sql);
    }

    /**
     * Same as $wpdb->get_row() - drop-in replacement
     */
    // phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps -- Method name matches $wpdb interface
    public static function get_row(string $sql, array $params = array()): mixed
    {
        global $wpdb;

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL -- $sql is already prepared above, WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->get_row($sql, ARRAY_A);
    }

    /**
     * Same as $wpdb->get_results() - drop-in replacement
     */
    // phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps -- Method name matches $wpdb interface
    public static function get_results(string $sql, array $params = array()): mixed
    {
        global $wpdb;

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL -- $sql is already prepared above, WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Same as $wpdb->query() - drop-in replacement
     */
    public static function query(string $sql, array $params = array()): mixed
    {
        global $wpdb;

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL -- $sql is already prepared above, WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->query($sql);
    }

    /**
     * Same as $wpdb->insert() - drop-in replacement
     */
    public static function insert(string $table, array $data, array $format = array()): mixed
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->insert($table, $data, $format);
    }

    /**
     * Same as $wpdb->update() - drop-in replacement
     */
    public static function update(
        string $table,
        array $data,
        array $where,
        array $format = array(),
        array $where_format = array()
    ): mixed {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->update($table, $data, $where, $format, $where_format);
    }

    /**
     * Same as $wpdb->delete() - drop-in replacement
     */
    public static function delete(string $table, array $where, array $where_format = array()): mixed
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- This is a database helper class
        return $wpdb->delete($table, $where, $where_format);
    }

    /**
     * Same as $wpdb->insert_id - drop-in replacement
     */
    // phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps -- Method name matches $wpdb interface
    public static function get_insert_id(): int
    {
        global $wpdb;

        return (int) $wpdb->insert_id;
    }

    /**
     * Get the global $wpdb object - useful for dependency injection
     */
    public static function getWpdb(): object
    {
        global $wpdb;

        return $wpdb;
    }
}
