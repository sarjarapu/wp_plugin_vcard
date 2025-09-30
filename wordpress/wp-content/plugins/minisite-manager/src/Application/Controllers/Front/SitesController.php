<?php
namespace Minisite\Application\Controllers\Front;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

final class SitesController {

	public function __construct( private ?object $renderer = null ) {}

	public function handleList(): void {
		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url( '/account/login?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

		$currentUser = wp_get_current_user();

		global $wpdb;
		$repo = new MinisiteRepository( $wpdb );

		// TODO: add pagination and filters
		$sites = $repo->listByOwner( (int) $currentUser->ID, 50, 0 );

		$items = array_map(
			function ( $p ) {
				// Derive presentational fields for v1
				$route      = home_url( '/b/' . rawurlencode( $p->slugs->business ) . '/' . rawurlencode( $p->slugs->location ) );
				$statusChip = $p->status === 'published' ? 'Published' : 'Draft';
				return array(
					'id'           => $p->id,
					'title'        => $p->title ?: $p->name,
					'name'         => $p->name,
					'slugs'        => array(
						'business' => $p->slugs->business,
						'location' => $p->slugs->location,
					),
					'route'        => $route,
					'location'     => trim( $p->city . ( isset( $p->region ) && $p->region ? ', ' . $p->region : '' ) . ', ' . $p->countryCode, ', ' ),
					'status'       => $p->status,
					'status_chip'  => $statusChip,
					'updated_at'   => $p->updatedAt ? $p->updatedAt->format( 'Y-m-d H:i' ) : null,
					'published_at' => $p->publishedAt ? $p->publishedAt->format( 'Y-m-d H:i' ) : null,
					// TODO: real subscription and online flags
					'subscription' => 'Unknown',
					'online'       => 'Unknown',
				);
			},
			$sites
		);

		// Render via Timber directly for auth pages, keeping consistency with other account views
		if ( class_exists( 'Timber\\Timber' ) ) {
			$base                      = trailingslashit( MINISITE_PLUGIN_DIR ) . 'templates/timber/views';
			\Timber\Timber::$locations = array_values( array_unique( array_merge( \Timber\Timber::$locations ?? array(), array( $base ) ) ) );

			\Timber\Timber::render(
				'account-sites.twig',
				array(
					'page_title' => 'My Minisites',
					'sites'      => $items,
					'can_create' => current_user_can( MINISITE_CAP_CREATE ),
				)
			);
			return;
		}

		// Fallback minimal HTML
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><meta charset="utf-8"><h1>My Minisites</h1>';
		foreach ( $items as $it ) {
			echo '<div><a href="' . htmlspecialchars( $it['route'] ) . '">' . htmlspecialchars( $it['title'] ) . '</a> — ' . htmlspecialchars( $it['status_chip'] ) . '</div>';
		}
	}

	public function handleEdit(): void {
		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url( '/account/login?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

		$siteId = get_query_var( 'minisite_site_id' );
		if ( ! $siteId ) {
			wp_redirect( home_url( '/account/sites' ) );
			exit;
		}

		$currentUser = wp_get_current_user();
		global $wpdb;
		$minisiteRepo = new MinisiteRepository( $wpdb );
		$versionRepo  = new VersionRepository( $wpdb );

		$minisite = $minisiteRepo->findById( $siteId );
		if ( ! $minisite ) {
			wp_redirect( home_url( '/account/sites' ) );
			exit;
		}

		// Check ownership (v1: using created_by as owner surrogate)
		if ( $minisite->createdBy !== (int) $currentUser->ID ) {
			wp_redirect( home_url( '/account/sites' ) );
			exit;
		}

		$error_msg   = '';
		$success_msg = '';
		$latestDraft = null;

		// Check for success message from redirect
		if ( isset( $_GET['draft_saved'] ) && $_GET['draft_saved'] === '1' ) {
			$success_msg = 'Draft saved successfully!';
		}

		// Get version to edit (from URL parameter or latest draft)
		$requestedVersionId = get_query_var( 'minisite_version_id' );
		$latestDraft        = null;
		$editingVersion     = null;

		if ( $requestedVersionId === 'latest' || ! $requestedVersionId ) {
			// Edit latest version (draft or published) - use smart method
			$editingVersion = $versionRepo->getLatestDraftForEditing( $siteId );
			$latestDraft    = $versionRepo->findLatestDraft( $siteId );

		} else {
			// Edit specific version
			$editingVersion = $versionRepo->findById( (int) $requestedVersionId );
			if ( ! $editingVersion || $editingVersion->minisiteId !== $siteId ) {
				wp_redirect( home_url( '/account/sites/' . $siteId . '/edit' ) );
				exit;
			}
		}

		// Handle form submission
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['minisite_edit_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['minisite_edit_nonce'], 'minisite_edit' ) ) {
				$error_msg = 'Security check failed. Please try again.';
			} else {
				try {
					// Build siteJson from form data
					$siteJson = $this->buildSiteJsonFromForm( $_POST );

					// Handle coordinate fields separately
					$lat = ! empty( $_POST['contact_lat'] ) ? (float) $_POST['contact_lat'] : null;
					$lng = ! empty( $_POST['contact_lng'] ) ? (float) $_POST['contact_lng'] : null;

					// Start transaction for atomic draft save
					$wpdb->query( 'START TRANSACTION' );

					try {
						// Create new draft version
						$nextVersion = $versionRepo->getNextVersionNumber( $siteId );

						// Use existing profile slugs (slugs should not change during editing)
						$slugs = $minisite->slugs;

						// Create GeoPoint from form data
						$geo = null;
						if ( $lat !== null && $lng !== null ) {
							$geo = new \Minisite\Domain\ValueObjects\GeoPoint( lat: $lat, lng: $lng );
						}

						$version = new \Minisite\Domain\Entities\Version(
							id: null,
							minisiteId: $siteId,
							versionNumber: $nextVersion,
							status: 'draft',
							label: sanitize_text_field( $_POST['version_label'] ?? "Version {$nextVersion}" ),
							comment: sanitize_textarea_field( $_POST['version_comment'] ?? '' ),
							createdBy: (int) $currentUser->ID,
							createdAt: null,
							publishedAt: null,
							sourceVersionId: null,
							siteJson: $siteJson,
							// Profile fields from form data
							slugs: $slugs,
							title: sanitize_text_field( $_POST['seo_title'] ?? $minisite->title ),
							name: sanitize_text_field( $_POST['business_name'] ?? $minisite->name ),
							city: sanitize_text_field( $_POST['business_city'] ?? $minisite->city ),
							region: sanitize_text_field( $_POST['business_region'] ?? $minisite->region ),
							countryCode: sanitize_text_field( $_POST['business_country'] ?? $minisite->countryCode ),
							postalCode: sanitize_text_field( $_POST['business_postal'] ?? $minisite->postalCode ),
							geo: $geo,
							siteTemplate: sanitize_text_field( $_POST['site_template'] ?? $minisite->siteTemplate ),
							palette: sanitize_text_field( $_POST['brand_palette'] ?? $minisite->palette ),
							industry: sanitize_text_field( $_POST['brand_industry'] ?? $minisite->industry ),
							defaultLocale: sanitize_text_field( $_POST['default_locale'] ?? $minisite->defaultLocale ),
							schemaVersion: $minisite->schemaVersion,
							siteVersion: $minisite->siteVersion,
							searchTerms: sanitize_text_field( $_POST['search_terms'] ?? $minisite->searchTerms )
						);

						$savedVersion = $versionRepo->save( $version );
						$latestDraft  = $savedVersion;

						// Update profile coordinates if provided (coordinates are stored on profile, not in versions)
						if ( $lat !== null && $lng !== null ) {
							// Only update coordinates, not site_json
							$minisiteRepo->updateCoordinates( $siteId, $lat, $lng, (int) $currentUser->ID );
						}

						// Update profile title if provided
						$newTitle = sanitize_text_field( $_POST['seo_title'] ?? '' );
						if ( ! empty( $newTitle ) && $newTitle !== $minisite->title ) {
							$minisiteRepo->updateTitle( $siteId, $newTitle );
						}

						// Update other business info fields in main table
						$businessInfoFields = array(
							'name'           => sanitize_text_field( $_POST['business_name'] ?? $minisite->name ),
							'city'           => sanitize_text_field( $_POST['business_city'] ?? $minisite->city ),
							'region'         => sanitize_text_field( $_POST['business_region'] ?? $minisite->region ),
							'country_code'   => sanitize_text_field( $_POST['business_country'] ?? $minisite->countryCode ),
							'postal_code'    => sanitize_text_field( $_POST['business_postal'] ?? $minisite->postalCode ),
							'site_template'  => sanitize_text_field( $_POST['site_template'] ?? $minisite->siteTemplate ),
							'palette'        => sanitize_text_field( $_POST['brand_palette'] ?? $minisite->palette ),
							'industry'       => sanitize_text_field( $_POST['brand_industry'] ?? $minisite->industry ),
							'default_locale' => sanitize_text_field( $_POST['default_locale'] ?? $minisite->defaultLocale ),
							'search_terms'   => sanitize_text_field( $_POST['search_terms'] ?? $minisite->searchTerms ),
						);

						$minisiteRepo->updateBusinessInfo( $siteId, $businessInfoFields, (int) $currentUser->ID );

						$wpdb->query( 'COMMIT' );

						// Redirect to show the latest version after save
						wp_redirect( home_url( '/account/sites/' . $siteId . '/edit?draft_saved=1' ) );
						exit;

					} catch ( \Exception $e ) {
						$wpdb->query( 'ROLLBACK' );
						throw $e;
					}
				} catch ( \Exception $e ) {
					$error_msg = 'Failed to save draft: ' . $e->getMessage();
				}
			}
		}

		// Render edit form
		if ( class_exists( 'Timber\\Timber' ) ) {
			$viewsBase                 = trailingslashit( MINISITE_PLUGIN_DIR ) . 'templates/timber/views';
			$componentsBase            = trailingslashit( MINISITE_PLUGIN_DIR ) . 'templates/timber/components';
			\Timber\Timber::$locations = array_values( array_unique( array_merge( \Timber\Timber::$locations ?? array(), array( $viewsBase, $componentsBase ) ) ) );

			// Use editing version data (should always be available now)
			$siteJson = $editingVersion ? $editingVersion->siteJson : $minisite->siteJson;

			\Timber\Timber::render(
				'account-sites-edit.twig',
				array(
					'page_title'      => 'Edit: ' . $minisite->title,
					'profile'         => $minisite,
					'site_json'       => $siteJson,
					'latest_draft'    => $latestDraft,
					'editing_version' => $editingVersion,
					'error_msg'       => $error_msg,
					'success_msg'     => $success_msg,
					'minisite_id'     => $siteId,
					'minisite_status' => $minisite->status,
					'preview_url'     => $editingVersion ?
						home_url( '/account/sites/' . $siteId . '/preview/' . $editingVersion->id ) :
						home_url( '/account/sites/' . $siteId . '/preview/current' ),
					'versions_url'    => home_url( '/account/sites/' . $siteId . '/versions' ),
					'edit_latest_url' => home_url( '/account/sites/' . $siteId . '/edit/latest' ),
				)
			);
			return;
		}

		// Fallback
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><meta charset="utf-8"><h1>Edit: ' . htmlspecialchars( $minisite->title ) . '</h1>';
		echo '<p>Edit form not available (Timber required).</p>';
	}

	public function handlePreview(): void {
		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url( '/account/login?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

		$siteId    = get_query_var( 'minisite_site_id' );
		$versionId = get_query_var( 'minisite_version_id' );

		if ( ! $siteId ) {
			wp_redirect( home_url( '/account/sites' ) );
			exit;
		}

		$currentUser = wp_get_current_user();
		global $wpdb;
		$repo = new MinisiteRepository( $wpdb );

		$minisite = $repo->findById( $siteId );
		if ( ! $minisite ) {
			wp_redirect( home_url( '/account/sites' ) );
			exit;
		}

		// Check ownership (v1: using created_by as owner surrogate)
		if ( $minisite->createdBy !== (int) $currentUser->ID ) {
			wp_redirect( home_url( '/account/sites' ) );
			exit;
		}

		// Handle version-specific preview
		$versionRepo = new VersionRepository( $wpdb );
		$siteJson    = null;

		if ( $versionId === 'current' || ! $versionId ) {
			// Show current published version (from profile.siteJson)
			$siteJson = $minisite->siteJson;
		} else {
			// Show specific version
			$version = $versionRepo->findById( (int) $versionId );
			if ( ! $version || $version->minisiteId !== $siteId ) {
				wp_redirect( home_url( '/account/sites/' . $siteId . '/preview/current' ) );
				exit;
			}
			$siteJson = $version->siteJson;
		}

		// Update profile with version-specific data for rendering
		$minisite->siteJson = $siteJson;

		// Use the existing TimberRenderer to render the profile
		if ( class_exists( 'Timber\\Timber' ) && $this->renderer ) {
			$this->renderer->render( $minisite );
			return;
		}

		// Fallback: render using existing profile template
		if ( class_exists( 'Timber\\Timber' ) ) {
			$base                      = trailingslashit( MINISITE_PLUGIN_DIR ) . 'templates/timber/v2025';
			\Timber\Timber::$locations = array_values( array_unique( array_merge( \Timber\Timber::$locations ?? array(), array( $base ) ) ) );

			\Timber\Timber::render(
				'profile.twig',
				array(
					'profile' => $minisite,
				)
			);
			return;
		}

		// Final fallback
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><meta charset="utf-8"><h1>Preview: ' . htmlspecialchars( $minisite->title ) . '</h1>';
		echo '<p>Preview not available (Timber required).</p>';
	}

	private function buildSiteJsonFromForm( array $postData ): array {
		// Build siteJson structure from form data
		return array(
			'seo'      => array(
				'title'       => sanitize_text_field( $postData['seo_title'] ?? '' ),
				'description' => sanitize_textarea_field( $postData['seo_description'] ?? '' ),
				'keywords'    => sanitize_text_field( $postData['seo_keywords'] ?? '' ),
				'favicon'     => esc_url_raw( $postData['seo_favicon'] ?? '' ),
			),
			'brand'    => array(
				'name'     => sanitize_text_field( $postData['brand_name'] ?? '' ),
				'logo'     => esc_url_raw( $postData['brand_logo'] ?? '' ),
				'industry' => sanitize_text_field( $postData['brand_industry'] ?? '' ),
				'palette'  => sanitize_text_field( $postData['brand_palette'] ?? 'blue' ),
			),
			'hero'     => array(
				'badge'      => sanitize_text_field( $postData['hero_badge'] ?? '' ),
				'heading'    => sanitize_text_field( $postData['hero_heading'] ?? '' ),
				'subheading' => $this->sanitizeRichTextContent( $postData['hero_subheading'] ?? '' ),
				'image'      => esc_url_raw( $postData['hero_image'] ?? '' ),
				'imageAlt'   => sanitize_text_field( $postData['hero_image_alt'] ?? '' ),
				'ctas'       => array(
					array(
						'text' => sanitize_text_field( $postData['hero_cta1_text'] ?? '' ),
						'url'  => esc_url_raw( $postData['hero_cta1_url'] ?? '' ),
					),
					array(
						'text' => sanitize_text_field( $postData['hero_cta2_text'] ?? '' ),
						'url'  => esc_url_raw( $postData['hero_cta2_url'] ?? '' ),
					),
				),
				'rating'     => array(
					'value' => sanitize_text_field( $postData['hero_rating_value'] ?? '' ),
					'count' => sanitize_text_field( $postData['hero_rating_count'] ?? '' ),
				),
			),
			'whyUs'    => array(
				'title' => sanitize_text_field( $postData['whyus_title'] ?? '' ),
				'html'  => $this->sanitizeRichTextContent( $postData['whyus_html'] ?? '' ),
				'image' => esc_url_raw( $postData['whyus_image'] ?? '' ),
			),
			'about'    => array(
				'html' => $this->sanitizeRichTextContent( $postData['about_html'] ?? '' ),
			),
			'contact'  => array(
				'phone'         => array(
					'text' => sanitize_text_field( $postData['contact_phone_text'] ?? '' ),
					'link' => sanitize_text_field( $postData['contact_phone_link'] ?? '' ),
				),
				'whatsapp'      => array(
					'text' => sanitize_text_field( $postData['contact_whatsapp_text'] ?? '' ),
					'link' => sanitize_text_field( $postData['contact_whatsapp_link'] ?? '' ),
				),
				'email'         => sanitize_email( $postData['contact_email'] ?? '' ),
				'website'       => array(
					'text' => sanitize_text_field( $postData['contact_website_text'] ?? '' ),
					'link' => esc_url_raw( $postData['contact_website_link'] ?? '' ),
				),
				'address_line1' => sanitize_text_field( $postData['contact_address1'] ?? '' ),
				'address_line2' => sanitize_text_field( $postData['contact_address2'] ?? '' ),
				'address_line3' => sanitize_text_field( $postData['contact_address3'] ?? '' ),
				'address_line4' => sanitize_text_field( $postData['contact_address4'] ?? '' ),
				'plusCode'      => sanitize_text_field( $postData['contact_pluscode'] ?? '' ),
				'plusCodeUrl'   => esc_url_raw( $postData['contact_pluscode_url'] ?? '' ),
				'hours'         => $this->buildHoursFromForm( $postData ),
			),
			'services' => $this->buildServicesFromForm( $postData ),
			'social'   => $this->buildSocialFromForm( $postData ),
			'gallery'  => $this->buildGalleryFromForm( $postData ),
		);
	}

	private function buildServicesFromForm( array $postData ): array {
		$services     = array();
		$serviceCount = (int) ( $postData['product_count'] ?? 0 );

		for ( $i = 0; $i < $serviceCount; $i++ ) {
			$services[] = array(
				'title'       => sanitize_text_field( $postData[ "product_{$i}_title" ] ?? '' ),
				'image'       => esc_url_raw( $postData[ "product_{$i}_image" ] ?? '' ),
				'description' => $this->sanitizeRichTextContent( $postData[ "product_{$i}_description" ] ?? '' ),
				'price'       => sanitize_text_field( $postData[ "product_{$i}_price" ] ?? '' ),
				'icon'        => sanitize_text_field( $postData[ "product_{$i}_icon" ] ?? '' ),
				'cta'         => sanitize_text_field( $postData[ "product_{$i}_cta_text" ] ?? '' ),
				'url'         => esc_url_raw( $postData[ "product_{$i}_cta_url" ] ?? '' ),
			);
		}

		return array(
			'title'   => sanitize_text_field( $postData['products_section_title'] ?? 'Products & Services' ),
			'listing' => $services,
		);
	}

	private function buildSocialFromForm( array $postData ): array {
		$networks = array( 'facebook', 'instagram', 'x', 'youtube', 'linkedin', 'tiktok' );
		$social   = array();

		foreach ( $networks as $network ) {
			$url = esc_url_raw( $postData[ "social_{$network}" ] ?? '' );
			if ( ! empty( $url ) ) {
				$social[ $network ] = $url;
			}
		}

		return $social;
	}

	private function buildHoursFromForm( array $postData ): array {
		$days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$hours = array();

		foreach ( $days as $day ) {
			$isClosed  = ! empty( $postData[ "hours_{$day}_closed" ] );
			$openTime  = sanitize_text_field( $postData[ "hours_{$day}_open" ] ?? '' );
			$closeTime = sanitize_text_field( $postData[ "hours_{$day}_close" ] ?? '' );

			$dayName = ucfirst( $day );

			if ( $isClosed ) {
				$hours[ $dayName ] = array(
					'closed' => true,
				);
			} elseif ( ! empty( $openTime ) && ! empty( $closeTime ) ) {
				// Store times in 24-hour format for HTML time inputs
				$hours[ $dayName ] = array(
					'open'  => $openTime,
					'close' => $closeTime,
				);
			}
		}

		return $hours;
	}

	private function formatTime24To12( string $time24 ): string {
		if ( empty( $time24 ) ) {
			return '';
		}

		$time   = explode( ':', $time24 );
		$hour   = (int) $time[0];
		$minute = $time[1] ?? '00';

		$ampm   = $hour >= 12 ? 'PM' : 'AM';
		$hour12 = $hour % 12;
		if ( $hour12 === 0 ) {
			$hour12 = 12;
		}

		return sprintf( '%d:%s %s', $hour12, $minute, $ampm );
	}

	private function formatTime12To24( string $time12 ): string {
		if ( empty( $time12 ) ) {
			return '';
		}

		// Check if it's already in 24-hour format (contains no AM/PM)
		if ( ! preg_match( '/\b(AM|PM)\b/i', $time12 ) ) {
			return $time12;
		}

		// Parse 12-hour format
		$time            = trim( $time12 );
		$ampm            = strtoupper( substr( $time, -2 ) );
		$timeWithoutAmPm = trim( substr( $time, 0, -2 ) );

		$parts  = explode( ':', $timeWithoutAmPm );
		$hour   = (int) $parts[0];
		$minute = $parts[1] ?? '00';

		if ( $ampm === 'PM' && $hour !== 12 ) {
			$hour += 12;
		} elseif ( $ampm === 'AM' && $hour === 12 ) {
			$hour = 0;
		}

		return sprintf( '%02d:%s', $hour, $minute );
	}

	private function buildGalleryFromForm( array $postData ): array {
		$gallery    = array();
		$imageCount = (int) ( $postData['gallery_count'] ?? 0 );

		for ( $i = 0; $i < $imageCount; $i++ ) {
			$imageUrl = esc_url_raw( $postData[ "gallery_{$i}_image" ] ?? '' );
			$imageAlt = sanitize_text_field( $postData[ "gallery_{$i}_alt" ] ?? '' );
			if ( ! empty( $imageUrl ) ) {
				$gallery[] = array(
					'src'     => $imageUrl,
					'alt'     => $imageAlt,
					'caption' => $imageAlt, // Use alt as caption fallback
				);
			}
		}

		return $gallery;
	}

	/**
	 * Export minisite data as JSON
	 */
	public function handleExport(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not authenticated', 401 );
			return;
		}

		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			wp_send_json_error( 'Method not allowed', 405 );
			return;
		}

		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'export_minisite' ) ) {
			wp_send_json_error( 'Security check failed', 403 );
			return;
		}

		$minisiteId = sanitize_text_field( $_POST['minisite_id'] ?? '' );
		if ( empty( $minisiteId ) ) {
			wp_send_json_error( 'Missing minisite ID', 400 );
			return;
		}

		try {
			global $wpdb;
			$minisiteRepo = new MinisiteRepository( $wpdb );
			$versionRepo  = new VersionRepository( $wpdb );

			// Get the minisite for ownership check
			$minisite = $minisiteRepo->findById( $minisiteId );
			if ( ! $minisite ) {
				wp_send_json_error( 'Minisite not found', 404 );
				return;
			}

			// Check ownership
			if ( $minisite->createdBy !== get_current_user_id() ) {
				wp_send_json_error( 'Unauthorized', 403 );
				return;
			}

			// Get the latest draft version for export
			$latestVersion = $versionRepo->findLatestDraft( $minisiteId );
			if ( ! $latestVersion ) {
				// Fallback to latest version if no draft exists
				$latestVersion = $versionRepo->findLatestVersion( $minisiteId );
			}

			if ( ! $latestVersion ) {
				wp_send_json_error( 'No version found for export', 404 );
				return;
			}

			// Debug: Log version data being exported
			error_log(
				'EXPORT DEBUG - Version data: ' . print_r(
					array(
						'version_id'     => $latestVersion->id,
						'version_number' => $latestVersion->versionNumber,
						'status'         => $latestVersion->status,
						'title'          => $latestVersion->title,
						'name'           => $latestVersion->name,
						'city'           => $latestVersion->city,
						'region'         => $latestVersion->region,
						'countryCode'    => $latestVersion->countryCode,
						'postalCode'     => $latestVersion->postalCode,
						'siteTemplate'   => $latestVersion->siteTemplate,
						'palette'        => $latestVersion->palette,
						'industry'       => $latestVersion->industry,
						'defaultLocale'  => $latestVersion->defaultLocale,
						'searchTerms'    => $latestVersion->searchTerms,
					),
					true
				)
			);

			// Create export data from the latest version
			$exportData = array(
				'export_version' => '1.0',
				'exported_at'    => date( 'c' ),
				'minisite'       => array(
					'id'             => $minisite->id,
					'title'          => $latestVersion->title,
					'name'           => $latestVersion->name,
					'city'           => $latestVersion->city,
					'region'         => $latestVersion->region,
					'country_code'   => $latestVersion->countryCode,
					'postal_code'    => $latestVersion->postalCode,
					'location'       => $latestVersion->geo ? array(
						'lat' => $latestVersion->geo->lat,
						'lng' => $latestVersion->geo->lng,
					) : null,
					'site_template'  => $latestVersion->siteTemplate,
					'palette'        => $latestVersion->palette,
					'industry'       => $latestVersion->industry,
					'default_locale' => $latestVersion->defaultLocale,
					'search_terms'   => $latestVersion->searchTerms,
					'site_json'      => $latestVersion->siteJson,
				),
				'metadata'       => array(
					'original_created_at' => $minisite->createdAt?->format( 'c' ),
					'original_updated_at' => $minisite->updatedAt?->format( 'c' ),
					'exported_by'         => wp_get_current_user()->user_email,
				),
			);

			// Set headers for file download
			$filename = 'minisite-' . sanitize_file_name( $minisite->title ) . '-' . date( 'Y-m-d' ) . '.json';

			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Cache-Control: no-cache, must-revalidate' );

			echo json_encode( $exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			exit;

		} catch ( \Exception $e ) {
			wp_send_json_error( 'Export failed: ' . $e->getMessage(), 500 );
		}
	}

	/**
	 * Import minisite data from JSON
	 */
	public function handleImport(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not authenticated', 401 );
			return;
		}

		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			wp_send_json_error( 'Method not allowed', 405 );
			return;
		}

		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'import_minisite' ) ) {
			wp_send_json_error( 'Security check failed', 403 );
			return;
		}

		$jsonData = $_POST['json_data'] ?? '';
		if ( empty( $jsonData ) ) {
			wp_send_json_error( 'Missing JSON data', 400 );
			return;
		}

		try {
			// Log the received data for debugging
			error_log( 'Import JSON data length: ' . strlen( $jsonData ) );
			error_log( 'Import JSON data preview: ' . substr( $jsonData, 0, 200 ) );

			// The JSON data might be escaped due to FormData transmission
			// Try to decode it first, and if that fails, try with stripslashes
			$importData = json_decode( $jsonData, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Try with stripslashes to handle escaped quotes
				$jsonData = stripslashes( $jsonData );
				error_log( 'Trying with stripslashes, new preview: ' . substr( $jsonData, 0, 200 ) );
				$importData = json_decode( $jsonData, true );
			}
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( 'JSON decode error: ' . json_last_error_msg() );
				error_log( 'JSON data causing error: ' . substr( $jsonData, 0, 500 ) );
				wp_send_json_error( 'Invalid JSON format: ' . json_last_error_msg(), 400 );
				return;
			}

			// Validate export format
			if ( ! isset( $importData['export_version'] ) || ! isset( $importData['minisite'] ) ) {
				wp_send_json_error( 'Invalid export format', 400 );
				return;
			}

			$minisiteData = $importData['minisite'];

			// Debug: Log minisite data being imported
			error_log(
				'IMPORT DEBUG - Minisite data: ' . print_r(
					array(
						'title'          => $minisiteData['title'] ?? 'MISSING',
						'name'           => $minisiteData['name'] ?? 'MISSING',
						'city'           => $minisiteData['city'] ?? 'MISSING',
						'region'         => $minisiteData['region'] ?? 'MISSING',
						'country_code'   => $minisiteData['country_code'] ?? 'MISSING',
						'postal_code'    => $minisiteData['postal_code'] ?? 'MISSING',
						'site_template'  => $minisiteData['site_template'] ?? 'MISSING',
						'palette'        => $minisiteData['palette'] ?? 'MISSING',
						'industry'       => $minisiteData['industry'] ?? 'MISSING',
						'default_locale' => $minisiteData['default_locale'] ?? 'MISSING',
						'search_terms'   => $minisiteData['search_terms'] ?? 'MISSING',
					),
					true
				)
			);

			// Validate required fields
			$requiredFields = array( 'title', 'name', 'city', 'country_code', 'site_template', 'palette', 'industry', 'default_locale', 'search_terms', 'site_json' );
			foreach ( $requiredFields as $field ) {
				if ( ! isset( $minisiteData[ $field ] ) ) {
					wp_send_json_error( "Missing required field: {$field}", 400 );
					return;
				}
			}

			// Return parsed data for form population
			wp_send_json_success(
				array(
					'message'  => 'Import data validated successfully',
					'data'     => $minisiteData,
					'metadata' => $importData['metadata'] ?? array(),
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( 'Import failed: ' . $e->getMessage(), 500 );
		}
	}

	/**
	 * Sanitize rich text content without causing cascading escapes
	 */
	private function sanitizeRichTextContent( string $content ): string {
		// Remove any existing slashes that might have been added by previous saves
		$content = wp_unslash( $content );

		// Allow safe HTML tags for rich text content
		$allowedTags = array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'span'   => array(
				'class' => array(),
				'style' => array(),
			),
			'div'    => array(
				'class' => array(),
				'style' => array(),
			),
			'h1'     => array(
				'class' => array(),
				'style' => array(),
			),
			'h2'     => array(
				'class' => array(),
				'style' => array(),
			),
			'h3'     => array(
				'class' => array(),
				'style' => array(),
			),
			'h4'     => array(
				'class' => array(),
				'style' => array(),
			),
			'h5'     => array(
				'class' => array(),
				'style' => array(),
			),
			'h6'     => array(
				'class' => array(),
				'style' => array(),
			),
			'ul'     => array(
				'class' => array(),
				'style' => array(),
			),
			'ol'     => array(
				'class' => array(),
				'style' => array(),
			),
			'li'     => array(
				'class' => array(),
				'style' => array(),
			),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'class'  => array(),
				'style'  => array(),
			),
		);

		return wp_kses( $content, $allowedTags );
	}
}
