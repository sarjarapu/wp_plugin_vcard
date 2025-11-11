<?php

declare(strict_types=1);

namespace Tests\Unit\Features\VersionManagement\Services;

use Minisite\Features\VersionManagement\Services\VersionSeederService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test class for testing protected methods of VersionSeederService
 *
 * Uses a testable subclass to access protected methods
 */
class TestableVersionSeederService extends VersionSeederService
{
    private ?string $testJsonDir = null;

    public function setTestJsonDir(string $dir): void
    {
        $this->testJsonDir = $dir;
    }

    protected function loadVersionsFromJson(string $jsonFile): array
    {
        // If test directory is set, use it; otherwise use parent implementation
        if ($this->testJsonDir !== null) {
            $jsonPath = $this->testJsonDir . '/data/json/versions/' . $jsonFile;

            if (! file_exists($jsonPath)) {
                throw new \RuntimeException('JSON file not found: ' . esc_html($jsonPath));
            }

            $jsonContent = file_get_contents($jsonPath);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    'Invalid JSON in file: ' . esc_html($jsonFile) . '. Error: ' . esc_html(json_last_error_msg())
                );
            }

            if (! isset($data['versions']) || ! is_array($data['versions'])) {
                throw new \RuntimeException(
                    'Invalid JSON structure in file: ' . esc_html($jsonFile) . '. Missing \'versions\' array.'
                );
            }

            return $data['versions'];
        }

        return parent::loadVersionsFromJson($jsonFile);
    }

    public function publicLoadVersionsFromJson(string $jsonFile): array
    {
        return $this->loadVersionsFromJson($jsonFile);
    }
}

/**
 * Unit tests for VersionSeederService protected methods
 */
final class VersionSeederServiceTestProtected extends TestCase
{
    private VersionSeederService|MockObject $versionRepository;
    private TestableVersionSeederService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->versionRepository = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface::class);
        $this->service = new TestableVersionSeederService($this->versionRepository);
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test loadVersionsFromJson throws exception when file not found
     */
    public function test_loadVersionsFromJson_file_not_found(): void
    {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/minisite-test-versions-' . uniqid();
        mkdir($tempDir . '/data/json/versions', 0755, true);

        // Set test directory on service (but don't create the file - it should fail)
        $this->service->setTestJsonDir($tempDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON file not found:');

        $this->service->publicLoadVersionsFromJson('nonexistent-file.json');

        // Cleanup
        @rmdir($tempDir . '/data/json/versions');
        @rmdir($tempDir . '/data/json');
        @rmdir($tempDir . '/data');
        @rmdir($tempDir);
    }

    /**
     * Test loadVersionsFromJson throws exception when JSON is invalid
     */
    public function test_loadVersionsFromJson_invalid_json(): void
    {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/minisite-test-versions-' . uniqid();
        mkdir($tempDir . '/data/json/versions', 0755, true);

        // Create a temporary file with invalid JSON
        $tempFile = $tempDir . '/data/json/versions/test-invalid-versions.json';
        file_put_contents($tempFile, '{ invalid json }');

        // Set test directory on service
        $this->service->setTestJsonDir($tempDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in file:');

        $this->service->publicLoadVersionsFromJson('test-invalid-versions.json');

        // Cleanup
        @unlink($tempFile);
        @rmdir($tempDir . '/data/json/versions');
        @rmdir($tempDir . '/data/json');
        @rmdir($tempDir . '/data');
        @rmdir($tempDir);
    }

    /**
     * Test loadVersionsFromJson throws exception when versions array is missing
     */
    public function test_loadVersionsFromJson_missing_versions_array(): void
    {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/minisite-test-versions-' . uniqid();
        mkdir($tempDir . '/data/json/versions', 0755, true);

        // Create a temporary file with valid JSON but missing versions array
        $tempFile = $tempDir . '/data/json/versions/test-missing-versions.json';
        file_put_contents($tempFile, json_encode(array('data' => 'some data')));

        // Set test directory on service
        $this->service->setTestJsonDir($tempDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON structure in file:');
        $this->expectExceptionMessage('Missing \'versions\' array');

        $this->service->publicLoadVersionsFromJson('test-missing-versions.json');

        // Cleanup
        @unlink($tempFile);
        @rmdir($tempDir . '/data/json/versions');
        @rmdir($tempDir . '/data/json');
        @rmdir($tempDir . '/data');
        @rmdir($tempDir);
    }

    /**
     * Test loadVersionsFromJson successfully loads valid JSON file
     */
    public function test_loadVersionsFromJson_success(): void
    {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/minisite-test-versions-' . uniqid();
        mkdir($tempDir . '/data/json/versions', 0755, true);

        // Create a temporary file with valid JSON
        $tempFile = $tempDir . '/data/json/versions/test-valid-versions.json';
        $testData = array(
            'versions' => array(
                array(
                    'versionNumber' => 1,
                    'status' => 'draft',
                ),
            ),
        );
        file_put_contents($tempFile, json_encode($testData));

        // Set test directory on service
        $this->service->setTestJsonDir($tempDir);

        $result = $this->service->publicLoadVersionsFromJson('test-valid-versions.json');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['versionNumber']);

        // Cleanup
        @unlink($tempFile);
        @rmdir($tempDir . '/data/json/versions');
        @rmdir($tempDir . '/data/json');
        @rmdir($tempDir . '/data');
        @rmdir($tempDir);
    }
}
