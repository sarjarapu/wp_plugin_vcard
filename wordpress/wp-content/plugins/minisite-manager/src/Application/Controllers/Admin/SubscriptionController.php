<?php

namespace Minisite\Application\Controllers\Admin;

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

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (! wp_verify_nonce($_POST['nonce'] ?? '', 'activate_minisite_subscription_admin')) {
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
            $newMinisiteCtrl = new \Minisite\Application\Controllers\Front\NewMinisiteController($profileRepo, $versionRepo);

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
            $subscription = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}minisite_payments 
                 WHERE minisite_id = %s AND woocommerce_order_id = %d",
                    $minisiteId,
                    $order->get_id()
                )
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
     * Render admin page
     */
    private function renderAdminPage(array $data): void
    {
        ?>
        <div class="wrap">
            <h1>Minisite Subscriptions</h1>
            
            <!-- Pending Orders -->
            <div class="card">
                <h2>Pending Payments</h2>
                <p>Orders waiting for UPI payment verification:</p>
                
                <?php if (empty($data['pending_orders'])) : ?>
                    <p>No pending orders.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Minisite</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['pending_orders'] as $order) : ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo esc_html($order['order_number']); ?></strong>
                                        <br>
                                        <small>ID: <?php echo esc_html($order['order_id']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html($order['customer_name']); ?>
                                        <br>
                                        <small><?php echo esc_html($order['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html($order['currency'] . ' ' . $order['total']); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($order['slug']); ?></strong>
                                        <br>
                                        <small>ID: <?php echo esc_html($order['minisite_id']); ?></small>
                                    </td>
                                    <td><?php echo esc_html($order['payment_method']); ?></td>
                                    <td><?php echo esc_html($order['date_created']); ?></td>
                                    <td>
                                        <button 
                                            class="button button-primary activate-subscription-btn" 
                                            data-order-id="<?php echo esc_attr($order['order_id']); ?>"
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('activate_minisite_subscription_admin')); ?>"
                                        >
                                            Activate
                                        </button>
                                        <a 
                                            href="<?php echo esc_url(admin_url('post.php?post=' . $order['order_id'] . '&action=edit')); ?>" 
                                            class="button"
                                            target="_blank"
                                        >
                                            View Order
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Completed Orders -->
            <div class="card">
                <h2>Active Subscriptions</h2>
                <p>Recently completed orders with active subscriptions:</p>
                
                <?php if (empty($data['completed_orders'])) : ?>
                    <p>No completed orders.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Minisite</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['completed_orders'] as $order) : ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo esc_html($order['order_number']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html($order['customer_name']); ?>
                                        <br>
                                        <small><?php echo esc_html($order['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($order['slug']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($order['subscription']) : ?>
                                            <?php echo esc_html($order['subscription']->expires_at); ?>
                                        <?php else : ?>
                                            <em>No subscription data</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['subscription']) : ?>
                                            <span class="status-<?php echo esc_attr($order['subscription']->status); ?>">
                                                <?php echo esc_html(ucfirst($order['subscription']->status)); ?>
                                            </span>
                                        <?php else : ?>
                                            <em>Unknown</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a 
                                            href="<?php echo esc_url(admin_url('post.php?post=' . $order['order_id'] . '&action=edit')); ?>" 
                                            class="button"
                                            target="_blank"
                                        >
                                            View Order
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .status-active { color: green; font-weight: bold; }
            .status-expired { color: red; font-weight: bold; }
            .status-grace_period { color: orange; font-weight: bold; }
            .card { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; }
            .activate-subscription-btn { margin-right: 5px; }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle subscription activation
            document.querySelectorAll('.activate-subscription-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.dataset.orderId;
                    const nonce = this.dataset.nonce;
                    
                    if (!confirm('Are you sure you want to activate this subscription? This will make the minisite publicly accessible.')) {
                        return;
                    }
                    
                    this.disabled = true;
                    this.textContent = 'Activating...';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'activate_minisite_subscription_admin',
                            order_id: orderId,
                            nonce: nonce
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Subscription activated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.data || 'Failed to activate subscription'));
                            this.disabled = false;
                            this.textContent = 'Activate';
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error.message);
                        this.disabled = false;
                        this.textContent = 'Activate';
                    });
                });
            });
        });
        </script>
        <?php
    }
}
