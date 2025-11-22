<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use Minisite\Infrastructure\Security\FormSecurityHelper;
use Minisite\Infrastructure\WordPress\Contracts\WordPressManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormSecurityHelper::class)]
final class FormSecurityHelperTest extends TestCase
{
    private WordPressManagerInterface|MockObject $manager;
    private FormSecurityHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->createMock(WordPressManagerInterface::class);
        $this->helper = new FormSecurityHelper($this->manager);
        $_POST = array();
        $_SERVER = array();
    }

    protected function tearDown(): void
    {
        $_POST = array();
        $_SERVER = array();
        parent::tearDown();
    }

    public function test_verify_nonce_returns_false_when_field_missing(): void
    {
        $this->assertFalse($this->helper->verifyNonce('action'));
    }

    public function test_verify_nonce_calls_wordpress_manager(): void
    {
        $_POST['custom_nonce'] = 'nonce-value';
        $this->manager->expects($this->once())
            ->method('sanitizeTextField')
            ->with('nonce-value')
            ->willReturn('nonce-value');

        $this->manager->expects($this->once())
            ->method('verifyNonce')
            ->with('nonce-value', 'my-action')
            ->willReturn(true);

        $this->assertTrue($this->helper->verifyNonce('my-action', 'custom_nonce'));
    }

    public function test_get_post_data_retrieves_and_sanitizes(): void
    {
        $_POST['field'] = ' value ';
        $this->manager->expects($this->once())
            ->method('sanitizeTextField')
            ->with(' value ')
            ->willReturn('value');

        $this->assertSame('value', $this->helper->getPostData('field'));
    }

    public function test_get_post_data_returns_default_when_missing(): void
    {
        $this->manager->expects($this->once())
            ->method('sanitizeTextField')
            ->with('default')
            ->willReturn('default');

        $this->assertSame('default', $this->helper->getPostData('field', 'default'));
    }

    public function test_get_post_data_int_converts_to_int(): void
    {
        $_POST['count'] = '42';
        $this->manager->method('sanitizeTextField')->willReturn('42');

        $this->assertSame(42, $this->helper->getPostDataInt('count'));
    }

    public function test_get_post_data_url_sanitizes(): void
    {
        $_POST['url'] = 'http://example.com';
        $this->manager->expects($this->once())
            ->method('sanitizeUrl')
            ->with('http://example.com')
            ->willReturn('http://example.com/');

        $this->assertSame('http://example.com/', $this->helper->getPostDataUrl('url'));
    }

    public function test_get_post_data_email_sanitizes(): void
    {
        $_POST['email'] = 'user@example.com';
        $this->manager->expects($this->once())
            ->method('sanitizeEmail')
            ->with('user@example.com')
            ->willReturn('user@example.com');

        $this->assertSame('user@example.com', $this->helper->getPostDataEmail('email'));
    }

    public function test_get_post_data_textarea_sanitizes(): void
    {
        $_POST['notes'] = "Line 1\nLine2";
        $this->manager->expects($this->once())
            ->method('sanitizeTextareaField')
            ->with("Line 1\nLine2")
            ->willReturn('clean');

        $this->assertSame('clean', $this->helper->getPostDataTextarea('notes'));
    }

    public function test_is_post_request_detects_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue($this->helper->isPostRequest());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertFalse($this->helper->isPostRequest());
    }

    public function test_is_valid_form_submission_checks_nonce_and_method(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['nonce'] = 'token';
        $this->manager->method('sanitizeTextField')->willReturn('token');
        $this->manager->method('verifyNonce')->with('token', 'action')->willReturn(true);

        $this->assertTrue($this->helper->isValidFormSubmission('action', 'nonce'));
    }
}
