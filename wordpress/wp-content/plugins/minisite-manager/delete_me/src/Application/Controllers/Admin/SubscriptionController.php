<?php

namespace Minisite\Application\Controllers\Admin;

use Minisite\Infrastructure\Utils\DatabaseHelper as db;

final class SubscriptionController
{
    /**
     * Handle the subscription management admin page
     */
    public function handleList(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Get pending orders with minisite data
        $pendingOrders   = $this->getPendingMinisiteOrders();
        $completedOrders = $this->getCompletedMinisiteOrders();

        // Render admin page
        $this->renderAdminPage(
            array(
                'pending_orders'   => $pendingOrders,
                'completed_orders' => $completedOrders,
            )
        );
    }

    /**
     * Handle manual subscription activation
     */
    public function handleActivateSubscription(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'activate_minisite_subscription_admin')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        if (! $orderId) {
            wp_send_json_error('Order ID required', 400);
            return;
        }

        try {
            global $wpdb;
            $profileRepo     = new \Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository($wpdb);
            $versionRepo     = new \Minisite\Infrastructure\Persistence\Repositories\VersionRepository($wpdb);
            $newMinisiteCtrl = new \Minisite\Application\Controllers\Front\NewMinisiteController(
                $profileRepo,
                $versionRepo
            );

            $newMinisiteCtrl->activateMinisiteSubscription($orderId);

            wp_send_json_success(array( 'message' => 'Subscription activated successfully' ));
        } catch (\Exception $e) {
            wp_send_json_error('Failed to activate subscription: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get pending orders with minisite data
     */
    private function getPendingMinisiteOrders(): array
    {
        global $wpdb;

        $orders = wc_get_orders(
            array(
                'status'     => array( 'pending', 'on-hold' ),
                'limit'      => 50,
                'meta_query' => array(
                    array(
                        'key'     => '_minisite_id',
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        $result = array();
        foreach ($orders as $order) {
            $minisiteId    = $order->get_meta('_minisite_id');
            $slug          = $order->get_meta('_slug');
            $reservationId = $order->get_meta('_reservation_id');

            $result[] = array(
                'order_id'       => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'total'          => $order->get_total(),
                'currency'       => $order->get_currency(),
                'status'         => $order->get_status(),
                'date_created'   => $order->get_date_created()->format('Y-m-d H:i:s'),
                'minisite_id'    => $minisiteId,
                'slug'           => $slug,
                'reservation_id' => $reservationId,
                'payment_method' => $order->get_payment_method_title(),
            );
        }

        return $result;
    }

    /**
     * Get completed orders with minisite data
     */
    private function getCompletedMinisiteOrders(): array
    {
        global $wpdb;

        $orders = wc_get_orders(
            array(
                'status'     => array( 'completed', 'processing' ),
                'limit'      => 20,
                'meta_query' => array(
                    array(
                        'key'     => '_minisite_id',
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        $result = array();
        foreach ($orders as $order) {
            $minisiteId = $order->get_meta('_minisite_id');
            $slug       = $order->get_meta('_slug');

            // Get subscription details
            $subscription = db::get_row(
                "SELECT * FROM {$wpdb->prefix}minisite_payments WHERE minisite_id = %s AND woocommerce_order_id = %d",
                [$minisiteId, $order->get_id()]
            );

            $result[] = array(
                'order_id'       => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'total'          => $order->get_total(),
                'currency'       => $order->get_currency(),
                'status'         => $order->get_status(),
                'date_created'   => $order->get_date_created()->format('Y-m-d H:i:s'),
                'minisite_id'    => $minisiteId,
                'slug'           => $slug,
                'subscription'   => $subscription,
            );
        }

        return $result;
    }

    /**
     * Render admin page using Twig template
     */
    private function renderAdminPage(array $data): void
    {
        // Use Timber renderer if available, otherwise fallback
        if (class_exists('Timber\\Timber')) {
            $base                      = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(
                array_unique(
                    array_merge(
                        \Timber\Timber::$locations ?? array(),
                        array( $base )
                    )
                )
            );

            \Timber\Timber::render(
                'admin-subscriptions.twig',
                array(
                    'page_title'       => 'Minisite Subscriptions',
                    'pending_orders'   => $data['pending_orders'],
                    'completed_orders' => $data['completed_orders'],
                    'nonce'            => wp_create_nonce('activate_minisite_subscription_admin'),
                )
            );
            return;
        }

        // Fallback: simple HTML (for development/testing)
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<title>Minisite Subscriptions</title>';
        echo '<h1>Minisite Subscriptions</h1>';
        echo '<p>Timber/Twig not available. Please install Timber plugin for proper rendering.</p>';
        echo '<pre>' . esc_html(print_r($data, true)) . '</pre>';
    }
}
