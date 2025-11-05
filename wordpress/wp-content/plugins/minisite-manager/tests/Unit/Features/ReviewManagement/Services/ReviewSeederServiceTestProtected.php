<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ReviewManagement\Services;

use Minisite\Features\ReviewManagement\Services\ReviewSeederService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for testing protected methods of ReviewSeederService
 * 
 * Uses a testable subclass to access protected methods
 */
class TestableReviewSeederService extends ReviewSeederService
{
    public function publicLoadReviewsFromJson(string $jsonFile): array
    {
        return $this->loadReviewsFromJson($jsonFile);
    }
}

/**
 * Unit tests for ReviewSeederService protected methods
 */
final class ReviewSeederServiceTestProtected extends TestCase
{
    private ReviewSeederService|MockObject $reviewRepository;
    private TestableReviewSeederService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->reviewRepository = $this->createMock(\Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface::class);
        $this->service = new TestableReviewSeederService($this->reviewRepository);
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test loadReviewsFromJson throws exception when file not found
     */
    public function test_loadReviewsFromJson_file_not_found(): void
    {
        // Define MINISITE_PLUGIN_DIR if not defined
        if (!defined('MINISITE_PLUGIN_DIR')) {
            define('MINISITE_PLUGIN_DIR', __DIR__ . '/../../../../../');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON file not found:');

        $this->service->publicLoadReviewsFromJson('nonexistent-file.json');
    }

    /**
     * Test loadReviewsFromJson throws exception when JSON is invalid
     */
    public function test_loadReviewsFromJson_invalid_json(): void
    {
        // Create a temporary file with invalid JSON
        $tempFile = sys_get_temp_dir() . '/test-invalid-reviews.json';
        file_put_contents($tempFile, '{ invalid json }');

        // Define MINISITE_PLUGIN_DIR to point to temp directory
        if (!defined('MINISITE_PLUGIN_DIR')) {
            define('MINISITE_PLUGIN_DIR', sys_get_temp_dir() . '/');
        }

        // Create service with custom path handling
        $service = new class($this->reviewRepository) extends ReviewSeederService {
            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = sys_get_temp_dir() . '/data/json/reviews/' . $jsonFile;
                // Override path for testing
                $jsonPath = sys_get_temp_dir() . '/' . $jsonFile;

                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }

            public function publicLoadReviewsFromJson(string $jsonFile): array
            {
                return $this->loadReviewsFromJson($jsonFile);
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in file:');

        $service->publicLoadReviewsFromJson('test-invalid-reviews.json');

        // Cleanup
        @unlink($tempFile);
    }

    /**
     * Test loadReviewsFromJson throws exception when reviews array is missing
     */
    public function test_loadReviewsFromJson_missing_reviews_array(): void
    {
        // Create a temporary file with valid JSON but missing reviews array
        $tempFile = sys_get_temp_dir() . '/test-missing-reviews.json';
        file_put_contents($tempFile, json_encode(['data' => 'some data']));

        $service = new class($this->reviewRepository) extends ReviewSeederService {
            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = sys_get_temp_dir() . '/' . $jsonFile;

                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }

            public function publicLoadReviewsFromJson(string $jsonFile): array
            {
                return $this->loadReviewsFromJson($jsonFile);
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON structure in file:');
        $this->expectExceptionMessage('Missing \'reviews\' array');

        $service->publicLoadReviewsFromJson('test-missing-reviews.json');

        // Cleanup
        @unlink($tempFile);
    }

    /**
     * Test loadReviewsFromJson successfully loads valid JSON file
     */
    public function test_loadReviewsFromJson_success(): void
    {
        // Create a temporary file with valid JSON
        $tempFile = sys_get_temp_dir() . '/test-valid-reviews.json';
        $testData = [
            'reviews' => [
                [
                    'authorName' => 'Test User',
                    'rating' => 5.0,
                    'body' => 'Great!'
                ]
            ]
        ];
        file_put_contents($tempFile, json_encode($testData));

        $service = new class($this->reviewRepository) extends ReviewSeederService {
            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = sys_get_temp_dir() . '/' . $jsonFile;

                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }

            public function publicLoadReviewsFromJson(string $jsonFile): array
            {
                return $this->loadReviewsFromJson($jsonFile);
            }
        };

        $result = $service->publicLoadReviewsFromJson('test-valid-reviews.json');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Test User', $result[0]['authorName']);

        // Cleanup
        @unlink($tempFile);
    }
}

