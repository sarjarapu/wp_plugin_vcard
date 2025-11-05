<?php

namespace Minisite\Core;

/**
 * Feature Registry
 *
 * SINGLE RESPONSIBILITY: Manage feature registration and initialization
 * - Maintains list of available features
 * - Handles feature initialization
 * - Provides feature discovery and management
 */
final class FeatureRegistry
{
    private static array $features = array(
        \Minisite\Features\Authentication\AuthenticationFeature::class,
        \Minisite\Features\MinisiteViewer\MinisiteViewerFeature::class,
        \Minisite\Features\MinisiteListing\MinisiteListingFeature::class,
        \Minisite\Features\VersionManagement\VersionManagementFeature::class,
        \Minisite\Features\MinisiteEdit\MinisiteEditFeature::class,
        \Minisite\Features\NewMinisite\NewMinisiteFeature::class,
        \Minisite\Features\PublishMinisite\PublishMinisiteFeature::class,
        \Minisite\Features\ReviewManagement\ReviewManagementFeature::class,
        \Minisite\Features\ConfigurationManagement\ConfigurationManagementFeature::class,
        // Future features
        // \Minisite\Features\Settings\SettingsFeature::class,
        // \Minisite\Features\Commerce\CommerceFeature::class,
        // \Minisite\Features\Subscription\SubscriptionFeature::class,
    );

    public static function initializeAll(): void
    {
        foreach (self::$features as $featureClass) {
            if (class_exists($featureClass) && method_exists($featureClass, 'initialize')) {
                $featureClass::initialize();
            }
        }
    }

    public static function registerFeature(string $featureClass): void
    {
        if (! in_array($featureClass, self::$features)) {
            self::$features[] = $featureClass;
        }
    }

    public static function getFeatures(): array
    {
        return self::$features;
    }
}
