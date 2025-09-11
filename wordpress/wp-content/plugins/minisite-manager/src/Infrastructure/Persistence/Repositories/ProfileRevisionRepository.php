<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\ProfileRevision;

final class ProfileRevisionRepository implements ProfileRevisionRepositoryInterface
{
    public function __construct(private \wpdb $db) {}
    private function table(): string { return $this->db->prefix . 'minisite_profile_revisions'; }

    public function add(ProfileRevision $rev): ProfileRevision
    {
        $this->db->insert($this->table(), [
            'profile_id'      => $rev->profileId,
            'revision_number' => $rev->revisionNumber,
            'status'          => $rev->status,
            'schema_version'  => $rev->schemaVersion,
            'site_json'       => wp_json_encode($rev->siteJson),
            'created_by'      => $rev->createdBy,
        ], ['%d','%d','%s','%d','%s','%d']);

        $rev->id = (int)$this->db->insert_id;
        return $rev;
    }

    public function listForProfile(int $profileId, int $limit = 20): array
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE profile_id=%d ORDER BY revision_number DESC LIMIT %d",
            $profileId, $limit
        );
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];
        return array_map(function(array $r) {
            return new ProfileRevision(
                id:             (int)$r['id'],
                profileId:      (int)$r['profile_id'],
                revisionNumber: (int)$r['revision_number'],
                status:         $r['status'],
                schemaVersion:  (int)$r['schema_version'],
                siteJson:       json_decode((string)$r['site_json'], true) ?: [],
                createdAt:      $r['created_at'] ? new \DateTimeImmutable($r['created_at']) : null,
                createdBy:      $r['created_by'] ? (int)$r['created_by'] : null
            );
        }, $rows);
    }
}