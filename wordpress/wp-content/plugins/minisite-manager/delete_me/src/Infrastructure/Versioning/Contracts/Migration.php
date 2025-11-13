<?php

/**
 * @deprecated This interface has been replaced by Doctrine migrations.
 * This file is archived in delete_me/ and will be removed in a future version.
 *
 * DO NOT USE THIS INTERFACE IN NEW CODE.
 */

namespace delete_me\Minisite\Infrastructure\Versioning\Contracts;

interface Migration
{
    /** Semantic version string, e.g. '1.0.0' */
    public function version(): string;

    /** Short human description (for logs) */
    public function description(): string;

    /** Apply this migration (must be idempotent). Throw on fatal. */
    public function up(): void;

    /** Optional rollback (best-effort, idempotent). */
    public function down(): void;
}
