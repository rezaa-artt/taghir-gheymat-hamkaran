<?php

if (!defined('ABSPATH')) {
    exit;
}

class KND_CP_Ajax
{
    public static function init()
    {
        add_action('wp_ajax_knd_cp_load', array(__CLASS__, 'load'));
        add_action('wp_ajax_knd_cp_start', array(__CLASS__, 'start'));
        add_action('wp_ajax_knd_cp_process', array(__CLASS__, 'process'));
        add_action('wp_ajax_knd_cp_cancel', array(__CLASS__, 'cancel'));
        add_action('wp_ajax_knd_cp_status', array(__CLASS__, 'status'));
    }

    private static function guard()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'دسترسی ندارید.'), 403);
        }
        check_ajax_referer('knd_cp', 'nonce');
    }

    /**
     * محصولات را ۵۰تا ۵۰تا می‌خواند و به همان صفحه اضافه می‌شود.
     */
    public static function load()
    {
        self::guard();

        $page = isset($_POST['page']) ? max(0, (int) $_POST['page']) : 0;
        $ids  = get_transient('knd_cp_all_ids_' . get_current_user_id());

        if ($page === 0 || !is_array($ids)) {
            $ids = KND_CP_Calculator::collect_ids();
            set_transient('knd_cp_all_ids_' . get_current_user_id(), $ids, HOUR_IN_SECONDS);
        }

        $total  = count($ids);
        $offset = $page * KND_CP_BATCH;
        $chunk  = array_slice($ids, $offset, KND_CP_BATCH);
        $rows   = KND_CP_Calculator::rows_for_ids($chunk);

        wp_send_json_success(array(
            'rows'     => $rows,
            'page'     => $page,
            'total'    => $total,
            'has_more' => ($offset + KND_CP_BATCH) < $total,
            'batch'    => KND_CP_BATCH,
        ));
    }

    /**
     * کار ذخیره را می‌سازد. اگر کار ناتمام باشد و continue=1 باشد از همان offset ادامه می‌دهد.
     */
    public static function start()
    {
        self::guard();

        $continue = !empty($_POST['continue']);
        $job      = get_option(KND_CP_JOB_OPTION, array());

        if ($continue && is_array($job) && !empty($job['ids']) && isset($job['status']) && $job['status'] !== 'complete') {
            $job['status'] = 'running';
            update_option(KND_CP_JOB_OPTION, $job, false);
            wp_send_json_success(array(
                'job' => KND_CP_Admin::public_job($job),
            ));
        }

        $cash        = KND_CP_Calculator::sanitize_percent(isset($_POST['cash_percent']) ? $_POST['cash_percent'] : 40);
        $installment = KND_CP_Calculator::sanitize_percent(isset($_POST['installment_percent']) ? $_POST['installment_percent'] : 30);
        $selected    = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
        $selected    = array_values(array_filter($selected));

        if (!$selected) {
            wp_send_json_error(array('message' => 'محصولی انتخاب نشده است.'));
        }

        KND_CP_Calculator::save_settings($cash, $installment);

        $job = array(
            'id'                  => wp_generate_uuid4(),
            'status'              => 'running',
            'ids'                 => $selected,
            'offset'              => 0,
            'done'                => 0,
            'failed'              => array(),
            'cash_percent'        => $cash,
            'installment_percent' => $installment,
            'updated'             => time(),
        );

        update_option(KND_CP_JOB_OPTION, $job, false);

        wp_send_json_success(array(
            'job' => KND_CP_Admin::public_job($job),
        ));
    }

    /**
     * یک دسته ۵۰تایی را ذخیره می‌کند و offset را جلو می‌برد.
     */
    public static function process()
    {
        self::guard();

        $job = get_option(KND_CP_JOB_OPTION, array());
        if (!is_array($job) || empty($job['ids'])) {
            wp_send_json_error(array('message' => 'کار ذخیره‌ای پیدا نشد. دوباره شروع کنید.'));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if ($job_id !== '' && isset($job['id']) && $job['id'] !== $job_id) {
            wp_send_json_error(array('message' => 'این کار دیگر معتبر نیست.'));
        }

        $offset      = isset($job['offset']) ? (int) $job['offset'] : 0;
        $ids         = $job['ids'];
        $total       = count($ids);
        $chunk       = array_slice($ids, $offset, KND_CP_BATCH);
        $cash        = isset($job['cash_percent']) ? $job['cash_percent'] : 40;
        $installment = isset($job['installment_percent']) ? $job['installment_percent'] : 30;
        $filled      = 0;

        foreach ($chunk as $id) {
            $product = wc_get_product((int) $id);
            if ($product && KND_CP_Calculator::fill_product($product, $cash, $installment)) {
                $filled++;
            } else {
                $job['failed'][] = (int) $id;
            }
        }

        $job['offset']  = $offset + count($chunk);
        $job['done']    = isset($job['done']) ? ((int) $job['done'] + $filled) : $filled;
        $job['updated'] = time();
        $job['status']  = ($job['offset'] >= $total) ? 'complete' : 'running';

        if ($job['status'] === 'complete') {
            update_option(KND_CP_JOB_OPTION, $job, false);
        } else {
            update_option(KND_CP_JOB_OPTION, $job, false);
        }

        wp_send_json_success(array(
            'job'     => KND_CP_Admin::public_job($job),
            'filled'  => $filled,
            'complete'=> $job['status'] === 'complete',
            'done'    => $job['done'],
            'total'   => $total,
            'offset'  => $job['offset'],
        ));
    }

    public static function cancel()
    {
        self::guard();
        delete_option(KND_CP_JOB_OPTION);
        wp_send_json_success(array('message' => 'کار ناتمام لغو شد.'));
    }

    public static function status()
    {
        self::guard();
        $job = get_option(KND_CP_JOB_OPTION, array());
        wp_send_json_success(array(
            'job' => KND_CP_Admin::public_job($job),
        ));
    }
}
