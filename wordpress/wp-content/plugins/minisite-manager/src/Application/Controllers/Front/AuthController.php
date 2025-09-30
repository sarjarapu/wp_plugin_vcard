<?php
namespace Minisite\Application\Controllers\Front;

use Minisite\Application\Rendering\TimberRenderer;

final class AuthController {

	public function __construct( private ?object $renderer = null ) {}

	public function handleLogin(): void {
		$error_msg   = '';
		$redirect_to = $_GET['redirect_to'] ?? home_url( '/account/dashboard' );

		// Handle login form submission
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['minisite_login_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['minisite_login_nonce'], 'minisite_login' ) ) {
				$error_msg = 'Security check failed. Please try again.';
			} else {
				$user_login = sanitize_text_field( $_POST['user_login'] ?? '' );
				$user_pass  = $_POST['user_pass'] ?? '';
				$remember   = isset( $_POST['remember'] );

				if ( empty( $user_login ) || empty( $user_pass ) ) {
					$error_msg = 'Please enter both username/email and password.';
				} else {
					$creds = array(
						'user_login'    => $user_login,
						'user_password' => $user_pass,
						'remember'      => $remember,
					);

					$user = wp_signon( $creds, false );
					if ( is_wp_error( $user ) ) {
						$error_msg = $user->get_error_message();
					} else {
						// Redirect to dashboard or intended page
						$redirect_to = sanitize_url( $_POST['redirect_to'] ?? home_url( '/account/dashboard' ) );
						wp_redirect( $redirect_to );
						exit;
					}
				}
			}
		}

		$this->renderAuthPage(
			'account-login.twig',
			array(
				'page_title'  => 'Sign In',
				'error_msg'   => $error_msg,
				'redirect_to' => $redirect_to,
			)
		);
	}

	public function handleRegister(): void {
		$error_msg   = '';
		$success_msg = '';

		// Handle registration form submission
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['minisite_register_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['minisite_register_nonce'], 'minisite_register' ) ) {
				$error_msg = 'Security check failed. Please try again.';
			} else {
				$user_login        = sanitize_text_field( $_POST['user_login'] ?? '' );
				$user_email        = sanitize_email( $_POST['user_email'] ?? '' );
				$user_pass         = $_POST['user_pass'] ?? '';
				$user_pass_confirm = $_POST['user_pass_confirm'] ?? '';

				if ( empty( $user_login ) || empty( $user_email ) || empty( $user_pass ) ) {
					$error_msg = 'Please fill in all required fields.';
				} elseif ( $user_pass !== $user_pass_confirm ) {
					$error_msg = 'Passwords do not match.';
				} elseif ( strlen( $user_pass ) < 6 ) {
					$error_msg = 'Password must be at least 6 characters long.';
				} else {
					$user_id = wp_create_user( $user_login, $user_pass, $user_email );
					if ( is_wp_error( $user_id ) ) {
						$error_msg = $user_id->get_error_message();
					} else {
						// Assign minisite_user role by default
						$user = new \WP_User( $user_id );
						$user->set_role( MINISITE_ROLE_USER );

						$success_msg = 'Account created successfully! You can now sign in.';
					}
				}
			}
		}

		$this->renderAuthPage(
			'account-register.twig',
			array(
				'page_title'  => 'Create Account',
				'error_msg'   => $error_msg,
				'success_msg' => $success_msg,
			)
		);
	}

	public function handleDashboard(): void {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url( '/account/login?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

		$user       = wp_get_current_user();
		$page_title = 'Dashboard';

		$this->renderAuthPage(
			'dashboard.twig',
			array(
				'page_title' => $page_title,
				'user'       => $user,
			)
		);
	}

	public function handleLogout(): void {
		wp_logout();
		wp_redirect( home_url( '/account/login' ) );
		exit;
	}

	public function handleForgotPassword(): void {
		$error_msg   = '';
		$success_msg = '';

		// Handle forgot password form submission
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['minisite_forgot_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['minisite_forgot_nonce'], 'minisite_forgot' ) ) {
				$error_msg = 'Security check failed. Please try again.';
			} else {
				$user_login = sanitize_text_field( $_POST['user_login'] ?? '' );

				if ( empty( $user_login ) ) {
					$error_msg = 'Please enter your username or email address.';
				} else {
					$result = retrieve_password( $user_login );
					if ( is_wp_error( $result ) ) {
						$error_msg = $result->get_error_message();
					} else {
						$success_msg = 'Check your email for the password reset link.';
					}
				}
			}
		}

		$this->renderAuthPage(
			'account-forgot.twig',
			array(
				'page_title'  => 'Reset Password',
				'error_msg'   => $error_msg,
				'success_msg' => $success_msg,
			)
		);
	}

	private function renderAuthPage( string $template, array $context = array() ): void {
		// Use Timber renderer if available, otherwise fallback
		if ( $this->renderer && method_exists( $this->renderer, 'renderAuthPage' ) ) {
			$this->renderer->renderAuthPage( $template, $context );
			return;
		}

		// Fallback: render using Timber directly
		if ( class_exists( 'Timber\\Timber' ) ) {
			$base                      = trailingslashit( MINISITE_PLUGIN_DIR ) . 'templates/timber/views';
			\Timber\Timber::$locations = array_values( array_unique( array_merge( \Timber\Timber::$locations ?? array(), array( $base ) ) ) );

			\Timber\Timber::render( $template, $context );
			return;
		}

		// Final fallback: simple HTML
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><meta charset="utf-8">';
		echo '<title>' . htmlspecialchars( $context['page_title'] ?? 'Account' ) . '</title>';
		echo '<h1>' . htmlspecialchars( $context['page_title'] ?? 'Account' ) . '</h1>';
		if ( ! empty( $context['error_msg'] ) ) {
			echo '<p style="color: red;">' . htmlspecialchars( $context['error_msg'] ) . '</p>';
		}
		if ( ! empty( $context['success_msg'] ) ) {
			echo '<p style="color: green;">' . htmlspecialchars( $context['success_msg'] ) . '</p>';
		}
	}
}
