<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRevisionRepository;
use Minisite\Domain\Entities\ProfileRevision;
use PHPUnit\Framework\TestCase;

final class ProfileRevisionRepositoryTest extends TestCase
{
    private function mockWpdb(): \wpdb
    {
        $wpdb = $this->getMockBuilder(\wpdb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare','get_results','insert'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        return $wpdb;
    }

    public function testAddReturnsTrueAndSetsInsertId(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->method('insert')->willReturnCallback(function() use ($wpdb) {
            $wpdb->insert_id = 77;
            return 1;
        });
    
        $repo = new ProfileRevisionRepository($wpdb);
    
        $rev = new ProfileRevision(
            id: null,
            profileId: 12,
            revisionNumber: 3,
            status: 'draft',
            schemaVersion: 1,
            siteJson: ['a' => 1],
            createdAt: new \DateTimeImmutable('now'),
            createdBy: 9
        );
    
        $saved = $repo->add($rev);
        $this->assertInstanceOf(ProfileRevision::class, $saved);
        $this->assertSame(77, $saved->id);
    }

    public function testListForProfileReturnsOrderedDesc(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->method('prepare')->willReturnCallback(fn($q, ...$a) => (new \wpdb)->prepare($q, ...$a));
    
        // If your repo maps rows → entities internally, you can still return rows here;
        // the repo should construct ProfileRevision objects from them.
        $wpdb->method('get_results')->willReturn([
            [
                'id' => 22,
                'profile_id' => 12,
                'revision_number' => 6,
                'status' => 'draft',
                'schema_version' => 1,
                'site_json' => '{"x":2}',
                'created_at' => '2025-01-02 00:00:00',
                'created_by' => 9,
            ],
            [
                'id' => 21,
                'profile_id' => 12,
                'revision_number' => 5,
                'status' => 'published',
                'schema_version' => 1,
                'site_json' => '{"x":1}',
                'created_at' => '2025-01-01 00:00:00',
                'created_by' => 8,
            ],
        ]);
    
        $repo = new ProfileRevisionRepository($wpdb);
        $rows = $repo->listForProfile(12, 10);
    
        // Assert on entity API, not array access:
        $this->assertCount(2, $rows);
        $this->assertInstanceOf(ProfileRevision::class, $rows[0]);
        $this->assertSame(6, $rows[0]->revisionNumber);
        $this->assertSame(5, $rows[1]->revisionNumber);
    }
}
