<?php

namespace Tests\Unit\Features\MinisiteDisplay\WordPress;

use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use PHPUnit\Framework\TestCase;

/**
 * Test WordPressMinisiteManager
 * 
 * Tests the WordPressMinisiteManager for proper WordPress integration
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressMinisiteManagerTest extends TestCase
{
    private WordPressMinisiteManager $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = new WordPressMinisiteManager();
    }

    /**
     * Test WordPressMinisiteManager can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(WordPressMinisiteManager::class, $this->wordPressManager);
    }

    /**
     * Test findMinisiteBySlugs method exists and is callable
     */
    public function test_find_minisite_by_slugs_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->wordPressManager, 'findMinisiteBySlugs'));
        $this->assertTrue(is_callable([$this->wordPressManager, 'findMinisiteBySlugs']));
    }

    /**
     * Test minisiteExists method exists and is callable
     */
    public function test_minisite_exists_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->wordPressManager, 'minisiteExists'));
        $this->assertTrue(is_callable([$this->wordPressManager, 'minisiteExists']));
    }

    /**
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $constructor = $reflection->getConstructor();
        
        // WordPressMinisiteManager uses PHP's default constructor (no explicit constructor)
        $this->assertNull($constructor);
    }

    /**
     * Test findMinisiteBySlugs method is public
     */
    public function test_find_minisite_by_slugs_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('findMinisiteBySlugs');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test minisiteExists method is public
     */
    public function test_minisite_exists_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('minisiteExists');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test findMinisiteBySlugs method parameter count
     */
    public function test_find_minisite_by_slugs_method_parameter_count(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('findMinisiteBySlugs');
        
        $this->assertEquals(2, $method->getNumberOfParameters());
    }

    /**
     * Test minisiteExists method parameter count
     */
    public function test_minisite_exists_method_parameter_count(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('minisiteExists');
        
        $this->assertEquals(2, $method->getNumberOfParameters());
    }

    /**
     * Test findMinisiteBySlugs method parameter types
     */
    public function test_find_minisite_by_slugs_method_parameter_types(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('findMinisiteBySlugs');
        $params = $method->getParameters();
        
        $this->assertCount(2, $params);
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('string', $params[1]->getType()->getName());
    }

    /**
     * Test minisiteExists method parameter types
     */
    public function test_minisite_exists_method_parameter_types(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('minisiteExists');
        $params = $method->getParameters();
        
        $this->assertCount(2, $params);
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('string', $params[1]->getType()->getName());
    }

    /**
     * Test findMinisiteBySlugs method return type
     */
    public function test_find_minisite_by_slugs_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('findMinisiteBySlugs');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertEquals('object', $returnType->getName()); // Object type
    }

    /**
     * Test minisiteExists method return type
     */
    public function test_minisite_exists_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('minisiteExists');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * Test WordPressMinisiteManager class has proper docblock
     */
    public function test_wordpress_minisite_manager_class_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $docComment = $reflection->getDocComment();
        
        $this->assertStringContainsString('WordPress Minisite Manager', $docComment);
    }

    /**
     * Test WordPressMinisiteManager class namespace
     */
    public function test_wordpress_minisite_manager_class_namespace(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        
        $this->assertEquals('Minisite\Features\MinisiteViewer\WordPress', $reflection->getNamespaceName());
    }

    /**
     * Test WordPressMinisiteManager class name
     */
    public function test_wordpress_minisite_manager_class_name(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        
        $this->assertEquals('WordPressMinisiteManager', $reflection->getShortName());
    }
}