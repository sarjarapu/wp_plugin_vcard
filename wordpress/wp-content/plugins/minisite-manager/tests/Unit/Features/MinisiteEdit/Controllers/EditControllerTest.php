<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit\Controllers;

use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;
use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;

/**
 * Test EditController
 */
class EditControllerTest extends TestCase
{
    private EditController $controller;
    private $mockEditService;
    private $mockEditRenderer;
    private $mockWordPressManager;
    private $mockFormSecurityHelper;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->setupWordPressMocks();

        $this->mockEditService = $this->createMock(EditService::class);
        $this->mockEditRenderer = $this->createMock(EditRenderer::class);
        $this->mockWordPressManager = $this->createMock(WordPressEditManager::class);
        $this->mockFormSecurityHelper = $this->createMock(FormSecurityHelper::class);

        $this->controller = new EditController(
            $this->mockEditService,
            $this->mockEditRenderer,
            $this->mockWordPressManager,
            $this->mockFormSecurityHelper
        );
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testHandleEditUserNotLoggedIn(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', false);
        $this->mockWordPressFunction('wp_redirect', true);

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->mockWordPressManager->expects($this->once())
            ->method('getLoginRedirectUrl')
            ->willReturn('http://example.com/login');

        // Mock the redirect method to throw an exception in tests (simulating exit)
        $this->mockWordPressManager->method('redirect')
            ->willReturnCallback(function($url) {
                // Throw an exception to simulate exit behavior in tests
                throw new \Exception('Redirect called with URL: ' . $url);
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Redirect called with URL: http://example.com/login');
        
        $this->controller->handleEdit();
    }

    public function testHandleEditNoSiteId(): void
    {
        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->atMost(2))
            ->method('getQueryVar')
            ->with($this->logicalOr(
                $this->equalTo('minisite_id'),
                $this->equalTo('minisite_version_id')
            ))
            ->willReturnMap([
                ['minisite_id', '', ''],
                ['minisite_version_id', '', '']
            ]);

        $this->mockWordPressManager->expects($this->once())
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('http://example.com/account/sites');

        $this->mockWordPressManager->expects($this->once())
            ->method('redirect')
            ->with('http://example.com/account/sites');

        $this->controller->handleEdit();
    }

    public function testHandleEditFormSubmission(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['test' => 'data'];
        $siteId = '123';

        // Mock FormSecurityHelper to allow form submission
        $this->mockFormSecurityHelper->expects($this->once())
            ->method('verifyNonce')
            ->with('minisite_edit', 'minisite_edit_nonce')
            ->willReturn(true);

        // Mock getPostData calls for logging
        $this->mockFormSecurityHelper->expects($this->any())
            ->method('getPostData')
            ->willReturn('test_value');

        $this->mockFormSecurityHelper->expects($this->any())
            ->method('getPostDataTextarea')
            ->willReturn('test_textarea');

        $this->mockFormSecurityHelper->expects($this->any())
            ->method('getPostDataEmail')
            ->willReturn('test@example.com');

        $this->mockFormSecurityHelper->expects($this->any())
            ->method('isPostRequest')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', null]
            ]);

        $this->mockEditService->expects($this->once())
            ->method('saveDraft')
            ->with($siteId, $_POST)
            ->willReturn((object) ['success' => true, 'redirectUrl' => 'http://example.com/redirect']);

        $this->mockWordPressManager->expects($this->once())
            ->method('redirect')
            ->with('http://example.com/redirect');

        $this->controller->handleEdit();
    }

    public function testHandleEditFormSubmissionWithErrors(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['test' => 'data'];
        $siteId = '123';
        $errors = ['Error 1', 'Error 2'];

        // Mock FormSecurityHelper to allow form submission
        $this->mockFormSecurityHelper->expects($this->once())
            ->method('verifyNonce')
            ->with('minisite_edit', 'minisite_edit_nonce')
            ->willReturn(true);

        // Mock getPostData calls for logging
        $this->mockFormSecurityHelper->expects($this->any())
            ->method('getPostData')
            ->willReturn('test_value');

        $this->mockFormSecurityHelper->expects($this->any())
            ->method('getPostDataTextarea')
            ->willReturn('test_textarea');

        $this->mockFormSecurityHelper->expects($this->any())
            ->method('getPostDataEmail')
            ->willReturn('test@example.com');

        $this->mockFormSecurityHelper->expects($this->any())
            ->method('isPostRequest')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', 'latest']
            ]);

        $this->mockEditService->expects($this->once())
            ->method('saveDraft')
            ->with($siteId, $_POST)
            ->willReturn((object) ['success' => false, 'errors' => $errors]);

        $editData = (object) [
            'minisite' => (object) ['id' => $siteId],
            'editingVersion' => null,
            'latestDraft' => null,
            'profileForForm' => (object) ['name' => 'Test'],
            'siteJson' => [],
            'successMessage' => '',
            'errorMessage' => ''
        ];

        $this->mockEditService->expects($this->once())
            ->method('getMinisiteForEditing')
            ->with($siteId, null)
            ->willReturn($editData);

        $this->mockEditRenderer->expects($this->once())
            ->method('renderEditForm')
            ->with($this->callback(function ($data) use ($errors) {
                return $data->errorMessage === implode(', ', $errors);
            }));

        $this->controller->handleEdit();
    }

    public function testHandleEditDisplayForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $siteId = '123';
        $versionId = 'latest';

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', $versionId]
            ]);

        $editData = (object) [
            'minisite' => (object) ['id' => $siteId],
            'editingVersion' => null,
            'latestDraft' => null,
            'profileForForm' => (object) ['name' => 'Test'],
            'siteJson' => [],
            'successMessage' => '',
            'errorMessage' => ''
        ];

        $this->mockEditService->expects($this->once())
            ->method('getMinisiteForEditing')
            ->with($siteId, $versionId)
            ->willReturn($editData);

        $this->mockEditRenderer->expects($this->once())
            ->method('renderEditForm')
            ->with($editData);

        $this->controller->handleEdit();
    }

    public function testHandleEditAccessDenied(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $siteId = '123';

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', null]
            ]);

        $this->mockEditService->expects($this->once())
            ->method('getMinisiteForEditing')
            ->with($siteId, null)
            ->willThrowException(new \RuntimeException('Access denied'));

        $this->mockWordPressManager->expects($this->once())
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('http://example.com/account/sites');

        $this->mockWordPressManager->expects($this->once())
            ->method('redirect')
            ->with('http://example.com/account/sites');

        $this->controller->handleEdit();
    }

    public function testHandleEditMinisiteNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $siteId = '123';

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', null]
            ]);

        $this->mockEditService->expects($this->once())
            ->method('getMinisiteForEditing')
            ->with($siteId, null)
            ->willThrowException(new \RuntimeException('Minisite not found'));

        $this->mockWordPressManager->expects($this->once())
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('http://example.com/account/sites');

        $this->mockWordPressManager->expects($this->once())
            ->method('redirect')
            ->with('http://example.com/account/sites');

        $this->controller->handleEdit();
    }

    public function testHandleEditOtherException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $siteId = '123';
        $errorMessage = 'Database connection failed';

        $this->mockWordPressManager->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', null]
            ]);

        $this->mockEditService->expects($this->once())
            ->method('getMinisiteForEditing')
            ->with($siteId, null)
            ->willThrowException(new \RuntimeException($errorMessage));

        $this->mockEditRenderer->expects($this->once())
            ->method('renderError')
            ->with($errorMessage);

        $this->controller->handleEdit();
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = [
            'wp_redirect', 'is_user_logged_in', 'wp_get_current_user',
            'get_query_var', 'home_url', 'wp_verify_nonce', 'wp_create_nonce',
            'sanitize_text_field', 'sanitize_textarea_field'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                $code = "
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return null;
                    }
                ";
                eval($code);
            }
        }
        
        // Handle 'exit' separately since it's a language construct, not a function
        // In tests, we catch exceptions from redirect() instead
    }

    /**
     * Mock WordPress function for specific test cases
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = [
            'wp_redirect', 'is_user_logged_in', 'wp_get_current_user',
            'get_query_var', 'home_url', 'wp_verify_nonce', 'wp_create_nonce',
            'sanitize_text_field', 'sanitize_textarea_field'
        ];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
