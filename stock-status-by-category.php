<?php
/**
 * تغییر موجودی انبار بر اساس دسته‌بندی
 *
 * اگر در Code Snippets می‌گذارید، خط <?php اول را پاک کنید.
 *
 * ووکامرس → تغییر موجودی انبار
 */

if (!defined('ABSPATH')) {
    exit;
}

function knd_stock_admin_menu()
{
    add_submenu_page(
        'woocommerce',
        'تغییر موجودی انبار',
        'تغییر موجودی انبار',
        'manage_woocommerce',
        'knd-stock-by-category',
        'knd_stock_admin_page'
    );
}

add_action('admin_menu', 'knd_stock_admin_menu');

function knd_stock_preview_key()
{
    return 'knd_stock_preview_' . get_current_user_id();
}

function knd_stock_admin_page()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $updated       = null;
    $show_preview  = false;
    $payload       = null;
    $transient_key = knd_stock_preview_key();
    $selected_cats = array();
    $action        = 'unmanage_instock';
    $qty           = 1;

    if (isset($_POST['knd_stock_preview']) && check_admin_referer('knd_stock_preview')) {
        $selected_cats = knd_stock_posted_cats();
        $action        = knd_stock_posted_action();
        $qty           = knd_stock_posted_qty();

        if (!$selected_cats) {
            echo '<div class="notice notice-error"><p>حداقل یک دسته‌بندی انتخاب کنید.</p></div>';
        } elseif (in_array($action, array('set_qty', 'add_qty'), true) && $qty < 1) {
            echo '<div class="notice notice-error"><p>برای این حالت، تعداد باید حداقل ۱ باشد.</p></div>';
        } else {
            $payload = array(
                'action'       => $action,
                'qty'          => $qty,
                'category_ids' => $selected_cats,
                'rows'         => knd_stock_collect_rows($selected_cats, $action, $qty),
            );
            set_transient($transient_key, $payload, HOUR_IN_SECONDS);
            $show_preview = true;
        }
    } elseif (isset($_POST['knd_stock_confirm']) && check_admin_referer('knd_stock_confirm')) {
        $payload = get_transient($transient_key);
        if (!is_array($payload) || empty($payload['rows'])) {
            echo '<div class="notice notice-error"><p>پیش‌نمایش منقضی شده. دوباره پیش‌نمایش بگیرید.</p></div>';
        } else {
            $allowed_ids = array_map('intval', array_column($payload['rows'], 'id'));
            $selected    = isset($_POST['knd_stock_ids']) ? array_map('intval', (array) $_POST['knd_stock_ids']) : array();

            if (!empty($_POST['knd_stock_confirm_all'])) {
                $selected = $allowed_ids;
            }

            $selected = array_values(array_intersect($selected, $allowed_ids));
            $updated  = knd_stock_apply_ids($selected, $payload['rows']);
            delete_transient($transient_key);
        }
    } elseif (!empty($_GET['knd_stock_preview'])) {
        $cached = get_transient($transient_key);
        if (is_array($cached) && !empty($cached['rows'])) {
            $payload       = $cached;
            $show_preview  = true;
            $selected_cats = $cached['category_ids'];
            $action        = $cached['action'];
            $qty           = $cached['qty'];
        }
    }

    echo '<div class="wrap">';
    echo '<h1>تغییر موجودی انبار</h1>';
    echo '<p>محصولات دسته‌های انتخاب‌شده و متغیرهای‌شان خوانده می‌شوند. تا تأیید نکنید چیزی ذخیره نمی‌شود.</p>';

    if ($updated !== null) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($updated) . ' محصول/متغیر به‌روزرسانی شد.</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field('knd_stock_preview');

    echo '<h2>۱. دسته‌بندی</h2>';
    knd_stock_render_category_picker($selected_cats);

    echo '<h2>۲. نوع تغییر</h2>';
    echo '<fieldset>';

    echo '<label style="display:block;margin:8px 0;">';
    echo '<input type="radio" name="knd_stock_action" value="unmanage_instock" ' . checked($action, 'unmanage_instock', false) . '> ';
    echo 'مدیریت موجودی را خاموش کن و محصول را <strong>موجود در انبار</strong> کن ';
    echo '<span class="description">برای محصولاتی که خودتان تولید می‌کنید و تعداد نمی‌خواهید.</span>';
    echo '</label>';

    echo '<label style="display:block;margin:8px 0;">';
    echo '<input type="radio" name="knd_stock_action" value="set_qty" ' . checked($action, 'set_qty', false) . '> ';
    echo 'مدیریت موجودی را روشن کن و موجودی را روی این عدد بگذار:';
    echo '</label>';

    echo '<label style="display:block;margin:8px 0;">';
    echo '<input type="radio" name="knd_stock_action" value="add_qty" ' . checked($action, 'add_qty', false) . '> ';
    echo 'همین عدد را به موجودی فعلی <strong>اضافه</strong> کن (مدیریت موجودی روشن می‌شود)';
    echo '</label>';

    echo '<p><label>تعداد ثابت: ';
    echo '<input type="number" name="knd_stock_qty" min="1" step="1" value="' . esc_attr($qty) . '" class="small-text">';
    echo '</label></p>';

    echo '</fieldset>';
    echo '<p><button type="submit" name="knd_stock_preview" class="button button-secondary">پیش‌نمایش تغییرات</button></p>';
    echo '</form>';

    if ($show_preview && $payload) {
        knd_stock_render_preview($payload);
    }

    echo '</div>';
}

function knd_stock_posted_cats()
{
    $cats = isset($_POST['knd_stock_cats']) ? array_map('intval', (array) $_POST['knd_stock_cats']) : array();
    return array_values(array_filter($cats));
}

function knd_stock_posted_action()
{
    $action  = isset($_POST['knd_stock_action']) ? sanitize_key(wp_unslash($_POST['knd_stock_action'])) : 'unmanage_instock';
    $allowed = array('unmanage_instock', 'set_qty', 'add_qty');

    return in_array($action, $allowed, true) ? $action : 'unmanage_instock';
}

function knd_stock_posted_qty()
{
    return isset($_POST['knd_stock_qty']) ? max(0, (int) $_POST['knd_stock_qty']) : 0;
}

function knd_stock_render_category_picker($selected_cats)
{
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ));

    if (is_wp_error($terms) || !$terms) {
        echo '<p>دسته‌بندی محصولی پیدا نشد.</p>';
        return;
    }

    $selected_cats = array_map('intval', (array) $selected_cats);

    echo '<div style="max-height:260px;overflow:auto;border:1px solid #c3c4c7;padding:10px;max-width:480px;background:#fff;">';
    foreach ($terms as $term) {
        $checked = in_array((int) $term->term_id, $selected_cats, true);
        echo '<label style="display:block;margin:4px 0;">';
        echo '<input type="checkbox" name="knd_stock_cats[]" value="' . esc_attr($term->term_id) . '" ' . checked($checked, true, false) . '> ';
        echo esc_html($term->name) . ' <span class="description">(' . esc_html($term->count) . ')</span>';
        echo '</label>';
    }
    echo '</div>';
}

function knd_stock_expand_category_ids($category_ids)
{
    $all = array();

    foreach ((array) $category_ids as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }

        $all[]    = $id;
        $children = get_term_children($id, 'product_cat');
        if (!is_wp_error($children)) {
            $all = array_merge($all, array_map('intval', $children));
        }
    }

    return array_values(array_unique($all));
}

function knd_stock_status_label($status)
{
    $map = array(
        'instock'     => 'موجود در انبار',
        'outofstock'  => 'ناموجود',
        'onbackorder' => 'پیش‌سفارش',
    );

    return isset($map[$status]) ? $map[$status] : $status;
}

function knd_stock_yes_no($value)
{
    return $value ? 'روشن' : 'خاموش';
}

function knd_stock_type_label($type)
{
    if ($type === 'variation') {
        return 'متغیر';
    }
    if ($type === 'variable') {
        return 'محصول متغیر';
    }
    return 'ساده';
}

function knd_stock_compute_next($product, $action, $qty)
{
    $stock = $product->get_stock_quantity('edit');
    $stock = ($stock === '' || $stock === null) ? 0 : (int) $stock;

    if ($action === 'set_qty') {
        return array(
            'manage' => true,
            'qty'    => (int) $qty,
            'status' => $qty > 0 ? 'instock' : 'outofstock',
        );
    }

    if ($action === 'add_qty') {
        $next = $stock + (int) $qty;
        return array(
            'manage' => true,
            'qty'    => $next,
            'status' => $next > 0 ? 'instock' : 'outofstock',
        );
    }

    return array(
        'manage' => false,
        'qty'    => '',
        'status' => 'instock',
    );
}

function knd_stock_row($product, $action, $qty, $parent_name = '')
{
    $next = knd_stock_compute_next($product, $action, $qty);
    $name = $product->get_name();

    if ($parent_name !== '') {
        $name = $parent_name . ' — ' . $name;
    }

    $current_qty = $product->get_stock_quantity('edit');

    return array(
        'id'          => $product->get_id(),
        'name'        => $name,
        'sku'         => $product->get_sku('edit'),
        'type'        => $product->get_type(),
        'cur_manage'  => (bool) $product->get_manage_stock('edit'),
        'cur_qty'     => ($current_qty === '' || $current_qty === null) ? '' : (int) $current_qty,
        'cur_status'  => $product->get_stock_status('edit'),
        'next_manage' => $next['manage'],
        'next_qty'    => $next['qty'],
        'next_status' => $next['status'],
        'action'      => $action,
    );
}

function knd_stock_collect_rows($category_ids, $action, $qty)
{
    $rows     = array();
    $term_ids = knd_stock_expand_category_ids($category_ids);
    $page     = 1;
    $seen     = array();

    if (!$term_ids) {
        return $rows;
    }

    do {
        $ids = wc_get_products(array(
            'limit'     => 100,
            'page'      => $page,
            'return'    => 'ids',
            'status'    => array('publish', 'draft', 'pending', 'private'),
            'type'      => array('simple', 'variable', 'grouped'),
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                ),
            ),
        ));

        foreach ($ids as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }

            $rows[] = knd_stock_row($product, $action, $qty);

            if (!$product->is_type('variable')) {
                continue;
            }

            $parent_name = $product->get_name();
            foreach ($product->get_children() as $variation_id) {
                if (isset($seen[$variation_id])) {
                    continue;
                }
                $seen[$variation_id] = true;

                $variation = wc_get_product($variation_id);
                if (!$variation) {
                    continue;
                }

                $rows[] = knd_stock_row($variation, $action, $qty, $parent_name);
            }
        }

        $page++;
    } while (!empty($ids));

    return $rows;
}

function knd_stock_action_label($action, $qty)
{
    if ($action === 'set_qty') {
        return 'موجودی روی ' . number_format_i18n((int) $qty) . ' تنظیم می‌شود و مدیریت موجودی روشن می‌ماند.';
    }

    if ($action === 'add_qty') {
        return number_format_i18n((int) $qty) . ' عدد به موجودی فعلی اضافه می‌شود و مدیریت موجودی روشن می‌شود.';
    }

    return 'مدیریت موجودی خاموش و وضعیت «موجود در انبار» می‌شود.';
}

function knd_stock_qty_text($qty)
{
    if ($qty === '') {
        return '—';
    }
    return number_format_i18n((int) $qty);
}

function knd_stock_render_preview($payload)
{
    $rows     = $payload['rows'];
    $per_page = 50;
    $total    = count($rows);
    $paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $pages    = max(1, (int) ceil($total / $per_page));
    $paged    = min($paged, $pages);
    $slice    = array_slice($rows, ($paged - 1) * $per_page, $per_page);
    $base_url = add_query_arg(array(
        'page'              => 'knd-stock-by-category',
        'knd_stock_preview' => 1,
    ), admin_url('admin.php'));

    echo '<hr>';
    echo '<h2>پیش‌نمایش (' . esc_html(number_format_i18n($total)) . ' مورد)</h2>';
    echo '<p>' . esc_html(knd_stock_action_label($payload['action'], $payload['qty'])) . '</p>';
    echo '<p>هنوز ذخیره نشده. موارد را چک کنید، بعد تأیید کنید.</p>';

    echo '<form method="post">';
    wp_nonce_field('knd_stock_confirm');
    echo '<p>';
    echo '<button type="submit" name="knd_stock_confirm" class="button button-primary">تأیید و اعمال موارد انتخاب‌شده</button> ';
    echo '<button type="submit" name="knd_stock_confirm_all" value="1" class="button">تأیید همه موارد پیش‌نمایش</button>';
    echo '<input type="hidden" name="knd_stock_confirm" value="1">';
    echo '</p>';

    echo '<table class="widefat striped"><thead><tr>';
    echo '<td class="check-column"><input type="checkbox" id="knd-stock-select-all" checked></td>';
    echo '<th>محصول</th><th>نوع</th><th>مدیریت موجودی فعلی</th><th>تعداد فعلی</th><th>وضعیت فعلی</th>';
    echo '<th>مدیریت موجودی جدید</th><th>تعداد جدید</th><th>وضعیت جدید</th>';
    echo '</tr></thead><tbody>';

    if (!$slice) {
        echo '<tr><td colspan="9">در این دسته‌ها محصولی پیدا نشد.</td></tr>';
    }

    foreach ($slice as $row) {
        echo '<tr>';
        echo '<th class="check-column"><input type="checkbox" name="knd_stock_ids[]" value="' . esc_attr($row['id']) . '" checked></th>';
        echo '<td><strong>' . esc_html($row['name']) . '</strong>';
        echo '<div class="description">شناسه: ' . esc_html($row['id']);
        if ($row['sku'] !== '') {
            echo ' — SKU: ' . esc_html($row['sku']);
        }
        echo '</div></td>';
        echo '<td>' . esc_html(knd_stock_type_label($row['type'])) . '</td>';
        echo '<td>' . esc_html(knd_stock_yes_no($row['cur_manage'])) . '</td>';
        echo '<td>' . esc_html(knd_stock_qty_text($row['cur_qty'])) . '</td>';
        echo '<td>' . esc_html(knd_stock_status_label($row['cur_status'])) . '</td>';
        echo '<td><strong>' . esc_html(knd_stock_yes_no($row['next_manage'])) . '</strong></td>';
        echo '<td><strong>' . esc_html(knd_stock_qty_text($row['next_qty'])) . '</strong></td>';
        echo '<td><strong>' . esc_html(knd_stock_status_label($row['next_status'])) . '</strong></td>';
        echo '</tr>';
    }

    echo '</tbody></table></form>';

    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === $paged) {
                echo '<strong>' . esc_html($i) . '</strong> ';
            } else {
                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html($i) . '</a> ';
            }
        }
        echo '</p>';
        echo '<p class="description">تیک‌ها فقط برای همین صفحه است. برای همه صفحات از «تأیید همه موارد پیش‌نمایش» استفاده کنید.</p>';
    }

    echo '<script>';
    echo 'var kndStockAll = document.getElementById("knd-stock-select-all");';
    echo 'if (kndStockAll) {';
    echo 'kndStockAll.addEventListener("change", function () {';
    echo 'var boxes = document.querySelectorAll("input[name=\'knd_stock_ids[]\']");';
    echo 'for (var i = 0; i < boxes.length; i++) { boxes[i].checked = kndStockAll.checked; }';
    echo '});';
    echo '}';
    echo '</script>';
}

function knd_stock_apply_ids($ids, $rows)
{
    $by_id = array();
    foreach ($rows as $row) {
        $by_id[(int) $row['id']] = $row;
    }

    $count = 0;

    foreach ((array) $ids as $id) {
        $id = (int) $id;
        if (!isset($by_id[$id])) {
            continue;
        }

        $row     = $by_id[$id];
        $product = wc_get_product($id);
        if (!$product) {
            continue;
        }

        $product->set_manage_stock((bool) $row['next_manage']);

        if ($row['next_manage']) {
            $product->set_stock_quantity((int) $row['next_qty']);
        }

        $product->set_stock_status($row['next_status']);
        $product->save();
        $count++;
    }

    return $count;
}
