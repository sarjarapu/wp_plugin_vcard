<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteViewer\ViewModels;

use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use PHPUnit\Framework\TestCase;

/**
 * Test MinisiteViewModel
 *
 * Tests the MinisiteViewModel DTO for proper data handling
 */
final class MinisiteViewModelTest extends TestCase
{
    /**
     * Helper to create a mock Minisite entity
     */
    private function createMockMinisite(string $id): Minisite
    {
        $minisite = $this->createMock(Minisite::class);
        $minisite->id = $id;
        $minisite->name = 'Test Minisite';
        $minisite->title = 'Test Title';

        return $minisite;
    }

    /**
     * Test constructor with all parameters
     */
    public function test_constructor_with_all_parameters(): void
    {
        $minisite = $this->createMockMinisite('test-123');
        $reviews = array(
            (object) array('id' => 1, 'rating' => 5.0),
            (object) array('id' => 2, 'rating' => 4.0),
        );

        $viewModel = new MinisiteViewModel(
            minisite: $minisite,
            reviews: $reviews,
            isBookmarked: true,
            canEdit: true
        );

        $this->assertSame($minisite, $viewModel->minisite);
        $this->assertEquals($reviews, $viewModel->reviews);
        $this->assertTrue($viewModel->isBookmarked);
        $this->assertTrue($viewModel->canEdit);
    }

    /**
     * Test constructor with default values
     */
    public function test_constructor_with_default_values(): void
    {
        $minisite = $this->createMockMinisite('test-456');

        $viewModel = new MinisiteViewModel(minisite: $minisite);

        $this->assertSame($minisite, $viewModel->minisite);
        $this->assertEmpty($viewModel->reviews);
        $this->assertFalse($viewModel->isBookmarked);
        $this->assertFalse($viewModel->canEdit);
    }

    /**
     * Test getMinisite returns minisite
     */
    public function test_get_minisite_returns_minisite(): void
    {
        $minisite = $this->createMockMinisite('test-789');
        $viewModel = new MinisiteViewModel(minisite: $minisite);

        $result = $viewModel->getMinisite();

        $this->assertSame($minisite, $result);
    }

    /**
     * Test getReviews returns reviews
     */
    public function test_get_reviews_returns_reviews(): void
    {
        $minisite = $this->createMockMinisite('test-reviews');
        $reviews = array(
            (object) array('id' => 1, 'rating' => 5.0),
        );

        $viewModel = new MinisiteViewModel(minisite: $minisite, reviews: $reviews);

        $result = $viewModel->getReviews();

        $this->assertEquals($reviews, $result);
        $this->assertCount(1, $result);
    }

    /**
     * Test getReviews returns empty array when no reviews
     */
    public function test_get_reviews_returns_empty_array_when_no_reviews(): void
    {
        $minisite = $this->createMockMinisite('test-no-reviews');
        $viewModel = new MinisiteViewModel(minisite: $minisite);

        $result = $viewModel->getReviews();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test isBookmarked returns true when bookmarked
     */
    public function test_is_bookmarked_returns_true_when_bookmarked(): void
    {
        $minisite = $this->createMockMinisite('test-bookmarked');
        $viewModel = new MinisiteViewModel(minisite: $minisite, isBookmarked: true);

        $result = $viewModel->isBookmarked();

        $this->assertTrue($result);
    }

    /**
     * Test isBookmarked returns false when not bookmarked
     */
    public function test_is_bookmarked_returns_false_when_not_bookmarked(): void
    {
        $minisite = $this->createMockMinisite('test-not-bookmarked');
        $viewModel = new MinisiteViewModel(minisite: $minisite, isBookmarked: false);

        $result = $viewModel->isBookmarked();

        $this->assertFalse($result);
    }

    /**
     * Test canEdit returns true when user can edit
     */
    public function test_can_edit_returns_true_when_user_can_edit(): void
    {
        $minisite = $this->createMockMinisite('test-can-edit');
        $viewModel = new MinisiteViewModel(minisite: $minisite, canEdit: true);

        $result = $viewModel->canEdit();

        $this->assertTrue($result);
    }

    /**
     * Test canEdit returns false when user cannot edit
     */
    public function test_can_edit_returns_false_when_user_cannot_edit(): void
    {
        $minisite = $this->createMockMinisite('test-cannot-edit');
        $viewModel = new MinisiteViewModel(minisite: $minisite, canEdit: false);

        $result = $viewModel->canEdit();

        $this->assertFalse($result);
    }

    /**
     * Test toArray returns array with minisite and reviews
     */
    public function test_to_array_returns_array_with_minisite_and_reviews(): void
    {
        $minisite = $this->createMockMinisite('test-to-array');
        $reviews = array(
            (object) array('id' => 1, 'rating' => 5.0),
        );

        $viewModel = new MinisiteViewModel(
            minisite: $minisite,
            reviews: $reviews,
            isBookmarked: true,
            canEdit: true
        );

        $result = $viewModel->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('minisite', $result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertSame($minisite, $result['minisite']);
        $this->assertEquals($reviews, $result['reviews']);
    }

    /**
     * Test toArray sets isBookmarked property on minisite
     */
    public function test_to_array_sets_is_bookmarked_property_on_minisite(): void
    {
        $minisite = $this->createMockMinisite('test-bookmarked-prop');
        $viewModel = new MinisiteViewModel(minisite: $minisite, isBookmarked: true);

        $result = $viewModel->toArray();

        $this->assertTrue($result['minisite']->isBookmarked);
    }

    /**
     * Test toArray sets canEdit property on minisite
     */
    public function test_to_array_sets_can_edit_property_on_minisite(): void
    {
        $minisite = $this->createMockMinisite('test-can-edit-prop');
        $viewModel = new MinisiteViewModel(minisite: $minisite, canEdit: true);

        $result = $viewModel->toArray();

        $this->assertTrue($result['minisite']->canEdit);
    }

    /**
     * Test toArray sets both properties on minisite
     */
    public function test_to_array_sets_both_properties_on_minisite(): void
    {
        $minisite = $this->createMockMinisite('test-both-props');
        $viewModel = new MinisiteViewModel(
            minisite: $minisite,
            isBookmarked: true,
            canEdit: true
        );

        $result = $viewModel->toArray();

        $this->assertTrue($result['minisite']->isBookmarked);
        $this->assertTrue($result['minisite']->canEdit);
    }

    /**
     * Test toArray with empty reviews
     */
    public function test_to_array_with_empty_reviews(): void
    {
        $minisite = $this->createMockMinisite('test-empty-reviews');
        $viewModel = new MinisiteViewModel(minisite: $minisite);

        $result = $viewModel->toArray();

        $this->assertIsArray($result['reviews']);
        $this->assertEmpty($result['reviews']);
    }
}

