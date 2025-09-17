<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Version;

final class VersionRepository implements VersionRepositoryInterface
{
    public function __construct(private \wpdb $db) {}

    private function table(): string 
    { 
        return $this->db->prefix . 'minisite_versions'; 
    }

    public function save(Version $version): Version
    {
        $data = [
            'minisite_id' => $version->minisiteId,
            'version_number' => $version->versionNumber,
            'status' => $version->status,
            'label' => $version->label,
            'comment' => $version->comment,
            'data_json' => wp_json_encode($version->dataJson),
            'created_by' => $version->createdBy,
            'published_at' => $version->publishedAt?->format('Y-m-d H:i:s'),
            'source_version_id' => $version->sourceVersionId,
        ];

        $formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d'];

        if ($version->id === null) {
            // Insert new version
            $this->db->insert($this->table(), $data, $formats);
            $version->id = (int) $this->db->insert_id;
        } else {
            // Update existing version
            $this->db->update(
                $this->table(), 
                $data, 
                ['id' => $version->id], 
                $formats, 
                ['%d']
            );
        }

        return $version;
    }

    public function findById(int $id): ?Version
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);
        
        if (!$row) {
            return null;
        }

        return $this->mapRow($row);
    }

    public function findByMinisiteId(int $minisiteId, int $limit = 50, int $offset = 0): array
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d 
             ORDER BY version_number DESC 
             LIMIT %d OFFSET %d",
            $minisiteId, $limit, $offset
        );
        
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn($row) => $this->mapRow($row), $rows);
    }

    public function findLatestVersion(int $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d 
             ORDER BY version_number DESC 
             LIMIT 1",
            $minisiteId
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function findLatestDraft(int $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d AND status = 'draft' 
             ORDER BY version_number DESC 
             LIMIT 1",
            $minisiteId
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function findPublishedVersion(int $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d AND status = 'published' 
             LIMIT 1",
            $minisiteId
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function getNextVersionNumber(int $minisiteId): int
    {
        $sql = $this->db->prepare(
            "SELECT MAX(version_number) as max_version FROM {$this->table()} WHERE minisite_id = %d",
            $minisiteId
        );
        
        $result = $this->db->get_var($sql);
        return $result ? (int) $result + 1 : 1;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table(), ['id' => $id], ['%d']);
        return $result !== false;
    }

    private function mapRow(array $row): Version
    {
        return new Version(
            id: (int) $row['id'],
            minisiteId: (int) $row['minisite_id'],
            versionNumber: (int) $row['version_number'],
            status: $row['status'],
            label: $row['label'],
            comment: $row['comment'],
            dataJson: json_decode($row['data_json'], true) ?: [],
            createdBy: (int) $row['created_by'],
            createdAt: $row['created_at'] ? new \DateTimeImmutable($row['created_at']) : null,
            publishedAt: $row['published_at'] ? new \DateTimeImmutable($row['published_at']) : null,
            sourceVersionId: $row['source_version_id'] ? (int) $row['source_version_id'] : null
        );
    }
}
