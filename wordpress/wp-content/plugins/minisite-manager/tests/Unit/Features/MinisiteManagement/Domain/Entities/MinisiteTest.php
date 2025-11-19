<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteManagement\Domain\Entities;

use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use PHPUnit\Framework\TestCase;

/**
 * Test Minisite Entity
 *
 * Tests the Minisite entity class methods and properties
 */
final class MinisiteTest extends TestCase
{
    /**
     * Test that Minisite can be instantiated with constructor parameters
     */
    public function test_can_be_instantiated_with_parameters(): void
    {
        $slugs = new SlugPair('test-business', 'test-location');
        $geo = new GeoPoint(40.7128, -74.0060);
        $now = new \DateTimeImmutable();

        $minisite = new Minisite(
            id: 'test-id-123',
            slug: 'test-slug',
            slugs: $slugs,
            title: 'Test Title',
            name: 'Test Name',
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: $geo,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array('test' => 'data'),
            searchTerms: 'test search',
            status: 'published',
            publishStatus: 'published',
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $now,
            createdBy: 1,
            updatedBy: 2,
            currentVersionId: 5
        );

        $this->assertInstanceOf(Minisite::class, $minisite);
        $this->assertEquals('test-id-123', $minisite->id);
        $this->assertEquals('test-slug', $minisite->slug);
        $this->assertEquals('test-business', $minisite->businessSlug);
        $this->assertEquals('test-location', $minisite->locationSlug);
        $this->assertEquals('Test Title', $minisite->title);
        $this->assertEquals('Test Name', $minisite->name);
        $this->assertEquals('Test City', $minisite->city);
        $this->assertEquals('Test Region', $minisite->region);
        $this->assertEquals('US', $minisite->countryCode);
        $this->assertEquals('12345', $minisite->postalCode);
        $this->assertNotNull($minisite->geo);
        $this->assertEquals($geo, $minisite->geo);
        $this->assertNotNull($minisite->slugs);
        $this->assertEquals($slugs, $minisite->slugs);
    }

    /**
     * Test that Minisite can be instantiated without parameters (for Doctrine)
     */
    public function test_can_be_instantiated_without_parameters(): void
    {
        $minisite = new Minisite();

        $this->assertInstanceOf(Minisite::class, $minisite);
        // Properties will be set by Doctrine or manually
    }

    /**
     * Test getSiteJsonAsArray method
     */
    public function test_get_site_json_as_array(): void
    {
        $minisite = new Minisite(
            id: 'test-id',
            name: 'Test',
            city: 'Test City',
            siteJson: array('key' => 'value', 'nested' => array('data' => 123))
        );

        $result = $minisite->getSiteJsonAsArray();

        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals(123, $result['nested']['data']);
    }

    /**
     * Test getSiteJsonAsArray with invalid JSON returns empty array
     */
    public function test_get_site_json_as_array_with_invalid_json(): void
    {
        $minisite = new Minisite();
        $minisite->siteJson = 'invalid json string';

        $result = $minisite->getSiteJsonAsArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getSiteJsonAsArray with empty JSON returns empty array
     */
    public function test_get_site_json_as_array_with_empty_json(): void
    {
        $minisite = new Minisite();
        $minisite->siteJson = '{}';

        $result = $minisite->getSiteJsonAsArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test setSiteJsonFromArray method
     */
    public function test_set_site_json_from_array(): void
    {
        $minisite = new Minisite();
        $data = array('key' => 'value', 'number' => 123, 'nested' => array('test' => true));

        $minisite->setSiteJsonFromArray($data);

        $this->assertIsString($minisite->siteJson);
        $decoded = json_decode($minisite->siteJson, true);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals(123, $decoded['number']);
        $this->assertTrue($decoded['nested']['test']);
    }

    /**
     * Test setSiteJsonFromArray with empty array
     */
    public function test_set_site_json_from_empty_array(): void
    {
        $minisite = new Minisite();

        $minisite->setSiteJsonFromArray(array());

        // json_encode([]) returns '[]', not '{}'
        $this->assertEquals('[]', $minisite->siteJson);
    }

    /**
     * Test getSlugs method when slugs property is set
     */
    public function test_get_slugs_when_slugs_property_set(): void
    {
        $slugs = new SlugPair('business', 'location');
        $minisite = new Minisite(
            id: 'test-id',
            name: 'Test',
            city: 'Test City',
            slugs: $slugs
        );

        $result = $minisite->getSlugs();

        $this->assertInstanceOf(SlugPair::class, $result);
        $this->assertEquals($slugs, $result);
    }

    /**
     * Test getSlugs method when slugs property is null but businessSlug and locationSlug are set
     */
    public function test_get_slugs_from_individual_slug_properties(): void
    {
        $minisite = new Minisite();
        $minisite->businessSlug = 'test-business';
        $minisite->locationSlug = 'test-location';

        $result = $minisite->getSlugs();

        $this->assertInstanceOf(SlugPair::class, $result);
        $this->assertEquals('test-business', $result->business);
        $this->assertEquals('test-location', $result->location);
    }

    /**
     * Test getSlugs method returns null when no slugs are available
     */
    public function test_get_slugs_returns_null_when_no_slugs(): void
    {
        $minisite = new Minisite();

        $result = $minisite->getSlugs();

        $this->assertNull($result);
    }

    /**
     * Test getSlugs method returns null when only one slug is set
     */
    public function test_get_slugs_returns_null_when_partial_slugs(): void
    {
        $minisite = new Minisite();
        $minisite->businessSlug = 'test-business';
        // locationSlug is null

        $result = $minisite->getSlugs();

        $this->assertNull($result);
    }

    /**
     * Test setSlugs method
     */
    public function test_set_slugs(): void
    {
        $minisite = new Minisite();
        $slugs = new SlugPair('new-business', 'new-location');

        $minisite->setSlugs($slugs);

        $this->assertEquals($slugs, $minisite->slugs);
        $this->assertEquals('new-business', $minisite->businessSlug);
        $this->assertEquals('new-location', $minisite->locationSlug);
    }

    /**
     * Test setSlugs method with null
     */
    public function test_set_slugs_with_null(): void
    {
        $minisite = new Minisite();
        $minisite->slugs = new SlugPair('old-business', 'old-location');
        $minisite->businessSlug = 'old-business';
        $minisite->locationSlug = 'old-location';

        $minisite->setSlugs(null);

        $this->assertNull($minisite->slugs);
        $this->assertNull($minisite->businessSlug);
        $this->assertNull($minisite->locationSlug);
    }

    /**
     * Test constructor with minimal required fields
     */
    public function test_constructor_with_minimal_fields(): void
    {
        $minisite = new Minisite(
            id: 'minimal-id',
            name: 'Minimal',
            city: 'City'
        );

        $this->assertEquals('minimal-id', $minisite->id);
        $this->assertEquals('Minimal', $minisite->name);
        $this->assertEquals('City', $minisite->city);
        $this->assertEquals('', $minisite->title); // Defaults to empty string
        $this->assertEquals('', $minisite->countryCode); // Defaults to empty string
        $this->assertEquals('v2025', $minisite->siteTemplate); // Default
        $this->assertEquals('blue', $minisite->palette); // Default
        $this->assertEquals('services', $minisite->industry); // Default
        $this->assertEquals('en-US', $minisite->defaultLocale); // Default
        $this->assertEquals(1, $minisite->schemaVersion); // Default
        $this->assertEquals(1, $minisite->siteVersion); // Default
        $this->assertEquals('published', $minisite->status); // Default
        $this->assertEquals('draft', $minisite->publishStatus); // Default
        $this->assertEquals('{}', $minisite->siteJson); // Default empty JSON
        $this->assertNotNull($minisite->createdAt);
        $this->assertNotNull($minisite->updatedAt);
    }

    /**
     * Test constructor with siteJson as array
     */
    public function test_constructor_with_site_json_array(): void
    {
        $jsonData = array('field1' => 'value1', 'field2' => array('nested' => 'data'));

        $minisite = new Minisite(
            id: 'test-id',
            name: 'Test',
            city: 'City',
            siteJson: $jsonData
        );

        $this->assertIsString($minisite->siteJson);
        $decoded = json_decode($minisite->siteJson, true);
        $this->assertEquals('value1', $decoded['field1']);
        $this->assertEquals('data', $decoded['field2']['nested']);
    }

    /**
     * Test constructor with null siteJson defaults to empty JSON
     */
    public function test_constructor_with_null_site_json(): void
    {
        $minisite = new Minisite(
            id: 'test-id',
            name: 'Test',
            city: 'City',
            siteJson: null
        );

        $this->assertEquals('{}', $minisite->siteJson);
    }
}

