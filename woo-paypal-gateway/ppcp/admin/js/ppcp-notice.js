(function ($) {
  $(document).on('click', '.wpg-notice-ai .notice-dismiss', function () {
    if (typeof WPG_NOTICE_AI === 'undefined') { return; }
    $.post(WPG_NOTICE_AI.ajax_url, {
      action: 'wpg_notice_ai_dismiss',
      nonce: WPG_NOTICE_AI.nonce
    });
  });
})(jQuery);