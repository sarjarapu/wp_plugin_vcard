<?php

namespace Minisite\Features\MinisiteViewer\Http;

use Minisite\Features\MinisiteViewer\Commands\DisplayMinisiteCommand;

/**
 * Display Request Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests for minisite display
 * - Extracts and validates request data
 * - Creates command objects from HTTP requests
 * - Handles request validation and sanitization
 */
final class DisplayRequestHandler
{
    /**
     * Handle display request from URL parameters
     *
     * @return DisplayMinisiteCommand|null
     */
    public function handleDisplayRequest(): ?DisplayMinisiteCommand
    {
        $businessSlug = get_query_var('minisite_biz');
        $locationSlug = get_query_var('minisite_loc');

        if (!$businessSlug || !$locationSlug) {
            return null;
        }

        return new DisplayMinisiteCommand(
            sanitize_text_field($businessSlug),
            sanitize_text_field($locationSlug)
        );
    }

    /**
     * Get business slug from query vars
     *
     * @return string|null
     */
    public function getBusinessSlug(): ?string
    {
        $slug = get_query_var('minisite_biz');
        return $slug ? sanitize_text_field($slug) : null;
    }

    /**
     * Get location slug from query vars
     *
     * @return string|null
     */
    public function getLocationSlug(): ?string
    {
        $slug = get_query_var('minisite_loc');
        return $slug ? sanitize_text_field($slug) : null;
    }
}
