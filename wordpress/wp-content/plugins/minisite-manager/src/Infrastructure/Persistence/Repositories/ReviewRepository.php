<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Review;

final class ReviewRepository implements ReviewRepositoryInterface
{
    public function __construct(private \wpdb $db) {}
    private function table(): string { return $this->db->prefix . 'minisite_reviews'; }

    public function add(Review $r): Review
    {
        $this->db->insert($this->table(), [
            'minisite_id'   => $r->minisiteId,
            'author_name'   => $r->authorName,
            'author_url'    => $r->authorUrl,
            'rating'        => $r->rating,
            'body'          => $r->body,
            'locale'        => $r->locale,
            'visited_month' => $r->visitedMonth,
            'source'        => $r->source,
            'source_id'     => $r->sourceId,
            'status'        => $r->status,
            'created_at'    => $r->createdAt?->format('Y-m-d H:i:s'),
            'updated_at'    => $r->updatedAt?->format('Y-m-d H:i:s'),
            'created_by'    => $r->createdBy,
        ], ['%d','%s','%s','%f','%s','%s','%s','%s','%s','%s','%s','%s','%d']);

        $r->id = (int)$this->db->insert_id;
        return $r;
    }

    public function listApprovedForMinisite(string $minisiteId, int $limit = 20): array
    {
        $sql = $this->db->prepare(
            // AND status='approved' 
            "SELECT * FROM {$this->table()} WHERE minisite_id=%s ORDER BY created_at DESC LIMIT %d",
            $minisiteId, $limit
        );
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];
        return array_map(function(array $r) {
            return new Review(
                id:           (int)$r['id'],
                minisiteId:   (int)$r['minisite_id'],
                authorName:   $r['author_name'],
                authorUrl:    $r['author_url'] ?: null,
                rating:       (float)$r['rating'],
                body:         $r['body'],
                locale:       $r['locale'] ?: null,
                visitedMonth: $r['visited_month'] ?: null,
                source:       $r['source'],
                sourceId:     $r['source_id'] ?: null,
                status:       $r['status'],
                createdAt:    $r['created_at'] ? new \DateTimeImmutable($r['created_at']) : null,
                updatedAt:    $r['updated_at'] ? new \DateTimeImmutable($r['updated_at']) : null,
                createdBy:    $r['created_by'] ? (int)$r['created_by'] : null
            );
        }, $rows);
    }
}
