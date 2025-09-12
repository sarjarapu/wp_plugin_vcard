<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRevisionRepository;
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
    
        $repo = new ProfileRevisionRepository($wpdb, 'wp_biz_profiles_revisions');
    
        $rev = new ProfileRevision(
            id: null,
            profileId: 12,
            revisionNumber: 3,
            status: 'draft',
            jsonData: ['a' => 1],
            createdAt: new \DateTimeImmutable('now')
        );
    
        $ok = $repo->add($rev);
    
        $this->assertTrue($ok);
        $this->assertSame(77, $wpdb->insert_id);
    }

    public function testListForProfileReturnsOrderedDesc(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->method('prepare')->willReturnCallback(fn($q, ...$a) => (new \wpdb)->prepare($q, ...$a));
    
        // If your repo maps rows → entities internally, you can still return rows here;
        // the repo should construct ProfileRevision objects from them.
        $wpdb->method('get_results')->willReturn([
            [
                'id' => 22, 'profile_id' => 12, 'revision_number' => 6,
                'status' => 'draft', 'json_data' => '{"x":2}', 'created_at' => '2025-01-02 00:00:00',
            ],
            [
                'id' => 21, 'profile_id' => 12, 'revision_number' => 5,
                'status' => 'published', 'json_data' => '{"x":1}', 'created_at' => '2025-01-01 00:00:00',
            ],
        ]);
    
        $repo = new ProfileRevisionRepository($wpdb, 'wp_biz_profiles_revisions');
        $rows = $repo->listForProfile(12, 10, 0);
    
        // Assert on entity API, not array access:
        $this->assertCount(2, $rows);
        $this->assertInstanceOf(ProfileRevision::class, $rows[0]);
        $this->assertSame(6, $rows[0]->revisionNumber());
        $this->assertSame(5, $rows[1]->revisionNumber());
    }
}
