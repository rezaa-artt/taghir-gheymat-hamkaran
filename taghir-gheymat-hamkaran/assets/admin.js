(function ($) {
  var state = {
    rows: [],
    loading: false,
    saving: false,
    page: 0
  };

  function cashPercent() {
    return parseFloat($('#knd-cp-cash').val()) || 0;
  }

  function installmentPercent() {
    return parseFloat($('#knd-cp-installment').val()) || 0;
  }

  function calcPrice(original, percent) {
    original = parseFloat(original) || 0;
    percent = parseFloat(percent) || 0;
    if (original <= 0) {
      return '';
    }
    var price = original * (1 - percent / 100);
    var decimals = kndCp.decimals || 0;
    return price.toFixed(decimals);
  }

  function formatNum(value) {
    if (value === '' || value === null || typeof value === 'undefined') {
      return '—';
    }
    var n = parseFloat(value);
    if (isNaN(n)) {
      return '—';
    }
    return n.toLocaleString('fa-IR');
  }

  function typeLabel(type) {
    if (type === 'variation') {
      return 'متغیر';
    }
    if (type === 'variable') {
      return 'محصول متغیر';
    }
    return 'ساده';
  }

  function setStatus(text, isError) {
    var $el = $('#knd-cp-status');
    $el.text(text || '');
    $el.toggleClass('knd-cp-status-err', !!isError);
    $el.toggleClass('knd-cp-status-ok', !isError && !!text);
  }

  function setProgress(done, total) {
    var pct = total ? Math.round((done / total) * 100) : 0;
    $('#knd-cp-progress').show();
    $('#knd-cp-progress-fill').css('width', pct + '%');
    $('#knd-cp-progress-text').text(done + ' از ' + total + ' (' + pct + '٪)');
  }

  function selectedIds() {
    var ids = [];
    $('input[name="knd_cp_ids[]"]:checked').each(function () {
      ids.push(parseInt($(this).val(), 10));
    });
    return ids;
  }

  function ensureTable() {
    if ($('#knd-cp-table').length) {
      return $('#knd-cp-tbody');
    }

    var html = '';
    html += '<table id="knd-cp-table" class="widefat striped">';
    html += '<thead><tr>';
    html += '<td class="check-column"><input type="checkbox" id="knd-cp-select-all" checked></td>';
    html += '<th>محصول</th><th>نوع</th><th>قیمت اصلی</th><th>قیمت فروش</th>';
    html += '<th>همکار نقد فعلی</th><th>همکار نقد جدید</th>';
    html += '<th>همکار شرایط فعلی</th><th>همکار شرایط جدید</th>';
    html += '</tr></thead><tbody id="knd-cp-tbody"></tbody></table>';
    $('#knd-cp-table-wrap').html(html);
    return $('#knd-cp-tbody');
  }

  function appendBatch(rows, batchIndex, total) {
    var $body = ensureTable();
    var start = (batchIndex - 1) * kndCp.batch + 1;
    var end = start + rows.length - 1;
    var head = '<tr class="knd-cp-batch-head"><td colspan="9">دسته ' + batchIndex + ' — موارد ' + start + ' تا ' + end + ' از ' + total + '</td></tr>';
    $body.append(head);

    rows.forEach(function (row) {
      var $tr = $('<tr></tr>').attr('data-id', row.id).attr('data-original', row.original);
      var sku = row.sku ? ' — SKU: ' + row.sku : '';
      $tr.append('<th class="check-column"><input type="checkbox" name="knd_cp_ids[]" value="' + row.id + '" checked></th>');
      $tr.append('<td><strong>' + escapeHtml(row.name) + '</strong><div class="description">شناسه: ' + row.id + sku + '</div></td>');
      $tr.append('<td>' + typeLabel(row.type) + '</td>');
      $tr.append('<td>' + formatNum(row.original) + '</td>');
      $tr.append('<td>' + formatNum(row.sale) + '</td>');
      $tr.append('<td>' + formatNum(row.current_cash) + '</td>');
      $tr.append('<td class="knd-cp-new-cash"><strong></strong></td>');
      $tr.append('<td>' + formatNum(row.current_installment) + '</td>');
      $tr.append('<td class="knd-cp-new-installment"><strong></strong></td>');
      $body.append($tr);
    });

    refreshPreview();
  }

  function refreshPreview() {
    var cash = cashPercent();
    var inst = installmentPercent();
    $('#knd-cp-table tbody tr[data-original]').each(function () {
      var original = $(this).attr('data-original');
      $(this).find('.knd-cp-new-cash strong').text(formatNum(calcPrice(original, cash)));
      $(this).find('.knd-cp-new-installment strong').text(formatNum(calcPrice(original, inst)));
    });
  }

  function escapeHtml(text) {
    return $('<div>').text(text || '').html();
  }

  function ajax(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = kndCp.nonce;
    return $.post(kndCp.ajax, data);
  }

  function loadPage(page) {
    state.loading = true;
    $('#knd-cp-load').prop('disabled', true);
    setStatus('در حال خواندن دسته ' + (page + 1) + '...');

    return ajax('knd_cp_load', { page: page }).done(function (res) {
      if (!res || !res.success) {
        setStatus((res && res.data && res.data.message) || 'خواندن محصولات ناموفق بود.', true);
        state.loading = false;
        $('#knd-cp-load').prop('disabled', false);
        return;
      }

      var data = res.data;
      appendBatch(data.rows, page + 1, data.total);
      state.rows = state.rows.concat(data.rows);
      setProgress(state.rows.length, data.total);
      setStatus(state.rows.length + ' محصول در همین صفحه بارگذاری شد.');

      if (data.has_more) {
        loadPage(page + 1);
      } else {
        state.loading = false;
        $('#knd-cp-load').prop('disabled', false);
        $('#knd-cp-save').prop('disabled', state.rows.length === 0);
        setStatus('همه محصولات در همین صفحه آمدند. درصد را چک کنید و ذخیره را بزنید.');
      }
    }).fail(function () {
      state.loading = false;
      $('#knd-cp-load').prop('disabled', false);
      setStatus('ارتباط قطع شد. دوباره «خواندن همه محصولات» را بزنید؛ موارد قبلی در صفحه مانده‌اند.', true);
    });
  }

  function processLoop() {
    state.saving = true;
    $('#knd-cp-save, #knd-cp-continue, #knd-cp-load').prop('disabled', true);

    ajax('knd_cp_process', { job_id: state.jobId }).done(function (res) {
      if (!res || !res.success) {
        state.saving = false;
        $('#knd-cp-save, #knd-cp-continue, #knd-cp-load').prop('disabled', false);
        $('#knd-cp-resume').show();
        setStatus((res && res.data && res.data.message) || 'ذخیره این دسته ناموفق بود. ادامه بزنید تا از همین‌جا برود.', true);
        return;
      }

      var data = res.data;
      setProgress(data.offset, data.total);
      var batchNo = Math.ceil(data.offset / kndCp.batch);
      var batchCount = Math.ceil(data.total / kndCp.batch);
      setStatus('دسته ' + batchNo + ' از ' + batchCount + ' ذخیره شد.');

      if (data.complete) {
        state.saving = false;
        state.jobId = null;
        kndCp.job = null;
        $('#knd-cp-save, #knd-cp-load').prop('disabled', false);
        $('#knd-cp-resume').hide();
        setStatus('ذخیره تمام شد. ' + data.done + ' محصول به‌روزرسانی شد.');
        return;
      }

      processLoop();
    }).fail(function () {
      state.saving = false;
      $('#knd-cp-save, #knd-cp-continue, #knd-cp-load').prop('disabled', false);
      $('#knd-cp-resume').show();
      setStatus('اتصال قطع شد. «ادامه ذخیره» را بزنید؛ از دسته ذخیره‌شده ادامه می‌دهد، از اول نمی‌رود.', true);
    });
  }

  function startJob(isContinue) {
    var payload = {
      continue: isContinue ? 1 : 0,
      cash_percent: cashPercent(),
      installment_percent: installmentPercent(),
      ids: selectedIds()
    };

    if (!isContinue && !payload.ids.length) {
      setStatus('حداقل یک محصول را تیک بزنید.', true);
      return;
    }

    ajax('knd_cp_start', payload).done(function (res) {
      if (!res || !res.success) {
        setStatus((res && res.data && res.data.message) || 'شروع ذخیره ناموفق بود.', true);
        return;
      }

      var job = res.data.job;
      state.jobId = job.id;
      setProgress(job.offset, job.total);
      processLoop();
    }).fail(function () {
      setStatus('شروع ذخیره به خاطر قطع اتصال انجام نشد.', true);
    });
  }

  function showResume(job) {
    if (!job) {
      $('#knd-cp-resume').hide();
      return;
    }
    state.jobId = job.id;
    $('#knd-cp-cash').val(job.cash_percent);
    $('#knd-cp-installment').val(job.installment_percent);
    setProgress(job.offset, job.total);
    $('#knd-cp-resume').show();
    setStatus('کار ناتمام: دسته ' + job.batch_index + ' از ' + job.batch_count + '. ادامه بزنید.');
  }

  $(document).on('change input', '#knd-cp-cash, #knd-cp-installment', refreshPreview);

  $(document).on('change', '#knd-cp-select-all', function () {
    $('input[name="knd_cp_ids[]"]').prop('checked', this.checked);
  });

  $('#knd-cp-load').on('click', function () {
    state.rows = [];
    state.page = 0;
    $('#knd-cp-table-wrap').empty();
    loadPage(0);
  });

  $('#knd-cp-save').on('click', function () {
    startJob(false);
  });

  $('#knd-cp-continue').on('click', function () {
    startJob(true);
  });

  $('#knd-cp-cancel-job').on('click', function () {
    ajax('knd_cp_cancel').done(function () {
      kndCp.job = null;
      state.jobId = null;
      $('#knd-cp-resume').hide();
      $('#knd-cp-progress').hide();
      setStatus('کار ناتمام لغو شد.');
    });
  });

  if (kndCp.job) {
    showResume(kndCp.job);
  }
})(jQuery);
