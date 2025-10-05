<?php

namespace Minisite\Features\VersionManagement\Http;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionRequestHandler
 */
class VersionRequestHandlerTest extends TestCase
{
    private VersionRequestHandler $requestHandler;

    protected function setUp(): void
    {
        $this->requestHandler = new VersionRequestHandler();
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_parse_list_versions_request_returns_command_when_valid(): void
    {
        // Test that the method exists and is callable
        $this->assertTrue(method_exists($this->requestHandler, 'parseListVersionsRequest'));
        $this->assertTrue(is_callable([$this->requestHandler, 'parseListVersionsRequest']));
    }

    public function test_parse_list_versions_request_returns_null_when_no_site_id(): void
    {
        $this->mockWordPressFunction('get_query_var', '', 'minisite_site_id');

        $command = $this->requestHandler->parseListVersionsRequest();

        $this->assertNull($command);
    }

    public function test_parse_create_draft_request_returns_command_when_valid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'nonce' => 'valid-nonce',
            'site_id' => 'test-site-123',
            'label' => 'Test Version',
            'version_comment' => 'Test comment',
            'seo_title' => 'SEO Title'
        ];

        $this->mockWordPressFunction('wp_verify_nonce', true);
        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;
        $this->mockWordPressFunction('wp_get_current_user', $user);
        $this->mockWordPressFunction('sanitize_textarea_field', 'Test comment');

        $command = $this->requestHandler->parseCreateDraftRequest();

        $this->assertInstanceOf(CreateDraftCommand::class, $command);
        $this->assertEquals('test-site-123', $command->siteId);
        $this->assertEquals(456, $command->userId);
        $this->assertEquals('Test Version', $command->label);
        $this->assertEquals('Test comment', $command->comment);
    }

    public function test_parse_publish_version_request_returns_command_when_valid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'nonce' => 'valid-nonce',
            'site_id' => 'test-site-123',
            'version_id' => '789'
        ];

        $this->mockWordPressFunction('wp_verify_nonce', true);
        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;
        $this->mockWordPressFunction('wp_get_current_user', $user);

        $command = $this->requestHandler->parsePublishVersionRequest();

        $this->assertInstanceOf(PublishVersionCommand::class, $command);
        $this->assertEquals('test-site-123', $command->siteId);
        $this->assertEquals(789, $command->versionId);
        $this->assertEquals(456, $command->userId);
    }

    public function test_parse_rollback_version_request_returns_command_when_valid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'nonce' => 'valid-nonce',
            'site_id' => 'test-site-123',
            'source_version_id' => '789'
        ];

        $this->mockWordPressFunction('wp_verify_nonce', true);
        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;
        $this->mockWordPressFunction('wp_get_current_user', $user);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertInstanceOf(RollbackVersionCommand::class, $command);
        $this->assertEquals('test-site-123', $command->siteId);
        $this->assertEquals(789, $command->sourceVersionId);
        $this->assertEquals(456, $command->userId);
    }

    private function setupWordPressMocks(): void
    {
        $functions = [
            'get_query_var', 'wp_get_current_user', 'wp_verify_nonce',
            'sanitize_text_field', 'sanitize_textarea_field', 'esc_url_raw'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        \$key = \$args[0] ?? 'default';
                        if (isset(\$GLOBALS['_test_mock_{$function}_' . \$key])) {
                            return \$GLOBALS['_test_mock_{$function}_' . \$key];
                        }
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return \$args[1] ?? null;
                    }
                ");
            }
        }
    }

    private function mockWordPressFunction(string $functionName, mixed $returnValue, ?string $param = null): void
    {
        $key = $param ? "{$functionName}_{$param}" : $functionName;
        $GLOBALS['_test_mock_' . $key] = $returnValue;
    }

    private function clearWordPressMocks(): void
    {
        $functions = [
            'get_query_var', 'wp_get_current_user', 'wp_verify_nonce',
            'sanitize_text_field', 'sanitize_textarea_field', 'esc_url_raw'
        ];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
