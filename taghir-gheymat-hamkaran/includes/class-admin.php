<?php

if (!defined('ABSPATH')) {
    exit;
}

class KND_CP_Admin
{
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function menu()
    {
        add_submenu_page(
            'woocommerce',
            'تغییر قیمت همکاران',
            'تغییر قیمت همکاران',
            'manage_woocommerce',
            'taghir-gheymat-hamkaran',
            array(__CLASS__, 'page')
        );
    }

    public static function assets($hook)
    {
        if ($hook !== 'woocommerce_page_taghir-gheymat-hamkaran') {
            return;
        }

        wp_enqueue_style(
            'knd-cp-admin',
            KND_CP_URL . 'assets/admin.css',
            array(),
            KND_CP_VERSION
        );

        wp_enqueue_script(
            'knd-cp-admin',
            KND_CP_URL . 'assets/admin.js',
            array('jquery'),
            KND_CP_VERSION,
            true
        );

        $settings = KND_CP_Calculator::settings();
        $job      = get_option(KND_CP_JOB_OPTION, array());

        wp_localize_script('knd-cp-admin', 'kndCp', array(
            'ajax'                 => admin_url('admin-ajax.php'),
            'nonce'                => wp_create_nonce('knd_cp'),
            'batch'                => KND_CP_BATCH,
            'cash_percent'         => $settings['cash_percent'],
            'installment_percent'  => $settings['installment_percent'],
            'job'                  => self::public_job($job),
            'decimals'             => wc_get_price_decimals(),
        ));
    }

    public static function public_job($job)
    {
        if (!is_array($job) || empty($job['ids']) || empty($job['status'])) {
            return null;
        }

        if ($job['status'] === 'complete') {
            return null;
        }

        $total  = count($job['ids']);
        $offset = isset($job['offset']) ? (int) $job['offset'] : 0;

        return array(
            'id'                   => isset($job['id']) ? $job['id'] : '',
            'status'               => $job['status'],
            'total'                => $total,
            'offset'               => $offset,
            'done'                 => isset($job['done']) ? (int) $job['done'] : $offset,
            'batch_index'          => (int) floor($offset / KND_CP_BATCH) + 1,
            'batch_count'          => (int) ceil($total / KND_CP_BATCH),
            'cash_percent'         => isset($job['cash_percent']) ? $job['cash_percent'] : 40,
            'installment_percent'  => isset($job['installment_percent']) ? $job['installment_percent'] : 30,
        );
    }

    public static function page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = KND_CP_Calculator::settings();

        echo '<div class="wrap knd-cp-wrap">';
        echo '<h1>تغییر قیمت همکاران</h1>';
        echo '<p>درصد از <strong>قیمت اصلی قبل از تخفیف مشتری</strong> کم می‌شود و در فیلدهای همکار نقد و شرایط ذخیره می‌گردد.</p>';

        echo '<div class="knd-cp-card">';
        echo '<h2>درصد همکاران</h2>';
        echo '<p class="description">این درصدها را دستی وارد کنید. پیش‌نمایش همان لحظه عوض می‌شود.</p>';
        echo '<p>';
        echo '<label>همکار نقد (٪): <input type="number" id="knd-cp-cash" min="0" max="100" step="0.01" value="' . esc_attr($settings['cash_percent']) . '" class="small-text"></label> ';
        echo '<label>همکار شرایط (٪): <input type="number" id="knd-cp-installment" min="0" max="100" step="0.01" value="' . esc_attr($settings['installment_percent']) . '" class="small-text"></label>';
        echo '</p>';
        echo '<p class="description">نقد: فیلد <code>' . esc_html(KND_CP_CASH_META) . '</code> — شرایط: فیلد <code>' . esc_html(KND_CP_INSTALLMENT_META) . '</code></p>';
        echo '</div>';

        echo '<div id="knd-cp-resume" class="knd-cp-card knd-cp-resume" style="display:none;">';
        echo '<h2>ذخیره ناتمام</h2>';
        echo '<p>ذخیره قبلی قطع شده. از همان دسته ادامه داده می‌شود، از اول شروع نمی‌شود.</p>';
        echo '<p><button type="button" class="button button-primary" id="knd-cp-continue">ادامه ذخیره</button> ';
        echo '<button type="button" class="button" id="knd-cp-cancel-job">لغو کار ناتمام</button></p>';
        echo '</div>';

        echo '<div class="knd-cp-actions">';
        echo '<button type="button" class="button button-secondary" id="knd-cp-load">خواندن همه محصولات</button> ';
        echo '<button type="button" class="button button-primary" id="knd-cp-save" disabled>شروع ذخیره ۵۰تایی</button>';
        echo '</div>';

        echo '<div id="knd-cp-progress" class="knd-cp-progress" style="display:none;">';
        echo '<div class="knd-cp-progress-bar"><span id="knd-cp-progress-fill"></span></div>';
        echo '<p id="knd-cp-progress-text"></p>';
        echo '</div>';

        echo '<p id="knd-cp-status"></p>';

        echo '<div id="knd-cp-table-wrap"></div>';
        echo '</div>';
    }
}
