<?php
/*
Plugin Name: Pre-Order for WooCommerce
Plugin URI: https://github.com/cornQ/wcq-preorder
Description: Allows products to be marked for pre-ordering. When enabled, a "Pre-Order" button appears on the product page and orders made using it are set to a custom "Pre-Ordered" status.
Version: 2.3
Requires at least: 6.7.2
Author: Md. Sohanur Rahman Sakib
Author URI: https://sakibsti.me/
License: GPLv3 or later
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add a Pre-Order checkbox and Pre-Order Stock Quantity field to the Inventory tab.
 */
function wcqpopskb_preorder_product_field() {
    wp_nonce_field( 'wcq_preorder_save', 'wcq_preorder_nonce' );
    woocommerce_wp_checkbox( array(
        'id'          => '_is_preorder',
        'label'       => __( 'Enable Pre-Order', 'wcq-preorder' ),
        'description' => __( 'Enable this product for pre-ordering.', 'wcq-preorder' ),
    ) );
    woocommerce_wp_text_input( array(
        'id'                => '_preorder_stock_quantity',
        'label'             => __( 'Pre-Order Stock Quantity', 'wcq-preorder' ),
        'description'       => __( 'Enter the quantity override for pre-order products. Leave blank to use default value (9999).', 'wcq-preorder' ),
        'desc_tip'          => true,
        'type'              => 'number',
        'custom_attributes' => array( 'min' => 0 ),
    ) );
}
add_action( 'woocommerce_product_options_inventory_product_data', 'wcqpopskb_preorder_product_field' );

/**
 * Save the Pre-Order fields.
 */
function wcqpopskb_preorder_save_product_field( $post_id ) {
    // Verify nonce for security
    if (
        ! isset( $_POST['wcq_preorder_nonce'] ) ||
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        ! wp_verify_nonce( wp_unslash( $_POST['wcq_preorder_nonce'] ), 'wcq_preorder_save' )
    ) {
        return;
    }

    // Check if user has permission to edit
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Save whether the product is marked as pre-order
    $is_preorder = isset( $_POST['_is_preorder'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_is_preorder', sanitize_text_field( $is_preorder ) );

    // Save pre-order stock quantity, if set
    if ( isset( $_POST['_preorder_stock_quantity'] ) ) {
        $stock_qty = sanitize_text_field( wp_unslash( $_POST['_preorder_stock_quantity'] ) );
        update_post_meta( $post_id, '_preorder_stock_quantity', $stock_qty );
    } else {
        delete_post_meta( $post_id, '_preorder_stock_quantity' );
    }
}
add_action( 'woocommerce_process_product_meta', 'wcqpopskb_preorder_save_product_field' );


/**
 * Change the Add to Cart button text to "Pre-Order" for pre-order products.
 */
function wcqpopskb_preorder_change_button_text( $text, $product ) {
    if ( 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        return __( 'Pre-Order', 'wcq-preorder' );
    }
    return $text;
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'wcqpopskb_preorder_change_button_text', 10, 2 );
add_filter( 'woocommerce_product_add_to_cart_text', 'wcqpopskb_preorder_change_button_text', 10, 2 );

/**
 * Allow pre-order products to be purchasable regardless of stock.
 */
function wcqpopskb_preorder_override_purchasable( $purchasable, $product ) {
    if ( 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        return true;
    }
    return $purchasable;
}
add_filter( 'woocommerce_is_purchasable', 'wcqpopskb_preorder_override_purchasable', 10, 2 );

/**
 * Force pre-order products to be treated as in stock.
 */
function wcqpopskb_preorder_force_in_stock( $in_stock, $product ) {
    if ( 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        return true;
    }
    return $in_stock;
}
add_filter( 'woocommerce_product_is_in_stock', 'wcqpopskb_preorder_force_in_stock', 10, 2 );

/**
 * Update availability text for pre-order products.
 */
function wcqpopskb_preorder_custom_availability_text( $availability, $product ) {
    if ( 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        $availability['availability'] = __( 'Pre-Order Available', 'wcq-preorder' );
    }
    return $availability;
}
add_filter( 'woocommerce_get_availability', 'wcqpopskb_preorder_custom_availability_text', 10, 2 );

/**
 * Register custom order status "Pre-Ordered".
 */
function wcqpopskb_register_preorder_status() {
    register_post_status( 'wc-pre-ordered', array(
        'label'                     => _x( 'Pre-Ordered', 'Order status', 'wcq-preorder' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        // translators: %s: number of products
        'label_count'               => _n_noop( 'Pre-Ordered <span class="count">(%s)</span>', 'Pre-Ordered <span class="count">(%s)</span>', 'wcq-preorder' ),
    ) );
}
add_action( 'init', 'wcqpopskb_register_preorder_status' );

/**
 * Add "Pre-Ordered" to the list of order statuses.
 */
function wcqpopskb_add_preorder_status( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-pre-ordered'] = _x( 'Pre-Ordered', 'Order status', 'wcq-preorder' );
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'wcqpopskb_add_preorder_status' );

/**
 * Set order status to "Pre-Ordered" if any pre-order product is in the order.
 */
function wcqpopskb_set_order_status_preorder( $order_id ) {
    if ( ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    $is_preorder_order = false;
    foreach ( $order->get_items() as $item ) {
        if ( 'yes' === get_post_meta( $item->get_product_id(), '_is_preorder', true ) ) {
            $is_preorder_order = true;
            break;
        }
    }
    if ( $is_preorder_order ) {
        $order->update_status( 'wc-pre-ordered', __( 'Order placed as pre-order.', 'wcq-preorder' ) );
    }
}
add_action( 'woocommerce_checkout_order_processed', 'wcqpopskb_set_order_status_preorder', 10, 1 );

function wcqpopskb_maybe_update_order_status_to_pre_ordered( $order_id ) {
    if ( ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( 'processing' !== $order->get_status() ) {
        return;
    }
    $is_preorder_order = false;
    foreach ( $order->get_items() as $item ) {
        if ( 'yes' === get_post_meta( $item->get_product_id(), '_is_preorder', true ) ) {
            $is_preorder_order = true;
            break;
        }
    }
    if ( $is_preorder_order ) {
        $order->update_status( 'wc-pre-ordered', __( 'Order placed as pre-order.', 'wcq-preorder' ) );
    }
}
add_action( 'woocommerce_thankyou', 'wcqpopskb_maybe_update_order_status_to_pre_ordered', 20, 1 );

/**
 * Redirect to the cart after adding a pre-order product.
 */
function wcqpopskb_preorder_redirect_to_cart( $url ) {
    if (
        isset( $_REQUEST['add-to-cart'], $_REQUEST['wcq_preorder_nonce'] ) &&
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        wp_verify_nonce( wp_unslash( $_REQUEST['wcq_preorder_nonce'] ), 'wcq_preorder_cart_redirect' )
    ) {
        $product_id = absint( $_REQUEST['add-to-cart'] );
        if ( 'yes' === get_post_meta( $product_id, '_is_preorder', true ) ) {
            return wc_get_cart_url();
        }
    }
    return $url;
}


/**
 * Disable AJAX add-to-cart for pre-order products.
 */
function wcqpopskb_preorder_inline_js() {
    if ( is_product() ) {
        global $product;
        if ( $product && 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($){
                $('.single_add_to_cart_button').removeClass('ajax_add_to_cart');
            });
            </script>
            <?php
        }
    }
}
add_action( 'wp_footer', 'wcqpopskb_preorder_inline_js' );

/**
 * Override stock quantity for pre-order products.
 */
function wcqpopskb_preorder_override_stock_quantity( $quantity, $product ) {
    if ( 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        $override = get_post_meta( $product->get_id(), '_preorder_stock_quantity', true );
        return ( '' !== $override && is_numeric( $override ) ) ? (int) $override : 9999;
    }
    return $quantity;
}
add_filter( 'woocommerce_product_get_stock_quantity', 'wcqpopskb_preorder_override_stock_quantity', 10, 2 );

/**
 * Add a warning message under the product title on the cart page if pre-ordered.
 */
function wcqpopskb_preorder_cart_warning( $item_name, $cart_item, $cart_item_key ) {
    $product = $cart_item['data'];
    if ( is_object( $product ) && 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        $item_name .= '<p style="color: red; font-weight: bold; margin: 5px 0 0;">' . esc_html__( 'Warning: This product is pre-ordered.', 'wcq-preorder' ) . '</p>';
    }
    return $item_name;
}
add_filter( 'woocommerce_cart_item_name', 'wcqpopskb_preorder_cart_warning', 10, 3 );

/**
 * Admin inline script to toggle the Pre-Order Stock Quantity field.
 */
function wcqpopskb_preorder_admin_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        function togglePreorderStockField() {
            if ($('#_is_preorder').is(':checked')) {
                $('#_preorder_stock_quantity_field').show();
            } else {
                $('#_preorder_stock_quantity_field').hide();
            }
        }
        togglePreorderStockField();
        $('#_is_preorder').change(function(){
            togglePreorderStockField();
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'wcqpopskb_preorder_admin_script' );

/**
 * Override stock checks on checkout for pre-order products.
 */
function wcqpopskb_preorder_override_stock_check( $has_enough, $product, $quantity ) {
    if ( 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        return true;
    }
    return $has_enough;
}
add_filter( 'woocommerce_product_has_enough_stock', 'wcqpopskb_preorder_override_stock_check', 10, 3 );
add_filter( 'woocommerce_variation_has_enough_stock', 'wcqpopskb_preorder_override_stock_check', 10, 3 );

/**
 * Allow backorders for pre-order products.
 */
function wcqpopskb_preorder_allow_backorders( $allowed, $product ) {
    if ( ! is_object( $product ) ) {
        $product = wc_get_product( $product );
    }
    if ( $product && 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        return true;
    }
    return $allowed;
}
add_filter( 'woocommerce_product_backorders_allowed', 'wcqpopskb_preorder_allow_backorders', 10, 2 );

/**
 * Display a note on the order details page for pre-ordered products.
 */
function wcqpopskb_preorder_order_item_meta( $item_id, $item, $order, $plain_text ) {
    $product = $item->get_product();
    if ( $product && 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
        if ( $plain_text ) {
            echo "\n" . esc_html__( 'This product is pre-ordered.', 'wcq-preorder' );
        } else {
            echo '<p style="color: red; font-weight: bold; margin: 0;">' . esc_html__( 'This product is pre-ordered.', 'wcq-preorder' ) . '</p>';
        }
    }
}
add_action( 'woocommerce_order_item_meta_end', 'wcqpopskb_preorder_order_item_meta', 10, 4 );

/**
 * Hide the "Buy Now" button and the "OR" wrapper on product pages if pre-order is enabled.
 */
function wcqpopskb_hide_buy_now_and_or_if_preorder() {
    if ( is_product() ) {
        global $product;
        if ( $product && 'yes' === get_post_meta( $product->get_id(), '_is_preorder', true ) ) {
            echo '<style>
                .et-single-buy-now,
                .et-or-wrapper { display: none !important; }
            </style>';
        }
    }
}
add_action( 'wp_head', 'wcqpopskb_hide_buy_now_and_or_if_preorder' );

?>