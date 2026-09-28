<?php
/**
 * قیمت همکاران با نقش کاربر
 *
 * نقد:     ۴۰٪ از قیمت اصلی (قبل از تخفیف مشتری)
 * شرایط:   ۳۰٪ از قیمت اصلی (قبل از تخفیف مشتری)
 *
 * اگر محصول روی فروش (Sale) باشد، درصد همکاران
 * از regular_price کم می‌شود نه از sale_price.
 *
 * نقش‌های کاربر:
 *   hamkar_naghd  → همکار نقد
 *   hamkar_shart  → همکار شرایط
 *
 * این فایل را در functions.php قالب یا افزونه Code Snippets قرار دهید.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HAMKAR_NAGHD_PERCENT', 40);
define('HAMKAR_SHART_PERCENT', 30);

/**
 * درصد تخفیف همکار برای کاربر فعلی.
 * اگر کاربر همکار نباشد null برمی‌گرداند.
 */
function hamkar_get_discount_percent($user_id = 0)
{
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
    if (!$user || empty($user->roles)) {
        return null;
    }

    if (in_array('hamkar_naghd', (array) $user->roles, true)) {
        return HAMKAR_NAGHD_PERCENT;
    }

    if (in_array('hamkar_shart', (array) $user->roles, true)) {
        return HAMKAR_SHART_PERCENT;
    }

    return null;
}

/**
 * قیمت اصلی محصول — همیشه قبل از تخفیف مشتری.
 * context=edit تا فیلتر قیمت همکار دوباره روی آن اعمال نشود.
 */
function hamkar_get_original_price($product)
{
    if (!$product instanceof WC_Product) {
        return 0;
    }

    $regular = $product->get_regular_price('edit');

    if ($regular === '' || $regular === null) {
        $regular = get_post_meta($product->get_id(), '_regular_price', true);
    }

    if ($regular === '' || $regular === null) {
        $regular = $product->get_price('edit');
    }

    return (float) $regular;
}

/**
 * قیمت همکار از روی قیمت اصلی.
 */
function hamkar_calc_price($original_price, $percent)
{
    $original_price = (float) $original_price;
    $percent        = (float) $percent;

    if ($original_price <= 0 || $percent <= 0) {
        return $original_price;
    }

    return $original_price * (1 - ($percent / 100));
}

/**
 * جایگزینی قیمت محصول برای همکاران در فروشگاه، سبد و تسویه.
 */
function hamkar_filter_product_price($price, $product)
{
    $percent = hamkar_get_discount_percent();
    if ($percent === null) {
        return $price;
    }

    $original = hamkar_get_original_price($product);
    if ($original <= 0) {
        return $price;
    }

    return hamkar_calc_price($original, $percent);
}

add_filter('woocommerce_product_get_price', 'hamkar_filter_product_price', 99, 2);
add_filter('woocommerce_product_get_regular_price', 'hamkar_filter_product_price', 99, 2);
add_filter('woocommerce_product_variation_get_price', 'hamkar_filter_product_price', 99, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'hamkar_filter_product_price', 99, 2);
add_filter('woocommerce_product_get_sale_price', 'hamkar_filter_product_price', 99, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'hamkar_filter_product_price', 99, 2);

/**
 * قیمت متغیرها در آرایه variation هم باید از قیمت اصلی حساب شود.
 */
function hamkar_filter_variation_prices($prices, $product, $for_display)
{
    $percent = hamkar_get_discount_percent();
    if ($percent === null) {
        return $prices;
    }

    foreach (['price', 'regular_price', 'sale_price'] as $key) {
        if (empty($prices[$key]) || !is_array($prices[$key])) {
            continue;
        }

        foreach ($prices[$key] as $variation_id => $value) {
            $variation = wc_get_product($variation_id);
            $original  = $variation ? hamkar_get_original_price($variation) : (float) $value;
            $prices[$key][$variation_id] = (string) hamkar_calc_price($original, $percent);
        }
    }

    return $prices;
}

add_filter('woocommerce_variation_prices', 'hamkar_filter_variation_prices', 99, 3);

/**
 * ثبت نقش‌های همکار (یک‌بار پس از فعال‌سازی).
 */
function hamkar_register_roles()
{
    if (!get_role('hamkar_naghd')) {
        add_role('hamkar_naghd', 'همکار نقد', ['read' => true]);
    }

    if (!get_role('hamkar_shart')) {
        add_role('hamkar_shart', 'همکار شرایط', ['read' => true]);
    }
}

add_action('init', 'hamkar_register_roles');
