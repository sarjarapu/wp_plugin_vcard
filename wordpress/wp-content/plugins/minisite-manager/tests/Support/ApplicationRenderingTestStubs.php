<?php

declare(strict_types=1);

namespace Minisite\Application\Rendering {
    if (! function_exists(__NAMESPACE__ . '\class_exists')) {
        /**
         * Allow tests to override class_exists checks within the Application\Rendering namespace.
         */
        function class_exists(string $class, bool $autoload = true): bool
        {
            if (isset($GLOBALS['_test_override_application_class_exists'])) {
                $override = $GLOBALS['_test_override_application_class_exists'];
                if (is_callable($override)) {
                    return (bool) $override($class, $autoload);
                }

                return (bool) $override;
            }

            return \class_exists($class, $autoload);
        }
    }

    if (! function_exists(__NAMESPACE__ . '\header')) {
        /**
         * Capture header() calls issued by TimberRenderer for assertions.
         */
        function header(string $header, bool $replace = true, int $http_response_code = 0): void
        {
            if (! isset($GLOBALS['_test_renderer_headers'])) {
                $GLOBALS['_test_renderer_headers'] = array();
            }

            $GLOBALS['_test_renderer_headers'][] = array(
                'header' => $header,
                'replace' => $replace,
                'response_code' => $http_response_code,
            );

            if (! empty($GLOBALS['_test_renderer_forward_headers'])) {
                \header($header, $replace, $http_response_code);
            }
        }
    }
}
