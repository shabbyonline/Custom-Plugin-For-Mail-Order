/*
* Apply Mail Order Shipping rules
* Enforce minimum amount for mali order = $50
* Apply flat fee if cart total 50-199 will be charged $20
* Anything over 200 is free, exclusive of taxes
* Exclude Drinks category in mail order
* No tax on free shipping ($cart_total >= 200 OR under-$50 case where cost is hidden)
*/ 
add_filter('woocommerce_package_rates', function($rates, $package) {

    $mail_order_key = 'flat_rate:10';
    $cart_total = WC()->cart->get_displayed_subtotal();
    $restricted_category = 'drinks';
    $has_drinks = false;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (has_term($restricted_category, 'product_cat', $cart_item['product_id'])) {
            $has_drinks = true;
            break;
        }
    }

    if (isset($rates[$mail_order_key])) {

        if ($cart_total >= 200 || $cart_total < 50) {
            $rates[$mail_order_key]->cost = 0;
            $rates[$mail_order_key]->taxes = [];
            $rates[$mail_order_key]->tax_class = 'mail-order-free'; // IMPORTANT: No tax class
        } else {
            $rates[$mail_order_key]->cost = 20;
            $rates[$mail_order_key]->tax_class = ''; // Optional: remove tax from paid shipping too if you want
        }

        if ($has_drinks) {
            $rates[$mail_order_key]->label = 'Mail Order (Drinks not allowed)';
        } elseif ($cart_total < 50) {
            $rates[$mail_order_key]->label = 'Mail Order (Minimum order $50)';
        } elseif ($cart_total >= 200) {
            $rates[$mail_order_key]->label = 'Mail Order: Free';
        } else {
            $rates[$mail_order_key]->label = 'Mail Order Flat Fee';
        }
    }

    return $rates;
}, 10, 2);

add_action('woocommerce_checkout_process', function() {
    $chosen_shipping = WC()->session->get('chosen_shipping_methods');
    $cart_total = WC()->cart->get_displayed_subtotal();

    $restricted_category = 'drinks';
    $has_drinks = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (has_term($restricted_category, 'product_cat', $cart_item['product_id'])) {
            $has_drinks = true;
            break;
        }
    }

    if (isset($chosen_shipping[0]) && $chosen_shipping[0] === 'flat_rate:10') {
        if ($has_drinks) {
            wc_add_notice(__('Mail Order is not available for orders containing drinks.', 'woocommerce'), 'error');
        } elseif ($cart_total < 50) {
            wc_add_notice(__('Minimum order for Mail Order is $50.', 'woocommerce'), 'error');
        }
    }
});
add_filter('woocommerce_cart_shipping_method_full_label', function($label, $method) {
    // Only target Mail Order method
    if ($method->id === 'flat_rate:10') {

        // Check if cart has 'drinks' category products
        $has_drinks = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (has_term('drinks', 'product_cat', $cart_item['product_id'])) {
                $has_drinks = true;
                break;
            }
        }

        if ($has_drinks) {
            // Remove the price span
            $label = preg_replace('/<span class="woocommerce-Price-amount.*<\/span>/', '', $label);
            // Remove any trailing colon + spaces
            $label = preg_replace('/:\s*$/', '', trim($label));
        }
    }
    return $label;
}, 10, 2);
