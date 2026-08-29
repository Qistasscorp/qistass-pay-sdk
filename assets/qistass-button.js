/**
 * Qistass Pay — drop-in checkout button.
 *
 * Zero dependencies. Works on any site (WordPress, Salla/Zid, custom PHP,
 * static HTML with a small backend, anything).
 *
 * SECURITY NOTE: this widget never talks to Qistass Pay's API directly —
 * secret_key must never be exposed in browser code. Instead it POSTs to an
 * endpoint on *your own* server (one you build using the PHP SDK's
 * createPaymentOrder()), which returns { redirect_url }, and this widget
 * takes the customer there. Same pattern Stripe/PayPal use for hosted
 * checkout — the SDK just makes it feel like a one-line drop-in.
 *
 * DEFAULT MODE IS A POPUP WINDOW, not a full-page redirect — matching the
 * PayPal-style checkout experience (customer never leaves your page). This
 * is deliberately a real popup window, not an <iframe> — Qistass Pay's
 * checkout page sends `X-Frame-Options: SAMEORIGIN`, so embedding it in an
 * iframe from your own domain will never work, by design (anti-clickjacking).
 * Set `mode: 'redirect'` to fall back to a full-page navigation instead.
 *
 * Usage:
 *   <div id="qistass-checkout"></div>
 *   <link rel="stylesheet" href="qistass-button.css">
 *   <script src="qistass-button.js"></script>
 *   <script>
 *     QistassPay.renderButton('#qistass-checkout', {
 *       endpoint: '/your-backend-route',   // calls createPaymentOrder() server-side
 *       label: 'ادفع عبر قسطاس باي',
 *       data: { order_id: 'ORD-2026-0142' }, // anything your endpoint needs to identify the order
 *       onReturn: function (returnedUrl) {
 *         // Popup closed (or navigated back to your own domain). ALWAYS verify
 *         // server-side before trusting this — call your backend's isPaid() check.
 *       }
 *     });
 *   </script>
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.QistassPay = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var LOGO_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M4 12l6 6L20 6"/></svg>';

  function createEl(tag, className, html) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (html !== undefined) el.innerHTML = html;
    return el;
  }

  /**
   * Opens a centered popup window immediately (must be called synchronously
   * from the click handler — browsers block window.open() calls that happen
   * after an async gap like a fetch, treating them as unrequested popups).
   * Starts on about:blank; call setPopupUrl() once the real URL is known.
   */
  function openPopup(w, h) {
    w = w || 480;
    h = h || 720;
    var left = (window.screenX || window.screenLeft || 0) + ((window.outerWidth || screen.width) - w) / 2;
    var top = (window.screenY || window.screenTop || 0) + ((window.outerHeight || screen.height) - h) / 2;
    try {
      return window.open(
        'about:blank',
        'qistass_pay_checkout',
        'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
        ',resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no'
      );
    } catch (e) {
      return null;
    }
  }

  /**
   * Polls the popup until it closes, or (once it navigates back to your own
   * domain via callback_url) until we can read its location — whichever
   * happens first. Reading .location on a still-cross-origin popup (i.e.
   * still on pay.qistass.com) throws, which is expected and just means
   * "keep waiting" — this is the standard cross-origin popup-polling
   * pattern, same category of technique Stripe/PayPal-style popups use.
   */
  function watchPopup(popup, onReturn) {
    var timer = setInterval(function () {
      var finished = false;
      var returnedUrl = null;

      if (!popup || popup.closed) {
        finished = true;
      } else {
        try {
          var href = popup.location.href;
          if (href && href.indexOf(window.location.origin) === 0) {
            returnedUrl = href;
            popup.close();
            finished = true;
          }
        } catch (e) {
          // Still on pay.qistass.com (cross-origin) — not finished yet.
        }
      }

      if (finished) {
        clearInterval(timer);
        if (typeof onReturn === 'function') {
          onReturn(returnedUrl); // null if the customer just closed the popup
        }
      }
    }, 500);

    return timer;
  }

  /**
   * @param {string|HTMLElement} target CSS selector or element to render into.
   * @param {Object} options
   * @param {string} options.endpoint     Your backend URL that creates the order and returns { redirect_url }.
   * @param {string} [options.label]      Button text. Defaults to Arabic "ادفع عبر قسطاس باي".
   * @param {Object} [options.data]       Extra fields POSTed to your endpoint as JSON (order id, amount, etc.).
   * @param {string} [options.method]     HTTP method for the request to your endpoint. Default 'POST'.
   * @param {'popup'|'redirect'} [options.mode] Default 'popup'. 'redirect' falls back to the old
   *                                      full-page navigation. Popup mode auto-falls-back to redirect
   *                                      if the browser blocks the popup (e.g. some in-app webviews).
   * @param {Function} [options.onReturn] Popup mode only. Called with (returnedUrl|null) once the popup
   *                                      closes or returns to your domain. ALWAYS re-verify server-side —
   *                                      this never means the payment actually succeeded on its own.
   * @param {Function} [options.onError]  Called with (Error) if the request to your endpoint fails
   *                                      or doesn't return a redirect_url. If omitted, a default
   *                                      inline error message is shown under the button.
   * @param {Function} [options.beforeRedirect] Redirect mode only. Called with (redirect_url) right
   *                                      before navigating — return false to cancel it yourself.
   */
  function renderButton(target, options) {
    options = options || {};
    if (!options.endpoint) {
      throw new Error('QistassPay.renderButton: options.endpoint is required.');
    }

    var container = typeof target === 'string' ? document.querySelector(target) : target;
    if (!container) {
      throw new Error('QistassPay.renderButton: target element not found: ' + target);
    }

    var label = options.label || 'ادفع عبر قسطاس باي';
    var method = options.method || 'POST';
    var mode = options.mode || 'popup';

    var btn = createEl('button', 'qistass-pay-btn');
    btn.type = 'button';
    var logo = createEl('span', 'qistass-pay-btn__logo', LOGO_SVG);
    var spinner = createEl('span', 'qistass-pay-btn__spinner');
    spinner.style.display = 'none';
    var text = createEl('span', 'qistass-pay-btn__label', label);
    btn.appendChild(logo);
    btn.appendChild(spinner);
    btn.appendChild(text);

    var errorBox = createEl('div', 'qistass-pay-btn__error');
    errorBox.style.display = 'none';

    container.innerHTML = '';
    container.appendChild(btn);
    container.appendChild(errorBox);

    // Toggles visibility rather than destructively replacing the logo's
    // outerHTML — the old implementation only ever swapped logo->spinner
    // and never back, which was invisible in the original redirect-only
    // design (the whole page navigated away right after) but becomes a
    // real, visible stuck-spinner bug in popup mode, where the button stays
    // on screen after loading finishes.
    function setLoading(isLoading) {
      btn.disabled = isLoading;
      logo.style.display = isLoading ? 'none' : '';
      spinner.style.display = isLoading ? '' : 'none';
    }

    function showError(message) {
      if (typeof options.onError === 'function') {
        options.onError(new Error(message));
        return;
      }
      errorBox.textContent = message;
      errorBox.style.display = 'block';
    }

    btn.addEventListener('click', function () {
      errorBox.style.display = 'none';

      // Must open synchronously, before the fetch below, or the browser's
      // popup blocker will treat it as an unrequested popup and kill it.
      var popup = mode === 'popup' ? openPopup() : null;
      var usingPopup = mode === 'popup' && !!popup;

      setLoading(true);

      fetch(options.endpoint, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(options.data || {})
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error('الخادم أعاد الحالة ' + res.status);
          }
          return res.json();
        })
        .then(function (json) {
          if (!json || !json.redirect_url) {
            throw new Error('لم يصل رابط دفع صالح من الخادم.');
          }

          if (usingPopup) {
            setLoading(false);
            popup.location.href = json.redirect_url;
            popup.focus();
            watchPopup(popup, options.onReturn);
            return;
          }

          // Redirect mode (either requested, or popup was blocked).
          if (typeof options.beforeRedirect === 'function') {
            var shouldContinue = options.beforeRedirect(json.redirect_url);
            if (shouldContinue === false) {
              setLoading(false);
              return;
            }
          }
          window.location.href = json.redirect_url;
        })
        .catch(function (err) {
          if (popup && !popup.closed) popup.close();
          setLoading(false);
          showError('تعذّر بدء عملية الدفع: ' + err.message);
        });
    });

    return btn;
  }

  return { renderButton: renderButton };
});
