<?php

namespace Minisite\Infrastructure\Versioning\Contracts;

interface Migration
{
    /** Semantic version string, e.g. '1.0.0' */
    public function version(): string;

    /** Short human description (for logs) */
    public function description(): string;

    /** Apply this migration (must be idempotent). Throw on fatal. */
    public function up(\wpdb $wpdb): void;

    /** Optional rollback (best-effort, idempotent). */
    public function down(\wpdb $wpdb): void;
}
