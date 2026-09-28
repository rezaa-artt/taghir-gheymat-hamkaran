<?php

if (!defined('ABSPATH')) {
    exit;
}

class KND_CP_Calculator
{
    public static function settings()
    {
        $saved = get_option(KND_CP_SETTINGS_OPTION, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        return array(
            'cash_percent'         => isset($saved['cash_percent']) ? (float) $saved['cash_percent'] : 40,
            'installment_percent'  => isset($saved['installment_percent']) ? (float) $saved['installment_percent'] : 30,
        );
    }

    public static function save_settings($cash_percent, $installment_percent)
    {
        update_option(KND_CP_SETTINGS_OPTION, array(
            'cash_percent'        => self::sanitize_percent($cash_percent),
            'installment_percent' => self::sanitize_percent($installment_percent),
        ));
    }

    public static function sanitize_percent($percent)
    {
        $percent = (float) $percent;
        if ($percent < 0) {
            $percent = 0;
        }
        if ($percent > 100) {
            $percent = 100;
        }
        return $percent;
    }

    public static function original_price($product)
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

    public static function calc($original_price, $percent)
    {
        $original_price = (float) $original_price;
        $percent        = self::sanitize_percent($percent);

        if ($original_price <= 0) {
            return '';
        }

        $price = $original_price * (1 - ($percent / 100));
        return wc_format_decimal($price, wc_get_price_decimals());
    }

    public static function fill_product($product, $cash_percent, $installment_percent)
    {
        if (!$product instanceof WC_Product) {
            return false;
        }

        $original = self::original_price($product);
        if ($original <= 0) {
            return false;
        }

        $cash        = self::calc($original, $cash_percent);
        $installment = self::calc($original, $installment_percent);

        $product->update_meta_data(KND_CP_CASH_META, $cash);
        $product->update_meta_data(KND_CP_INSTALLMENT_META, $installment);
        $product->save_meta_data();

        update_post_meta($product->get_id(), KND_CP_CASH_META, $cash);
        update_post_meta($product->get_id(), KND_CP_INSTALLMENT_META, $installment);

        return true;
    }

    public static function row($product, $parent_name = '')
    {
        $original = self::original_price($product);
        if ($original <= 0) {
            return null;
        }

        $sale = $product->get_sale_price('edit');
        $name = $product->get_name();
        if ($parent_name !== '') {
            $name = $parent_name . ' — ' . $name;
        }

        return array(
            'id'                  => $product->get_id(),
            'name'                => $name,
            'sku'                 => $product->get_sku('edit'),
            'type'                => $product->get_type(),
            'original'            => $original,
            'sale'                => ($sale !== '' && $sale !== null) ? (float) $sale : '',
            'current_cash'        => get_post_meta($product->get_id(), KND_CP_CASH_META, true),
            'current_installment' => get_post_meta($product->get_id(), KND_CP_INSTALLMENT_META, true),
        );
    }

    /**
     * شناسه همه محصولات ساده و متغیرها، بدون والد بدون قیمت.
     */
    public static function collect_ids()
    {
        $ids  = array();
        $page = 1;

        do {
            $found = wc_get_products(array(
                'limit'  => 100,
                'page'   => $page,
                'return' => 'ids',
                'status' => array('publish', 'draft', 'pending', 'private'),
                'type'   => array_keys(wc_get_product_types()),
            ));

            foreach ($found as $id) {
                $product = wc_get_product($id);
                if (!$product) {
                    continue;
                }

                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && self::original_price($variation) > 0) {
                            $ids[] = (int) $variation_id;
                        }
                    }
                    continue;
                }

                if (self::original_price($product) > 0) {
                    $ids[] = (int) $id;
                }
            }

            $page++;
        } while (!empty($found));

        return array_values(array_unique($ids));
    }

    public static function rows_for_ids($ids)
    {
        $rows = array();

        foreach ((array) $ids as $id) {
            $product = wc_get_product((int) $id);
            if (!$product) {
                continue;
            }

            $parent_name = '';
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                if ($parent) {
                    $parent_name = $parent->get_name();
                }
            }

            $row = self::row($product, $parent_name);
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
