var $ = jQuery;
$(document).ready(function () {
    var $deactivationModal = $(".deactivation-Modal");
    if ($deactivationModal.length) {
        new ModalWpr($deactivationModal);
    }
    $("#deactivation-no-reason").on("click", function (e) {
        e.preventDefault();
        $('.deactivation-Modal').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
        const $link = $(this);
        const url = $link.attr("href");
        const data = {
            action: 'wpg_send_deactivation',
            reason: 'reason-other',
            reason_details: 'other',
            nonce: wpg_feedback_form_ajax_data.nonce
        };
        $.post(ajaxurl, data)
                .always(function () {
                    window.location.replace(url);
                });
    });


    $("#mixpanel-send-deactivation").on("click", function (e) {
        e.preventDefault();
        $('.deactivation-Modal').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
        const $button = $('#mixpanel-send-deactivation');
        const selected = $("input[name='reason']:checked");
        const reason = selected.val();
        const reasonDetails = selected.siblings('.deactivation-Modal-fieldHidden').find('textarea').val();

        if (!reason) {
            alert("Please select a reason before deactivating.");
            return;
        }

        $button.prop('disabled', true).css({cursor: 'not-allowed', opacity: '0.6'});

        const data = {
            action: 'wpg_send_deactivation',
            reason: reason,
            nonce: wpg_feedback_form_ajax_data.nonce,
            reason_details: reasonDetails || ''
        };

        $.post(ajaxurl, data)
                .always(function () {
                    window.location.replace($button.attr("href"));
                });
    });

});

function ModalWpr(aElem) {
    var refThis = this;
    this.elem = aElem;
    this.overlay = $('.deactivation-Modal-overlay');
    this.radio = $('input[name=reason]', aElem);
    this.closer = $('.deactivation-Modal-close, .deactivation-Modal-cancel', aElem);
    this.return = $('.deactivation-Modal-return', aElem);
    this.opener = $('.plugins [data-slug="woo-paypal-gateway"] .deactivate');
    this.question = $('.deactivation-Modal-question', aElem);
    this.button = $('.button-primary', aElem);
    this.title = $('.deactivation-Modal-header h2', aElem);
    this.textFields = $('input[type=text], textarea', aElem);
    this.hiddenReason = $('#deactivation-reason', aElem);
    this.hiddenDetails = $('#deactivation-details', aElem);
    this.titleText = this.title.text();
    this.opener.on("click", function () {
        refThis.open();
        return false;
    });
    this.closer.on("click", function () {
        refThis.close();
        return false;
    });
    aElem.on("keyup", function (event) {
        if (event.keyCode === 27) { // ESC key
            refThis.close();
            return false;
        }
    });
    this.return.on("click", function () {
        refThis.returnToQuestion();
        return false;
    });
    this.radio.on("change", function () {
        refThis.change($(this));
    });
    this.textFields.on("keyup", function () {
        refThis.hiddenDetails.val($(this).val());
        if (refThis.hiddenDetails.val() !== '') {
            refThis.button.removeClass('deactivation-isDisabled').removeAttr("disabled");
        } else {
            refThis.button.addClass('deactivation-isDisabled').attr("disabled", true);
        }
    });
}

ModalWpr.prototype.change = function (aElem) {
    var id = aElem.attr('id');
    this.hiddenReason.val(aElem.val());
    this.hiddenDetails.val('');
    this.textFields.val('');
    $('.deactivation-Modal-fieldHidden').removeClass('deactivation-isOpen');
    $('.deactivation-Modal-hidden').removeClass('deactivation-isOpen');

    var field = aElem.siblings('.deactivation-Modal-fieldHidden');
    if (field.length) {
        field.addClass('deactivation-isOpen');
        field.find('textarea').focus();
        this.button.addClass('deactivation-isDisabled').attr("disabled", true);
    } else {
        this.button.removeClass('deactivation-isDisabled').removeAttr("disabled");
    }
};

ModalWpr.prototype.returnToQuestion = function () {
    $('.deactivation-Modal-fieldHidden, .deactivation-Modal-hidden').removeClass('deactivation-isOpen');
    this.question.addClass('deactivation-isOpen');
    this.return.removeClass('deactivation-isOpen');
    this.title.text(this.titleText);
    this.hiddenReason.val('');
    this.hiddenDetails.val('');
    this.radio.prop('checked', false);
    this.button.addClass('deactivation-isDisabled').attr("disabled", true);
};

ModalWpr.prototype.open = function () {
    this.elem.show();
    this.overlay.show();
    localStorage.setItem('deactivation-hash', '');
};

ModalWpr.prototype.close = function () {
    this.returnToQuestion();
    this.elem.hide();
    this.overlay.hide();
};