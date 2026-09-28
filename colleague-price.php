<?php
/**
 * پر کردن فیلدهای قیمت همکاران
 *
 * همکار نقد:     _knd_colleague_cash_price_above          → ۴۰٪ کمتر از قیمت اصلی
 * همکار شرایط:   _knd_colleague_installment_price_above   → ۳۰٪ کمتر از قیمت اصلی
 *
 * مبنا همیشه regular_price است (قبل از تخفیف مشتری).
 *
 * این فایل را در functions.php قالب یا افزونه Code Snippets قرار دهید.
 * بعد از فعال‌سازی: ووکامرس → پر کردن قیمت همکاران
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KND_CASH_META', '_knd_colleague_cash_price_above');
define('KND_INSTALLMENT_META', '_knd_colleague_installment_price_above');
define('KND_CASH_PERCENT', 40);
define('KND_INSTALLMENT_PERCENT', 30);

/**
 * قیمت اصلی محصول — قبل از تخفیف فروش.
 */
function knd_hamkar_original_price($product)
{
    if (!$product instanceof WC_Product) {
        return 0;
    }

    $regular = $product->get_regular_price('edit');

    if ($regular === '' || $regular === null) {
        $regular = get_post_meta($product->get_id(), '_regular_price', true);
    }

    return (float) $regular;
}

/**
 * درصد را از قیمت اصلی کم می‌کند و به فرمت قیمت ووکامرس برمی‌گرداند.
 */
function knd_hamkar_calc($original_price, $percent)
{
    $original_price = (float) $original_price;
    $percent        = (float) $percent;

    if ($original_price <= 0) {
        return '';
    }

    $price = $original_price * (1 - ($percent / 100));

    return wc_format_decimal($price, wc_get_price_decimals());
}

/**
 * دو فیلد همکار را برای یک محصول یا متغیر پر می‌کند.
 * اگر قیمت اصلی نباشد، فیلدها دست نخورده می‌مانند.
 */
function knd_hamkar_fill_product($product)
{
    if (!$product instanceof WC_Product) {
        return false;
    }

    $original = knd_hamkar_original_price($product);
    if ($original <= 0) {
        return false;
    }

    $cash        = knd_hamkar_calc($original, KND_CASH_PERCENT);
    $installment = knd_hamkar_calc($original, KND_INSTALLMENT_PERCENT);

    $product->update_meta_data(KND_CASH_META, $cash);
    $product->update_meta_data(KND_INSTALLMENT_META, $installment);
    $product->save_meta_data();

    update_post_meta($product->get_id(), KND_CASH_META, $cash);
    update_post_meta($product->get_id(), KND_INSTALLMENT_META, $installment);

    return true;
}

/**
 * بعد از ذخیره محصول ساده / والد متغیر.
 */
function knd_hamkar_on_save_product($product_id)
{
    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }

    knd_hamkar_fill_product($product);

    if ($product->is_type('variable')) {
        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                knd_hamkar_fill_product($variation);
            }
        }
    }
}

add_action('woocommerce_process_product_meta', 'knd_hamkar_on_save_product', 99);
add_action('woocommerce_save_product_variation', 'knd_hamkar_fill_variation', 99, 2);

function knd_hamkar_fill_variation($variation_id, $i)
{
    $variation = wc_get_product($variation_id);
    if ($variation) {
        knd_hamkar_fill_product($variation);
    }
}

/**
 * صفحه ادمین برای پر کردن یک‌جای محصولات قدیمی.
 */
function knd_hamkar_admin_menu()
{
    add_submenu_page(
        'woocommerce',
        'پر کردن قیمت همکاران',
        'پر کردن قیمت همکاران',
        'manage_woocommerce',
        'knd-fill-colleague-prices',
        'knd_hamkar_admin_page'
    );
}

add_action('admin_menu', 'knd_hamkar_admin_menu');

function knd_hamkar_admin_page()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $filled   = null;
    $rows     = [];
    $show_preview = false;
    $transient_key = knd_hamkar_preview_key();

    if (
        isset($_POST['knd_hamkar_preview'])
        && check_admin_referer('knd_hamkar_preview')
    ) {
        $rows = knd_hamkar_collect_preview_rows();
        set_transient($transient_key, $rows, HOUR_IN_SECONDS);
        $show_preview = true;
    } elseif (
        isset($_POST['knd_hamkar_confirm'])
        && check_admin_referer('knd_hamkar_confirm')
    ) {
        $preview_rows = get_transient($transient_key);
        if (!is_array($preview_rows)) {
            $preview_rows = [];
        }

        $allowed_ids = array_map('intval', array_column($preview_rows, 'id'));
        $selected    = isset($_POST['knd_product_ids']) ? array_map('intval', (array) $_POST['knd_product_ids']) : [];

        if (!empty($_POST['knd_hamkar_confirm_all'])) {
            $selected = $allowed_ids;
        }

        $selected = array_values(array_intersect($selected, $allowed_ids));
        $filled   = knd_hamkar_fill_ids($selected);
        delete_transient($transient_key);
    } elseif (!empty($_GET['knd_preview'])) {
        $cached = get_transient($transient_key);
        if (is_array($cached) && $cached) {
            $rows = $cached;
            $show_preview = true;
        }
    }

    ?>
    <div class="wrap">
        <h1>پر کردن قیمت همکاران</h1>
        <p>قیمت همکار از روی <strong>قیمت اصلی قبل از تخفیف</strong> حساب می‌شود:</p>
        <ul>
            <li>همکار نقد (<code><?php echo esc_html(KND_CASH_META); ?></code>): ۴۰٪ کمتر</li>
            <li>همکار شرایط (<code><?php echo esc_html(KND_INSTALLMENT_META); ?></code>): ۳۰٪ کمتر</li>
        </ul>

        <?php if ($filled !== null) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($filled); ?> محصول/متغیر به‌روزرسانی شد.</p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('knd_hamkar_preview'); ?>
            <p>
                <button type="submit" name="knd_hamkar_preview" class="button button-secondary">
                    پیش‌نمایش قیمت‌ها
                </button>
            </p>
        </form>

        <?php if ($show_preview) : ?>
            <?php knd_hamkar_render_preview_table($rows); ?>
        <?php endif; ?>
    </div>
    <?php
}

function knd_hamkar_preview_key()
{
    return 'knd_hamkar_preview_' . get_current_user_id();
}

function knd_hamkar_preview_row($product, $parent_name = '')
{
    $original = knd_hamkar_original_price($product);
    if ($original <= 0) {
        return null;
    }

    $sale = $product->get_sale_price('edit');
    $name = $product->get_name();

    if ($parent_name !== '') {
        $name = $parent_name . ' — ' . $name;
    }

    return [
        'id'                 => $product->get_id(),
        'name'               => $name,
        'original'           => $original,
        'sale'               => ($sale !== '' && $sale !== null) ? (float) $sale : '',
        'current_cash'       => get_post_meta($product->get_id(), KND_CASH_META, true),
        'current_installment'=> get_post_meta($product->get_id(), KND_INSTALLMENT_META, true),
        'new_cash'           => knd_hamkar_calc($original, KND_CASH_PERCENT),
        'new_installment'    => knd_hamkar_calc($original, KND_INSTALLMENT_PERCENT),
    ];
}

function knd_hamkar_collect_preview_rows()
{
    $rows = [];
    $page = 1;

    do {
        $ids = wc_get_products([
            'limit'  => 100,
            'page'   => $page,
            'return' => 'ids',
            'status' => ['publish', 'draft', 'pending', 'private'],
            'type'   => array_keys(wc_get_product_types()),
        ]);

        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }

            if ($product->is_type('variable')) {
                $parent_name = $product->get_name();
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if (!$variation) {
                        continue;
                    }
                    $row = knd_hamkar_preview_row($variation, $parent_name);
                    if ($row) {
                        $rows[] = $row;
                    }
                }
                continue;
            }

            $row = knd_hamkar_preview_row($product);
            if ($row) {
                $rows[] = $row;
            }
        }

        $page++;
    } while (!empty($ids));

    return $rows;
}

function knd_hamkar_format_price($price)
{
    if ($price === '' || $price === null) {
        return '—';
    }

    return wc_price($price);
}

function knd_hamkar_render_preview_table($rows)
{
    $per_page = 50;
    $total    = count($rows);
    $paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $pages    = max(1, (int) ceil($total / $per_page));
    $paged    = min($paged, $pages);
    $slice    = array_slice($rows, ($paged - 1) * $per_page, $per_page);
    $base_url = add_query_arg([
        'page'        => 'knd-fill-colleague-prices',
        'knd_preview' => 1,
    ], admin_url('admin.php'));

    ?>
    <hr>
    <h2>پیش‌نمایش (<?php echo esc_html(number_format_i18n($total)); ?> مورد)</h2>
    <p>قیمت‌ها هنوز ذخیره نشده‌اند. موارد را چک کنید، بعد تأیید کنید.</p>

    <form method="post">
        <?php wp_nonce_field('knd_hamkar_confirm'); ?>
        <p>
            <button type="submit" name="knd_hamkar_confirm" class="button button-primary">
                تأیید و پر کردن موارد انتخاب‌شده
            </button>
            <button type="submit" name="knd_hamkar_confirm_all" value="1" class="button">
                تأیید همه موارد پیش‌نمایش
            </button>
            <input type="hidden" name="knd_hamkar_confirm" value="1">
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" id="knd-select-all" checked>
                    </td>
                    <th>محصول</th>
                    <th>قیمت اصلی</th>
                    <th>قیمت فروش (بعد تخفیف)</th>
                    <th>همکار نقد فعلی</th>
                    <th>همکار نقد جدید</th>
                    <th>همکار شرایط فعلی</th>
                    <th>همکار شرایط جدید</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$slice) : ?>
                    <tr>
                        <td colspan="8">محصولی با قیمت اصلی پیدا نشد.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($slice as $row) : ?>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" name="knd_product_ids[]" value="<?php echo esc_attr($row['id']); ?>" checked>
                        </th>
                        <td>
                            <strong><?php echo esc_html($row['name']); ?></strong>
                            <div class="description">شناسه: <?php echo esc_html($row['id']); ?></div>
                        </td>
                        <td><?php echo wp_kses_post(knd_hamkar_format_price($row['original'])); ?></td>
                        <td><?php echo wp_kses_post(knd_hamkar_format_price($row['sale'])); ?></td>
                        <td><?php echo wp_kses_post(knd_hamkar_format_price($row['current_cash'])); ?></td>
                        <td><strong><?php echo wp_kses_post(knd_hamkar_format_price($row['new_cash'])); ?></strong></td>
                        <td><?php echo wp_kses_post(knd_hamkar_format_price($row['current_installment'])); ?></td>
                        <td><strong><?php echo wp_kses_post(knd_hamkar_format_price($row['new_installment'])); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <?php if ($pages > 1) : ?>
        <p>
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
                <?php if ($i === $paged) : ?>
                    <strong><?php echo esc_html($i); ?></strong>
                <?php else : ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $i, $base_url)); ?>"><?php echo esc_html($i); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </p>
        <p class="description">تیک‌ها فقط برای همین صفحه اعمال می‌شود. برای همه صفحات از «تأیید همه موارد پیش‌نمایش» استفاده کنید.</p>
    <?php endif; ?>

    <script>
    document.getElementById('knd-select-all')?.addEventListener('change', function () {
        document.querySelectorAll('input[name="knd_product_ids[]"]').forEach(function (box) {
            box.checked = this.checked;
        }, this);
    });
    </script>
    <?php
}

function knd_hamkar_fill_ids($ids)
{
    $count = 0;

    foreach ((array) $ids as $id) {
        $product = wc_get_product((int) $id);
        if ($product && knd_hamkar_fill_product($product)) {
            $count++;
        }
    }

    return $count;
}
