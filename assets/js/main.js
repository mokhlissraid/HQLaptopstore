/**
 * HQ Laptop — الدفع (checkout) + واجهة الدفع الإلكتروني التجريبية
 */
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('checkout-form');
  if (!form || typeof HQCart === 'undefined') return;

  var errEl = document.getElementById('checkout-error');
  var submitBtn = document.getElementById('checkout-submit');
  var onlineBlock = document.getElementById('online-pay-block');
  var cardNumber = document.getElementById('card_number');
  var cardHolder = document.getElementById('card_holder');
  var cardExpiry = document.getElementById('card_expiry');
  var cardCvc = document.getElementById('card_cvc');
  var linesEl = document.getElementById('checkout-lines');
  var totalEl = document.getElementById('checkout-total');
  var toastHost = null;
  var toastStyleInjected = false;
  var recentToastMap = {};

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showError(msgOrList) {
    if (!errEl) return;
    if (Array.isArray(msgOrList) && msgOrList.length > 0) {
      errEl.innerHTML =
        '<ul class="list-disc space-y-1 pr-5">' +
        msgOrList
          .map(function (m) {
            return '<li>' + escapeHtml(m) + '</li>';
          })
          .join('') +
        '</ul>';
      errEl.classList.remove('hidden');
      return;
    }
    var msg = typeof msgOrList === 'string' ? msgOrList : '';
    errEl.textContent = msg;
    errEl.classList.toggle('hidden', !msg);
  }

  function ensureToastHost() {
    if (toastHost) return toastHost;
    toastHost = document.createElement('div');
    toastHost.className =
      'fixed top-4 left-1/2 z-50 w-[min(92vw,420px)] -translate-x-1/2 space-y-2 pointer-events-none';
    document.body.appendChild(toastHost);
    return toastHost;
  }

  function ensureToastStyle() {
    if (toastStyleInjected) return;
    var style = document.createElement('style');
    style.textContent =
      '.hq-toast{opacity:0;transform:translateY(-8px) scale(.98);transition:opacity .22s ease,transform .22s ease}' +
      '.hq-toast.show{opacity:1;transform:translateY(0) scale(1)}';
    document.head.appendChild(style);
    toastStyleInjected = true;
  }

  function cleanupRecentToasts() {
    var now = Date.now();
    Object.keys(recentToastMap).forEach(function (k) {
      if (now - recentToastMap[k] > 5000) delete recentToastMap[k];
    });
  }

  function showToast(message, type, delay) {
    var msg = String(message || '').trim();
    if (!msg) return;
    cleanupRecentToasts();
    var key = (type || 'info') + '::' + msg;
    if (recentToastMap[key]) return;
    recentToastMap[key] = Date.now();

    ensureToastStyle();
    var host = ensureToastHost();
    var isSuccess = type === 'success';
    var toast = document.createElement('div');
    toast.className =
      'hq-toast pointer-events-auto rounded-2xl border px-4 py-3 shadow-xl backdrop-blur ' +
      (isSuccess
        ? 'border-emerald-200/70 bg-emerald-50/90 text-emerald-900'
        : 'border-amber-200/70 bg-white/95 text-brand-dark shadow-brand-purple/10');

    toast.innerHTML =
      '<div class="flex items-start gap-3">' +
      '<span class="mt-0.5 text-sm font-bold ' +
      (isSuccess ? 'text-emerald-700' : 'text-brand-purple') +
      '">' +
      (isSuccess ? '✓' : '!') +
      '</span>' +
      '<p class="min-w-0 flex-1 text-sm leading-relaxed"></p>' +
      '<button type="button" class="rounded-lg px-2 py-0.5 text-xs text-brand-dark/60 hover:bg-black/5 hover:text-brand-dark">إغلاق</button>' +
      '</div>';

    var p = toast.querySelector('p');
    if (p) p.textContent = msg;
    var closeBtn = toast.querySelector('button');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        toast.classList.remove('show');
        setTimeout(function () {
          if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 220);
      });
    }

    host.appendChild(toast);
    setTimeout(function () {
      toast.classList.add('show');
    }, Math.max(0, delay || 0));

    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 220);
    }, Math.max(3500, (delay || 0) + 3500));
  }

  function showStockToasts(messages, adjusted) {
    if (!Array.isArray(messages) || messages.length === 0) return;
    if (adjusted) {
      showToast('تم تعديل الكميات تلقائيا حسب المخزون المتاح حاليا.', 'success', 0);
    }
    messages.forEach(function (m, i) {
      showToast(m, 'warning', adjusted ? 220 + i * 180 : i * 180);
    });
  }

  function extractStockMessagesFromText(msg) {
    var text = String(msg || '').trim();
    if (!text) return [];

    var lines = text
      .split(/\r?\n+/)
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean);
    if (lines.length > 1 && lines.some(function (l) { return l.indexOf('المنتج "') === 0; })) {
      return lines;
    }

    var matched = text.match(/المنتج\s+"[^"]+"\s+متوفر منه حاليا\s+\d+\s+فقط/g);
    if (matched && matched.length > 0) {
      return matched;
    }

    return [];
  }

  function renderCheckoutSummary(cart) {
    if (!linesEl || !totalEl) return;
    linesEl.innerHTML = cart
      .map(function (line) {
        var qty = line.qty || 1;
        var sub = (Number(line.price) || 0) * qty;
        return (
          '<li class="flex justify-between gap-4">' +
          '<span class="text-brand-dark/80">' +
          HQCart.escapeHtml(line.name) +
          ' × ' +
          qty +
          '</span>' +
          '<span class="font-semibold text-brand-purple shrink-0">' +
          HQCart.formatPrice(sub) +
          '</span>' +
          '</li>'
        );
      })
      .join('');
    totalEl.textContent = HQCart.formatPrice(HQCart.grandTotal(cart));
  }

  function fetchStocksForCart(cart) {
    var ids = [];
    cart.forEach(function (line) {
      var id = Number(line.id);
      if (id > 0 && ids.indexOf(id) === -1) ids.push(id);
    });
    if (ids.length === 0) return Promise.resolve({});
    return fetch('backend/get_cart_stock.php?ids=' + encodeURIComponent(ids.join(',')))
      .then(function (res) {
        if (!res.ok) throw new Error('stock_fetch_failed');
        return res.json();
      })
      .then(function (data) {
        if (!data || data.ok !== true || typeof data.stocks !== 'object') return {};
        return data.stocks;
      });
  }

  function validateAndAdjustCartStock(cart) {
    return fetchStocksForCart(cart)
      .then(function (stocks) {
        var next = cart.map(function (line) {
          return Object.assign({}, line);
        });
        var violations = [];
        var adjusted = false;

        next.forEach(function (line) {
          var pid = Number(line.id);
          var requested = Number(line.qty) || 1;
          if (!Number.isFinite(requested) || requested < 1) requested = 1;
          requested = Math.floor(requested);
          line.qty = requested;

          var stockRaw = Object.prototype.hasOwnProperty.call(stocks, String(pid))
            ? Number(stocks[String(pid)])
            : null;
          if (stockRaw === null || !Number.isFinite(stockRaw)) return;

          var available = Math.floor(stockRaw);
          if (available < 0) available = 0;

          if (requested > available) {
            violations.push(
              'المنتج "' + String(line.name || '') + '" متوفر منه حاليا ' + available + ' فقط'
            );
            line.qty = available > 0 ? available : 1;
            adjusted = true;
          }
        });

        if (adjusted) {
          next = next.filter(function (line) {
            return (Number(line.qty) || 0) > 0;
          });
          HQCart.setCart(next);
          renderCheckoutSummary(next);
        }

        return {
          cart: adjusted ? next : cart,
          violations: violations,
          adjusted: adjusted,
        };
      })
      .catch(function () {
        return {
          cart: cart,
          violations: [],
          adjusted: false,
          stockCheckFailed: true,
        };
      });
  }

  function digitsOnly(s) {
    return String(s || '').replace(/\D/g, '');
  }

  function setOnlineFieldsRequired(isOnline) {
    [cardNumber, cardHolder, cardExpiry, cardCvc].forEach(function (el) {
      if (el) el.required = !!isOnline;
    });
  }

  function syncOnlineBlock() {
    if (!onlineBlock) return;
    var checked = form.querySelector('input[name="payment_method"]:checked');
    var isOnline = checked && checked.value === 'online';
    onlineBlock.classList.toggle('hidden', !isOnline);
    setOnlineFieldsRequired(isOnline);
  }

  form.querySelectorAll('input[name="payment_method"]').forEach(function (r) {
    r.addEventListener('change', syncOnlineBlock);
  });
  syncOnlineBlock();

  if (cardExpiry) {
    cardExpiry.addEventListener('input', function () {
      var v = digitsOnly(cardExpiry.value).slice(0, 4);
      cardExpiry.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
    });
  }

  /** MM/YY — أربعة أرقام بعد إزالة الشرطة */
  function validateExpiry(raw) {
    var d = digitsOnly(raw);
    if (d.length !== 4) return false;
    var mm = parseInt(d.slice(0, 2), 10);
    return mm >= 1 && mm <= 12;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    showError('');

    var cart = HQCart.getCart();
    if (cart.length === 0) {
      showError('السلة فارغة.');
      return;
    }

    var name = (form.elements.namedItem('customer_name') || {}).value;
    var phone = (form.elements.namedItem('phone') || {}).value;
    var address = (form.elements.namedItem('address') || {}).value;
    name = name ? name.trim() : '';
    phone = phone ? phone.trim() : '';
    address = address ? address.trim() : '';

    if (name.length < 2) {
      showError('الرجاء إدخال الاسم.');
      return;
    }
    if (phone.length < 8) {
      showError('الرجاء إدخال رقم هاتف صالح.');
      return;
    }
    if (address.length < 5) {
      showError('الرجاء إدخال العنوان بشكل أوضح.');
      return;
    }

    var pmRadio = form.querySelector('input[name="payment_method"]:checked');
    var payment_method = pmRadio ? pmRadio.value : 'cod';

    if (payment_method === 'online') {
      var num = digitsOnly(cardNumber ? cardNumber.value : '');
      if (num.length < 16) {
        showError('أدخل رقم بطاقة صالحًا (16 رقمًا على الأقل).');
        return;
      }
      var holder = cardHolder ? cardHolder.value.trim() : '';
      if (holder.length < 2) {
        showError('أدخل الاسم كما يظهر على البطاقة.');
        return;
      }
      if (!validateExpiry(cardExpiry ? cardExpiry.value : '')) {
        showError('تاريخ الانتهاء بصيغة MM/YY (شهر صالح 01–12).');
        return;
      }
      var cvc = digitsOnly(cardCvc ? cardCvc.value : '');
      if (cvc.length < 3 || cvc.length > 4) {
        showError('أدخل رمز CVC (3 أو 4 أرقام).');
        return;
      }
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري الإرسال...';
    }

    validateAndAdjustCartStock(cart)
      .then(function (precheck) {
        if (precheck.stockCheckFailed) {
          throw new Error('stock_check_failed');
        }
        if (precheck.violations.length > 0) {
          showStockToasts(precheck.violations, precheck.adjusted);
          showError('');
          return null;
        }

        var finalCart = precheck.cart;
        if (!finalCart || finalCart.length === 0) {
          showError('السلة فارغة.');
          return null;
        }

        var items = finalCart.map(function (line) {
          return {
            product_id: Number(line.id),
            product_name: String(line.name),
            quantity: Number(line.qty) || 1,
            unit_price: Number(line.price),
          };
        });

        var payload = {
          customer_name: name,
          phone: phone,
          address: address,
          items: items,
          payment_method: payment_method,
        };

        return fetch('backend/create_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json; charset=UTF-8' },
          body: JSON.stringify(payload),
        });
      })
      .then(function (res) {
        if (!res) return null;
        return res.json().then(function (data) {
          return { ok: res.ok, status: res.status, data: data };
        });
      })
      .then(function (res) {
        if (!res) return;
        var result = res;
        if (result.ok && result.data && result.data.ok) {
          HQCart.setCart([]);
          window.location.href =
            'success.html?order=' + encodeURIComponent(String(result.data.order_id));
          return;
        }
        if (result.data && Array.isArray(result.data.errors) && result.data.errors.length > 0) {
          showStockToasts(result.data.errors, false);
          showError('');
          return;
        }

        var msg = (result.data && result.data.error) || 'تعذر إتمام الطلب. حاول مرة أخرى.';
        var stockMsgs = extractStockMessagesFromText(msg);
        if (stockMsgs.length > 0) {
          showStockToasts(stockMsgs, false);
          showError('');
          return;
        }
        showError(msg);
      })
      .catch(function (err) {
        if (err && err.message === 'stock_check_failed') {
          showError('تعذر التحقق من المخزون حاليا. حاول مرة أخرى.');
          return;
        }
        showError('خطأ في الاتصال بالخادم.');
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'تأكيد الطلب';
        }
      });
  });
});
