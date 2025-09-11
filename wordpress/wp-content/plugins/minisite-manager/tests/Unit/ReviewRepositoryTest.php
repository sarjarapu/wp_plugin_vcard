<?php
namespace Tests\Unit;

use Minisite\Infrastructure\Persistence\Repositories\ReviewRepository;
use Minisite\Domain\Entities\Review;
use PHPUnit\Framework\TestCase;

final class ReviewRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testAddSetsInsertId(): void
    {
        $wpdb = $this->getMockBuilder(\wpdb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert'])
            ->getMock();

        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 55;
        $wpdb->method('insert')->willReturn(1);

        $repo = new ReviewRepository($wpdb);
        $review = new Review(null, 1, 'Jane', null, 4.5, 'Great!', 'en-US', '2025-09', 'manual', null, 'approved', null, null, 1);
        $saved = $repo->add($review);

        $this->assertSame(55, $saved->id);
    }
}