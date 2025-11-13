<?php

namespace Minisite\Features\VersionManagement\Http;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionRequestHandler
 */
class VersionRequestHandlerTest extends TestCase
{
    private VersionRequestHandler $requestHandler;
    private MockObject $wordPressManager;
    private MockObject $formSecurityHelper;

    protected function setUp(): void
    {
        $this->wordPressManager = $this->createMock(WordPressVersionManager::class);
        $this->formSecurityHelper = $this->createMock(FormSecurityHelper::class);
        $this->requestHandler = new VersionRequestHandler($this->wordPressManager, $this->formSecurityHelper);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        // Clean up global variables
        unset($_SERVER['REQUEST_METHOD']);
        $_POST = array();
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
        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_id')
            ->willReturn('');

        $command = $this->requestHandler->parseListVersionsRequest();

        $this->assertNull($command);
    }

    public function test_parse_list_versions_request_returns_null_when_user_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_id')
            ->willReturn('test-site-123');

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $command = $this->requestHandler->parseListVersionsRequest();

        $this->assertNull($command);
    }

    public function test_parse_list_versions_request_returns_null_when_user_id_is_zero(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 0;

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_id')
            ->willReturn('test-site-123');

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

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

        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->atLeast(3))
            ->method('getPostData')
            ->willReturnMap([
                ['nonce', '', 'valid-nonce'],
                ['site_id', '', 'test-site-123'],
                ['label', '', 'Test Version'],
                ['version_comment', '', 'Test comment']
            ]);

        // Note: unslash is now handled by wp_unslash() directly, not through WordPressManager

        // Note: sanitizeTextareaField is now handled by helper functions, not directly called

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

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

        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->exactly(1))
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->formSecurityHelper
            ->expects($this->exactly(1))
            ->method('getPostDataInt')
            ->with('version_id', 0)
            ->willReturn(789);

        // Note: unslash is now handled by wp_unslash() directly, not through WordPressManager

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

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

        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->exactly(1))
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->formSecurityHelper
            ->expects($this->exactly(1))
            ->method('getPostDataInt')
            ->with('source_version_id', 0)
            ->willReturn(789);

        // Note: unslash is now handled by wp_unslash() directly, not through WordPressManager

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertInstanceOf(RollbackVersionCommand::class, $command);
        $this->assertEquals('test-site-123', $command->siteId);
        $this->assertEquals(789, $command->sourceVersionId);
        $this->assertEquals(456, $command->userId);
    }

    // ===== ERROR PATH TESTS =====

    public function test_parse_create_draft_request_returns_null_when_not_post(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $command = $this->requestHandler->parseCreateDraftRequest();

        $this->assertNull($command);
    }

    public function test_parse_create_draft_request_returns_null_when_get_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $command = $this->requestHandler->parseCreateDraftRequest();

        $this->assertNull($command);
    }

    public function test_parse_create_draft_request_returns_null_when_nonce_invalid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(false);

        $command = $this->requestHandler->parseCreateDraftRequest();

        $this->assertNull($command);
    }

    public function test_parse_create_draft_request_returns_null_when_site_id_missing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('');

        $command = $this->requestHandler->parseCreateDraftRequest();

        $this->assertNull($command);
    }

    public function test_parse_create_draft_request_returns_null_when_user_not_logged_in(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $command = $this->requestHandler->parseCreateDraftRequest();

        $this->assertNull($command);
    }

    public function test_parse_publish_version_request_returns_null_when_not_post(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $command = $this->requestHandler->parsePublishVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_publish_version_request_returns_null_when_nonce_invalid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(false);

        $command = $this->requestHandler->parsePublishVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_publish_version_request_returns_null_when_site_id_missing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('');

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostDataInt')
            ->with('version_id', 0)
            ->willReturn(789);

        $command = $this->requestHandler->parsePublishVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_publish_version_request_returns_null_when_version_id_missing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostDataInt')
            ->with('version_id', 0)
            ->willReturn(0);

        $command = $this->requestHandler->parsePublishVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_publish_version_request_returns_null_when_user_not_logged_in(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostDataInt')
            ->with('version_id', 0)
            ->willReturn(789);

        // User check happens after site_id and version_id validation
        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $command = $this->requestHandler->parsePublishVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_rollback_version_request_returns_null_when_not_post(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_rollback_version_request_returns_null_when_nonce_invalid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(false);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_rollback_version_request_returns_null_when_site_id_missing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('');

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostDataInt')
            ->with('source_version_id', 0)
            ->willReturn(789);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_rollback_version_request_returns_null_when_source_version_id_missing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostDataInt')
            ->with('source_version_id', 0)
            ->willReturn(0);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_rollback_version_request_returns_null_when_user_not_logged_in(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('site_id', '')
            ->willReturn('test-site-123');

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostDataInt')
            ->with('source_version_id', 0)
            ->willReturn(789);

        // User check happens after site_id and source_version_id validation
        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $command = $this->requestHandler->parseRollbackVersionRequest();

        $this->assertNull($command);
    }

    public function test_parse_create_draft_request_handles_missing_form_fields(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'nonce' => 'valid-nonce',
            'site_id' => 'test-site-123',
            // Missing label, version_comment, and all form fields
        ];

        $user = $this->createMock(\WP_User::class);
        $user->ID = 456;

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->atLeast(2))
            ->method('getPostData')
            ->willReturnMap([
                ['site_id', '', 'test-site-123'],
                ['label', '', ''],
                ['version_comment', '', ''],
            ]);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

        $command = $this->requestHandler->parseCreateDraftRequest();

        // Should still create command with empty/default values
        $this->assertInstanceOf(CreateDraftCommand::class, $command);
        $this->assertEquals('test-site-123', $command->siteId);
        $this->assertEquals(456, $command->userId);
        $this->assertEquals('', $command->label);
        $this->assertEquals('', $command->comment);
        // siteJson should have default structure even with missing fields
        $this->assertIsArray($command->siteJson);
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
