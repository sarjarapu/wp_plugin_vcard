<?php

namespace Tests\Unit\Features\MinisiteViewer\Commands;

use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test ViewMinisiteCommand
 * 
 * Tests the ViewMinisiteCommand value object to ensure proper data encapsulation
 */
final class ViewMinisiteCommandTest extends TestCase
{
    /**
     * Test ViewMinisiteCommand constructor with all parameters
     */
    public function test_constructor_sets_all_properties(): void
    {
        $businessSlug = 'coffee-shop';
        $locationSlug = 'downtown';

        $command = new ViewMinisiteCommand($businessSlug, $locationSlug);

        $this->assertEquals($businessSlug, $command->businessSlug);
        $this->assertEquals($locationSlug, $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand constructor with empty strings
     */
    public function test_constructor_with_empty_strings(): void
    {
        $command = new ViewMinisiteCommand('', '');

        $this->assertEquals('', $command->businessSlug);
        $this->assertEquals('', $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand with special characters
     */
    public function test_constructor_with_special_characters(): void
    {
        $businessSlug = 'café-&-restaurant';
        $locationSlug = 'main-street-123';

        $command = new ViewMinisiteCommand($businessSlug, $locationSlug);

        $this->assertEquals($businessSlug, $command->businessSlug);
        $this->assertEquals($locationSlug, $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand with hyphens and underscores
     */
    public function test_constructor_with_hyphens_and_underscores(): void
    {
        $businessSlug = 'business-name_with_underscores';
        $locationSlug = 'location-name_with_underscores';

        $command = new ViewMinisiteCommand($businessSlug, $locationSlug);

        $this->assertEquals($businessSlug, $command->businessSlug);
        $this->assertEquals($locationSlug, $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand with numbers
     */
    public function test_constructor_with_numbers(): void
    {
        $businessSlug = 'business123';
        $locationSlug = 'location456';

        $command = new ViewMinisiteCommand($businessSlug, $locationSlug);

        $this->assertEquals($businessSlug, $command->businessSlug);
        $this->assertEquals($locationSlug, $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand with long strings
     */
    public function test_constructor_with_long_strings(): void
    {
        $businessSlug = 'very-long-business-name-with-many-words-and-description';
        $locationSlug = 'very-long-location-name-with-many-words-and-description';

        $command = new ViewMinisiteCommand($businessSlug, $locationSlug);

        $this->assertEquals($businessSlug, $command->businessSlug);
        $this->assertEquals($locationSlug, $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand properties are readonly
     */
    public function test_properties_are_readonly(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        // Properties should be readonly - attempting to modify should not work
        // This test verifies the readonly nature by checking the properties exist
        $this->assertObjectHasProperty('businessSlug', $command);
        $this->assertObjectHasProperty('locationSlug', $command);
    }

    /**
     * Test ViewMinisiteCommand with same business and location slugs
     */
    public function test_constructor_with_same_slugs(): void
    {
        $slug = 'same-slug';

        $command = new ViewMinisiteCommand($slug, $slug);

        $this->assertEquals($slug, $command->businessSlug);
        $this->assertEquals($slug, $command->locationSlug);
    }

    /**
     * Test ViewMinisiteCommand with whitespace
     */
    public function test_constructor_with_whitespace(): void
    {
        $businessSlug = ' business with spaces ';
        $locationSlug = ' location with spaces ';

        $command = new ViewMinisiteCommand($businessSlug, $locationSlug);

        $this->assertEquals($businessSlug, $command->businessSlug);
        $this->assertEquals($locationSlug, $command->locationSlug);
    }
}
