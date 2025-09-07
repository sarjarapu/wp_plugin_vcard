<?php
/**
 * Registration Modal Template
 * 
 * This template is automatically included in the footer for non-logged-in users
 * 
 * @package vCard
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only show for non-logged-in users
if (is_user_logged_in()) {
    return;
}
?>

<!-- Registration Modal will be created dynamically by JavaScript -->
<script type="text/javascript">
// Ensure the registration system is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof VCardUserRegistration !== 'undefined') {
        // Registration system is loaded
        console.log('vCard User Registration system loaded');
    } else {
        console.warn('vCard User Registration system not loaded');
    }
});
</script>

<!-- Registration trigger buttons (can be placed anywhere in templates) -->
<style>
.vcard-auth-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.vcard-auth-buttons.vcard-auth-inline {
    display: inline-flex;
}

.vcard-auth-buttons.vcard-auth-vertical {
    flex-direction: column;
}

.vcard-auth-buttons.vcard-auth-center {
    justify-content: center;
}

.vcard-auth-buttons.vcard-auth-right {
    justify-content: flex-end;
}

/* Hide auth buttons for logged-in users */
body.logged-in .vcard-auth-buttons {
    display: none;
}

/* Show user account info for logged-in users */
.vcard-user-account-info {
    display: none;
}

body.logged-in .vcard-user-account-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.vcard-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #007cba;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
}

.vcard-user-name {
    font-weight: 500;
    color: #333;
}

.vcard-user-menu {
    position: relative;
}

.vcard-user-menu-toggle {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.vcard-user-menu-toggle:hover {
    background: #f0f0f0;
    color: #333;
}

.vcard-user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 180px;
    z-index: 1000;
    display: none;
}

.vcard-user-dropdown.show {
    display: block;
}

.vcard-user-dropdown a {
    display: block;
    padding: 10px 16px;
    color: #333;
    text-decoration: none;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s ease;
}

.vcard-user-dropdown a:hover {
    background: #f8f9fa;
}

.vcard-user-dropdown a:last-child {
    border-bottom: none;
}

.vcard-user-dropdown a i {
    width: 16px;
    margin-right: 8px;
    color: #666;
}
</style>

<!-- Example usage in templates -->
<!--
<div class="vcard-auth-buttons vcard-auth-center">
    <button class="vcard-login-btn">
        <i class="fas fa-sign-in-alt"></i> Login
    </button>
    <button class="vcard-register-btn">
        <i class="fas fa-user-plus"></i> Register
    </button>
</div>

<div class="vcard-user-account-info">
    <div class="vcard-user-avatar">
        <?php echo esc_html(strtoupper(substr(wp_get_current_user()->display_name, 0, 1))); ?>
    </div>
    <span class="vcard-user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
    <div class="vcard-user-menu">
        <button class="vcard-user-menu-toggle">
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="vcard-user-dropdown">
            <a href="<?php echo esc_url(admin_url('profile.php')); ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="#" class="vcard-view-contacts-btn">
                <i class="fas fa-address-book"></i> My Contacts
            </a>
            <a href="<?php echo esc_url(wp_logout_url()); ?>">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>
-->

<script type="text/javascript">
// Simple user menu toggle
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.vcard-user-menu-toggle')) {
            e.preventDefault();
            var dropdown = e.target.closest('.vcard-user-menu').querySelector('.vcard-user-dropdown');
            dropdown.classList.toggle('show');
        } else if (!e.target.closest('.vcard-user-menu')) {
            // Close dropdown when clicking outside
            document.querySelectorAll('.vcard-user-dropdown.show').forEach(function(dropdown) {
                dropdown.classList.remove('show');
            });
        }
    });
});
</script>