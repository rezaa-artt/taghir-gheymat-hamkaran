<?php
/**
 * تخفیف پنل صدور فاکتور → پیش‌فاکتور سپیدار
 *
 * تخفیف صدور روی کل فاکتور است، نه روی هر کالا.
 * باید فقط در هدر پیش‌فاکتور بنشیند:
 *
 *   payload["Discount"]
 *
 * این همان ستون «مبلغ تخفیف» روی ردیف «جمع کل» است.
 *
 * این‌ها را پر نکنید:
 *   payload["Items"][i]["Discount"]   → تخفیف همان ردیف کالا
 *   payload["DiscountOnCustomer"]     → تخفیف مشتری در سپیدار (جای دیگر)
 *
 * اگر در Code Snippets می‌گذارید، خط <?php اول را پاک کنید.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلیدهای متای سفارش در پنل صدور را اینجا بگذارید.
 * اگر اسم فیلد فرق دارد فقط همین ۳ خط را عوض کنید.
 */
if (!defined('KND_INV_DISCOUNT_AMOUNT_META')) {
    define('KND_INV_DISCOUNT_AMOUNT_META', '_knd_invoice_discount_amount');
}
if (!defined('KND_INV_DISCOUNT_PERCENT_META')) {
    define('KND_INV_DISCOUNT_PERCENT_META', '_knd_invoice_discount_percent');
}
if (!defined('KND_INV_DISCOUNT_TYPE_META')) {
    define('KND_INV_DISCOUNT_TYPE_META', '_knd_invoice_discount_type');
}

/**
 * مبلغ تخفیف صدور را به تومان برمی‌گرداند.
 * نوع percent یعنی درصد از جمع کل اقلام (قبل از تخفیف).
 */
function knd_invoice_discount_toman($order, $items_total_toman = 0)
{
    $type    = $order->get_meta(KND_INV_DISCOUNT_TYPE_META, true);
    $amount  = (float) $order->get_meta(KND_INV_DISCOUNT_AMOUNT_META, true);
    $percent = (float) $order->get_meta(KND_INV_DISCOUNT_PERCENT_META, true);

    $type = $type !== '' ? $type : ($percent > 0 && $amount <= 0 ? 'percent' : 'amount');

    if ($type === 'percent' && $percent > 0) {
        $amount = ((float) $items_total_toman) * ($percent / 100);
    }

    if ($amount < 0) {
        $amount = 0;
    }

    return $amount;
}

/**
 * تومان پنل صدور → ریال سپیدار.
 */
function knd_invoice_discount_rial($toman)
{
    return (int) round(((float) $toman) * 10);
}

/**
 * تخفیف را فقط روی هدر می‌گذارد؛ ردیف کالا دست نمی‌خورد.
 *
 * $payload باید آرایه JSON پیش‌فاکتور سپیدار باشد.
 */
function knd_sepidar_put_invoice_discount($payload, $discount_rial)
{
    if (!is_array($payload)) {
        return $payload;
    }

    $discount_rial = (int) $discount_rial;
    if ($discount_rial < 0) {
        $discount_rial = 0;
    }

    $payload['Discount'] = $discount_rial;

    return $payload;
}

/**
 * اگر پلاگین صدور فیلتر داشته باشد، از همین رد می‌شود.
 * اسم فیلتر را با فایل پلاگین خودتان یکی کنید.
 */
add_filter('knd_sepidar_quotation_payload', 'knd_sepidar_apply_invoice_discount', 20, 2);
add_filter('knd_sepidar_invoice_payload', 'knd_sepidar_apply_invoice_discount', 20, 2);

function knd_sepidar_apply_invoice_discount($payload, $order)
{
    if (!$order instanceof WC_Order || !is_array($payload)) {
        return $payload;
    }

    $items_total = 0;
    if (!empty($payload['Items']) && is_array($payload['Items'])) {
        foreach ($payload['Items'] as $item) {
            if (isset($item['Price'])) {
                $items_total += ((float) $item['Price']) / 10;
            }
        }
    }

    if ($items_total <= 0) {
        $items_total = (float) $order->get_subtotal();
    }

    $toman = knd_invoice_discount_toman($order, $items_total);
    $rial  = knd_invoice_discount_rial($toman);

    return knd_sepidar_put_invoice_discount($payload, $rial);
}
