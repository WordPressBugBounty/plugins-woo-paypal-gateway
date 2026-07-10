(function () {
    'use strict';

    var rendered = false;
    var observer = null;

    function getContainer() {
        return document.querySelector('[data-wpg-minicart="1"]');
    }

    function isDrawerOpen() {
        var drawer = document.querySelector('.wc-block-mini-cart__drawer');
        if (!drawer) {
            return false;
        }
        return drawer.classList.contains('is-active') || drawer.getAttribute('aria-hidden') === 'false';
    }

    function triggerRender() {
        if (rendered) {
            return;
        }
        var container = getContainer();
        if (!container) {
            return;
        }
        rendered = true;

        document.body.dispatchEvent(new CustomEvent('ppcp_minicart_ready', { bubbles: true }));
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).trigger('ppcp_minicart_ready');
        }
    }

    function triggerUpdate() {
        document.body.dispatchEvent(new CustomEvent('ppcp_minicart_updated', { bubbles: true }));
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).trigger('ppcp_minicart_updated');
        }
    }

    function startObserving() {
        if (observer) {
            return;
        }

        var target = document.body;
        observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (isDrawerOpen()) {
                        triggerRender();
                        return;
                    }
                }
                if (mutation.type === 'attributes' && mutation.attributeName === 'aria-hidden') {
                    if (isDrawerOpen()) {
                        triggerRender();
                        return;
                    }
                }
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var node = mutation.addedNodes[j];
                        if (node.nodeType === 1 && (node.classList.contains('wc-block-mini-cart__drawer') || node.querySelector('.wc-block-mini-cart__drawer'))) {
                            if (isDrawerOpen()) {
                                triggerRender();
                                return;
                            }
                        }
                    }
                }
            }
        });

        observer.observe(target, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['class', 'aria-hidden']
        });
    }

    if (typeof wp !== 'undefined' && wp.hooks && wp.hooks.addAction) {
        wp.hooks.addAction(
            'experimental__woocommerce_blocks-cart-update-cart-item',
            'wpg-ppcp-minicart',
            function () {
                rendered = false;
                triggerUpdate();
            }
        );
    }

    document.addEventListener('DOMContentLoaded', function () {
        startObserving();

        if (isDrawerOpen()) {
            triggerRender();
        }
    });
})();
