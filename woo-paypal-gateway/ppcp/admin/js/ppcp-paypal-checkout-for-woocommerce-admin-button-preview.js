(function () {
  class WPGPayPalSmartButtonPreview {
    constructor(config = {}) {
      this.sectionMap = config.sectionMap || {
        product: 'woocommerce_wpg_paypal_checkout_product_button_settings',
        cart: 'woocommerce_wpg_paypal_checkout_cart_button_settings',
        express_checkout: 'woocommerce_wpg_paypal_checkout_express_checkout_button_settings',
        mini_cart: 'woocommerce_wpg_paypal_checkout_mini_cart_button_settings',
        checkout: 'woocommerce_wpg_paypal_checkout_checkout_button_settings',
      };

      this.contexts = Object.keys(this.sectionMap);
      this.previewIdPrefix = config.previewIdPrefix || 'wpg-ppcp-preview-';
      this.timers = {};

      // SDK single-load state (IMPORTANT: no reload = no zoid error)
      this.sdk = { loaded: false, loading: false, url: null, callbacks: [] };

      this.allowedDisableFunding = new Set([
        'paylater', 'credit', 'venmo', 'card',
        'ideal', 'bancontact', 'sepa', 'eps', 'p24', 'blik', 'trustly', 'mybank',
        'mercadopago', 'oxxo', 'boleto', 'boletobancario', 'multibanco', 'itau',
        'payu', 'satispay', 'wechatpay', 'paidy'
      ]);

      this.injectCssOnce();
      this.bind();
      this.bootstrap();
    }

    injectCssOnce() {
      if (document.getElementById('wpg-ppcp-preview-css')) return;

      const css = `
        .wpg-ppcp-preview-card{
          background: #fff;
          border: 1px solid #dcdcde;
          border-radius: 10px;
          padding: 14px 14px;
          position: relative;
        }
        .wpg-ppcp-preview-title{
          font-weight:600;
          margin-bottom:10px;
          color:#1d2327;
        }
        .wpg-ppcp-preview-desc{
          margin-top:10px;
          color:#646970;
          font-size:13px;
        }
            
       .wpg-ppcp-preview-col:empty{
        display:none !important;
      }

        .wpg-ppcp-preview-render{ width:100%; }

        /* width control via class on .wpg-ppcp-preview-render */
        .wpg-ppcp-preview-render.small{ max-width:300px; }
        .wpg-ppcp-preview-render.medium{ max-width:400px; }
        .wpg-ppcp-preview-render.large{ max-width:500px; }
        .wpg-ppcp-preview-render.responsive{ width:100%; max-width:600px; }

        /* overlay to block any click */
        .wpg-ppcp-preview-blocker{
          position:absolute; inset:0; z-index:50;
          cursor:not-allowed; background:transparent;
        }

        /* layout containers */
        .wpg-ppcp-preview-row{
          display:flex;
          gap:10px;
          align-items:flex-start;
          flex-wrap:wrap;
        }
        .wpg-ppcp-preview-col{
          flex:1 1 180px;
          min-width:160px;
        }
        .wpg-ppcp-preview-stack{
          display:flex;
          flex-direction:column;
          gap:10px;
        }

        /* hidden state */
        .wpg-ppcp-preview-hidden{ display:none !important; }
        .wpg-ppcp-preview-hidden{
            display:none !important;
          }
      `;

      const style = document.createElement('style');
      style.id = 'wpg-ppcp-preview-css';
      style.textContent = css;
      document.head.appendChild(style);
    }

    bind() {
      jQuery(document).on('click', 'h3.ppcp-collapsible-section', () => {
        const ctx = this.getActiveContext();
        if (ctx) this.schedule(ctx, 200);
      });

      jQuery(document).on('change input', 'table.form-table :input', (e) => {
        const ctx = this.getContextFromInput(e.target) || this.getActiveContext();
        if (!ctx) return;
        if (!this.isSectionActive(ctx)) return;
        if (!this.getPreviewEl(ctx)) return;
        this.schedule(ctx, 150);
      });

      jQuery(document).on('select2:select select2:unselect', 'select', (e) => {
        const ctx = this.getContextFromInput(e.target) || this.getActiveContext();
        if (!ctx) return;
        if (!this.isSectionActive(ctx)) return;
        if (!this.getPreviewEl(ctx)) return;
        this.schedule(ctx, 150);
      });

      // ✅ Prevent click inside preview render (what you asked)
      document.addEventListener(
        'click',
        (e) => {
          const el = e.target && e.target.closest && e.target.closest('.wpg-ppcp-preview-render');
          if (!el) return;
          e.preventDefault();
          e.stopPropagation();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          return false;
        },
        true
      );
    }

    bootstrap() {
      const ctx = this.getActiveContext();
      if (ctx) this.schedule(ctx, 200);
    }

    schedule(ctx, delay) {
      clearTimeout(this.timers[ctx]);
      this.timers[ctx] = setTimeout(() => this.render(ctx), delay);
    }

    getActiveContext() {
      const $h = jQuery('h3.ppcp-collapsible-section.active:visible').first();
      return this.getContextFromHeading($h);
    }

    getContextFromHeading($h) {
      const id = $h && $h.attr ? $h.attr('id') : null;
      if (!id) return null;
      return this.contexts.find((k) => this.sectionMap[k] === id) || null;
    }

    getContextFromInput(input) {
      const $table = jQuery(input).closest('table.form-table');
      if ($table.length) {
        const $h = $table.prevAll('h3.ppcp-collapsible-section').first();
        const ctx = this.getContextFromHeading($h);
        if (ctx) return ctx;
      }
      return this.getActiveContext();
    }

    isSectionActive(ctx) {
      const $h = jQuery(`#${this.sectionMap[ctx]}`);
      return $h.length && $h.hasClass('active') && $h.is(':visible');
    }

    getSectionTables(ctx) {
      const $h = jQuery(`#${this.sectionMap[ctx]}`);
      if (!$h.length) return jQuery();
      return $h.nextUntil('h3.ppcp-collapsible-section', 'table.form-table');
    }

    getPreviewEl(ctx) {
      return document.getElementById(this.previewIdPrefix + ctx);
    }

    // ✅ Checkout: if "use place order" enabled, hide preview
    isCheckoutUsePlaceOrderEnabled() {
      const el = document.getElementById('woocommerce_wpg_paypal_checkout_use_place_order');
      return !!(el && el.checked);
    }

    setPreviewVisible(previewEl, visible) {
        if (!previewEl) return;

        // hide/show preview container
        previewEl.classList.toggle('wpg-ppcp-preview-hidden', !visible);

        // ✅ ALSO hide/show closest table row
        const tr = previewEl.closest('tr');
        if (tr) {
          tr.classList.toggle('wpg-ppcp-preview-hidden', !visible);
        }
      }


    ensureStructure(previewEl) {
      if (previewEl.querySelector('.wpg-ppcp-preview-card')) return;

      
      

      previewEl.innerHTML = `
        <div class="wpg-ppcp-preview-card">
          
          <div class="wpg-ppcp-preview-render" data-wpg-render></div>
          <div class="wpg-ppcp-preview-blocker" title="Preview only"></div>
      
        </div>
      `;
    }

    esc(s) {
      return String(s || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    pickFromSection($tables, selectors, fallback = '') {
      for (const sel of selectors) {
        const $el = $tables.find(sel).first();
        if ($el.length) {
          const v = $el.val();
          if (v !== undefined && v !== null && (Array.isArray(v) ? v.length : String(v).length)) {
            return v;
          }
        }
      }
      return fallback;
    }

    normalizeSizeValue(v) {
      const raw = String(v || '').toLowerCase().trim();
      if (['small', 'medium', 'large', 'responsive'].includes(raw)) return raw;
      if (raw.includes('small')) return 'small';
      if (raw.includes('medium')) return 'medium';
      if (raw.includes('large')) return 'large';
      if (raw.includes('responsive') || raw.includes('full') || raw.includes('100')) return 'responsive';
      return 'responsive';
    }

    applySizeClass(renderEl, sizeVal) {
      renderEl.classList.remove('small', 'medium', 'large', 'responsive');
      renderEl.classList.add(this.normalizeSizeValue(sizeVal));
    }

    getButtonSize(ctx) {
      const $tables = this.getSectionTables(ctx);
      return this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_size`,
        `[id$="${ctx}_button_size"]`,
        `[id$="_button_size"]`,
        `[id$="_size"]`,
      ], 'responsive');
    }

    getButtonLayout(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_layout`,
        `[id$="${ctx}_button_layout"]`,
        `[id$="_button_layout"]`,
        `[id$="_layout"]`,
      ], 'horizontal');
      return String(v || '').toLowerCase().trim() === 'vertical' ? 'vertical' : 'horizontal';
    }

    getButtonColor(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_color`,
        `[id$="${ctx}_button_color"]`,
        `[id$="_button_color"]`,
        `[id$="_color"]`,
      ], 'gold');
      return String(v || 'gold').toLowerCase().trim() || 'gold';
    }

    getButtonShape(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_shape`,
        `[id$="${ctx}_button_shape"]`,
        `[id$="_button_shape"]`,
        `[id$="_shape"]`,
      ], 'rect');
      return String(v || '').toLowerCase().trim() === 'pill' ? 'pill' : 'rect';
    }

    getButtonLabel(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_label`,
        `[id$="${ctx}_button_label"]`,
        `[id$="_button_label"]`,
        `[id$="_label"]`,
      ], 'paypal');
      return String(v || 'paypal').toLowerCase().trim() || 'paypal';
    }

    getButtonHeight(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_height`,
        `[id$="${ctx}_button_height"]`,
        `[id$="_button_height"]`,
        `[id$="_height"]`,
      ], '45');
      const n = parseInt(v, 10);
      if (!Number.isFinite(n)) return 45;
      return Math.max(25, Math.min(55, n));
    }

    getButtonTagline(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_tagline`,
        `#woocommerce_wpg_paypal_checkout_${ctx}_button_show_tagline`,
        `[id$="${ctx}_button_tagline"]`,
        `[id$="${ctx}_button_show_tagline"]`,
        `[id$="_button_tagline"]`,
        `[id$="_show_tagline"]`,
        `[id$="_tagline"]`,
      ], '');

      if (typeof v === 'string') {
        const raw = v.toLowerCase().trim();
        if (raw === 'yes' || raw === '1' || raw === 'true' || raw === 'on') return true;
        if (raw === 'no' || raw === '0' || raw === 'false' || raw === 'off') return false;
      }
      return false;
    }

    getDisableFunding(ctx) {
      const $tables = this.getSectionTables(ctx);
      const v = this.pickFromSection($tables, [
        `#woocommerce_wpg_paypal_checkout_${ctx}_disallowed_funding_methods`,
        `[id$="${ctx}_disallowed_funding_methods"]`,
        `[id$="_disallowed_funding_methods"]`,
        `[id$="disallowed_funding_methods"]`,
      ], []);

      const arr = Array.isArray(v) ? v : (v ? [v] : []);
      const out = [];
      const seen = new Set();

      for (const x of arr) {
        const key = String(x || '').toLowerCase().trim().replace(/[^a-z0-9_-]/g, '');
        if (!key) continue;
        if (!this.allowedDisableFunding.has(key)) continue;
        if (seen.has(key)) continue;
        seen.add(key);
        out.push(key);
      }
      return out;
    }

    getCurrency(previewEl) {
      return previewEl.getAttribute('data-currency') || window.ppcp_param_preview?.store_currency || '';
    }

    getCountry() {
      return window.ppcp_param_preview?.store_country || '';
    }

    buildSdkUrl(currency) {
      const p = new URLSearchParams({
        'client-id': 'sb',
        components: 'buttons,funding-eligibility',
        intent: 'capture',
      });

      if (currency) p.set('currency', currency);

      const c = this.getCountry();
      if (c) p.set('buyer-country', c);

      return `https://www.paypal.com/sdk/js?${p.toString()}`;
    }

    loadSdkOnce(currency, cb) {
      const url = this.buildSdkUrl(currency);

      if (this.sdk.loaded && this.sdk.url === url && window.paypal && window.paypal.Buttons) {
        cb(true);
        return;
      }

      this.sdk.callbacks.push(cb);
      if (this.sdk.loading) return;

      this.sdk.loading = true;
      this.sdk.loaded = false;
      this.sdk.url = url;

      if (!document.querySelector('script[data-wpg-preview-sdk="1"]')) {
        const s = document.createElement('script');
        s.src = url;
        s.async = true;
        s.dataset.wpgPreviewSdk = '1';

        s.onload = () => {
          this.sdk.loading = false;
          this.sdk.loaded = !!(window.paypal && window.paypal.Buttons);
          const cbs = this.sdk.callbacks.splice(0);
          cbs.forEach(fn => { try { fn(this.sdk.loaded); } catch (e) {} });
        };

        s.onerror = () => {
          this.sdk.loading = false;
          this.sdk.loaded = false;
          const cbs = this.sdk.callbacks.splice(0);
          cbs.forEach(fn => { try { fn(false); } catch (e) {} });
        };

        document.body.appendChild(s);
      }
    }

    getAllPossibleFundingConsts(paypalObj) {
      if (paypalObj && typeof paypalObj.getFundingSources === 'function') {
        try {
          const arr = paypalObj.getFundingSources();
          if (Array.isArray(arr) && arr.length) return arr;
        } catch (e) {}
      }

      const FUNDING = paypalObj && paypalObj.FUNDING ? paypalObj.FUNDING : null;
      if (!FUNDING) return [];
      const vals = [];
      for (const k in FUNDING) {
        if (Object.prototype.hasOwnProperty.call(FUNDING, k)) vals.push(FUNDING[k]);
      }
      return Array.from(new Set(vals));
    }

    fundingConstToDisableKey(paypalObj, fundingConst) {
      const FUNDING = paypalObj && paypalObj.FUNDING ? paypalObj.FUNDING : null;
      if (!FUNDING) return '';

      if (fundingConst === FUNDING.PAYPAL) return 'paypal';
      if (fundingConst === FUNDING.CARD) return 'card';
      if (fundingConst === FUNDING.CREDIT) return 'credit';
      if (fundingConst === (FUNDING.PAYLATER || FUNDING.PAY_LATER)) return 'paylater';
      if (fundingConst === FUNDING.VENMO) return 'venmo';

      return String(fundingConst || '').toLowerCase();
    }

    render(ctx) {
      const previewEl = this.getPreviewEl(ctx);
      if (!previewEl) return;
      if (!this.isSectionActive(ctx)) return;

      // ✅ checkout: if use_place_order enabled, hide preview and stop
      if (ctx === 'checkout' && this.isCheckoutUsePlaceOrderEnabled()) {
        this.setPreviewVisible(previewEl, false);
        return;
      }
      // otherwise ensure visible
      this.setPreviewVisible(previewEl, true);

      this.ensureStructure(previewEl);

      const renderEl = previewEl.querySelector('[data-wpg-render]');
      if (!renderEl) return;

      this.applySizeClass(renderEl, this.getButtonSize(ctx));

      const layoutVal = this.getButtonLayout(ctx);
      const colorVal = this.getButtonColor(ctx);
      const shapeVal = this.getButtonShape(ctx);
      const labelVal = this.getButtonLabel(ctx);
      const heightVal = this.getButtonHeight(ctx);
      const taglineVal = this.getButtonTagline(ctx);

      const disallowed = new Set(this.getDisableFunding(ctx));

      renderEl.innerHTML = '';

      const currency = this.getCurrency(previewEl);

      this.loadSdkOnce(currency, (ok) => {
        if (!ok || !window.paypal || !window.paypal.Buttons) {
          renderEl.innerHTML = '<em style="color:#b32d2e;">Preview unavailable (PayPal SDK not loaded).</em>';
          return;
        }

        const allFundingConsts = this.getAllPossibleFundingConsts(window.paypal);
        const fallback = [
          window.paypal.FUNDING && window.paypal.FUNDING.PAYPAL,
          window.paypal.FUNDING && (window.paypal.FUNDING.PAYLATER || window.paypal.FUNDING.PAY_LATER),
          window.paypal.FUNDING && window.paypal.FUNDING.VENMO,
          window.paypal.FUNDING && window.paypal.FUNDING.CREDIT,
          window.paypal.FUNDING && window.paypal.FUNDING.CARD,
        ].filter(Boolean);

        const list = (allFundingConsts && allFundingConsts.length) ? allFundingConsts : fallback;

        const renderList = list.filter((fc) => {
          const key = this.fundingConstToDisableKey(window.paypal, fc);
          if (!key) return true;
          return !disallowed.has(key);
        });

        const container = document.createElement('div');
        container.className = (layoutVal === 'vertical') ? 'wpg-ppcp-preview-stack' : 'wpg-ppcp-preview-row';
        renderEl.appendChild(container);

        const promises = [];

        for (const fundingConst of renderList) {
          const col = document.createElement('div');
          col.className = (layoutVal === 'vertical') ? '' : 'wpg-ppcp-preview-col';
          container.appendChild(col);

          const opts = {
            fundingSource: fundingConst,
            style: {
              layout: layoutVal,
              color: colorVal,
              shape: shapeVal,
              label: labelVal,
              height: heightVal,
              tagline: !!taglineVal,
            },
            onClick: (data, actions) => {
              try { return actions.reject(); } catch (e) {}
              return false;
            },
            createOrder: () => { throw new Error('Preview only'); },
            onError: () => {},
          };

          // ✅ remove empty wrapper if render fails to avoid whitespace
          promises.push(
            window.paypal.Buttons(opts)
              .render(col)
              .catch(() => { try { col.remove(); } catch (e) {} })
          );
        }

        Promise.allSettled(promises).then(() => {});
      });
    }
  }

  jQuery(() => new WPGPayPalSmartButtonPreview());
  window.WPGPayPalSmartButtonPreview = WPGPayPalSmartButtonPreview;
})();
