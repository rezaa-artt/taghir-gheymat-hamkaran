<?php
/**
 * Plugin Name: تغییر قیمت همکاران
 * Description: این پلاگین را برای فروشگاه ووکامرس ساختم تا قیمت همکار نقد و شرایط از روی قیمت اصلی (قبل از تخفیف مشتری) با درصد دستی حساب شود، همه محصولات در یک صفحه دیده شوند و ذخیره به‌صورت ۵۰تایی با قابلیت ادامه بعد از قطع شدن نت انجام شود.
 * Version: 1.0.0
 * Author: رضا غلامی
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * Text Domain: taghir-gheymat-hamkaran
 *
 * سازنده: رضا غلامی
 *
 * فیلدها:
 *   همکار نقد:     _knd_colleague_cash_price_above
 *   همکار شرایط:   _knd_colleague_installment_price_above
 *
 * مسیر پیش‌فرض: ووکامرس → تغییر قیمت همکاران
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KND_CP_VERSION', '1.0.0');
define('KND_CP_FILE', __FILE__);
define('KND_CP_DIR', plugin_dir_path(__FILE__));
define('KND_CP_URL', plugin_dir_url(__FILE__));
define('KND_CP_BATCH', 50);
define('KND_CP_CASH_META', '_knd_colleague_cash_price_above');
define('KND_CP_INSTALLMENT_META', '_knd_colleague_installment_price_above');
define('KND_CP_JOB_OPTION', 'knd_cp_job');
define('KND_CP_SETTINGS_OPTION', 'knd_cp_settings');

require_once KND_CP_DIR . 'includes/class-calculator.php';
require_once KND_CP_DIR . 'includes/class-admin.php';
require_once KND_CP_DIR . 'includes/class-ajax.php';

function knd_cp_init()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'knd_cp_need_woocommerce');
        return;
    }

    KND_CP_Admin::init();
    KND_CP_Ajax::init();
}

add_action('plugins_loaded', 'knd_cp_init');

function knd_cp_need_woocommerce()
{
    echo '<div class="notice notice-error"><p>افزونه تغییر قیمت همکاران به ووکامرس نیاز دارد.</p></div>';
}
