<?php

namespace Minisite\Features\MinisiteViewer\Http;

/**
 * View Response Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP responses for minisite view
 * - Manages response headers and status codes
 * - Handles redirects and error responses
 * - Creates standardized response contexts
 */
class ViewResponseHandler
{
    /**
     * Set 404 response
     */
    public function set404Response(): void
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    /**
     * Create success context for rendering
     *
     * @param object $minisite
     * @return array
     */
    public function createSuccessContext(object $minisite): array
    {
        return array(
            'minisite' => $minisite,
            'page_title' => $minisite->name ?? 'Minisite',
            'success' => true,
        );
    }

    /**
     * Create error context for rendering
     *
     * @param string $errorMessage
     * @return array
     */
    public function createErrorContext(string $errorMessage): array
    {
        return array(
            'error_message' => $errorMessage,
            'page_title' => 'Minisite Not Found',
            'success' => false,
        );
    }

    /**
     * Set content type header
     *
     * @param string $contentType
     */
    public function setContentType(string $contentType = 'text/html; charset=utf-8'): void
    {
        header('Content-Type: ' . $contentType);
    }
}
