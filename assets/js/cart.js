/**
 * HQ Laptop — سلة التسوق (localStorage)
 * المفتاح ثابت حتى تتشارك كل الصفحات نفس البيانات.
 */
(function (global) {
  var STORAGE_KEY = 'hq_laptop_cart';

  function getCart() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      var c = raw ? JSON.parse(raw) : [];
      return Array.isArray(c) ? c : [];
    } catch (e) {
      return [];
    }
  }

  function setCart(cart) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
  }

  /**
   * يضيف منتجًا أو يزيد الكمية إن وُجد مسبقًا (نفس id).
   * @param {{ id: number, name: string, price: number, image?: string }} item
   */
  function addToCart(item) {
    var cart = getCart();
    var i = cart.findIndex(function (x) {
      return Number(x.id) === Number(item.id);
    });
    if (i >= 0) {
      cart[i].qty = (cart[i].qty || 1) + 1;
    } else {
      cart.push({
        id: Number(item.id),
        name: String(item.name),
        price: Number(item.price),
        image: item.image || 'assets/images/placeholder-laptop.svg',
        qty: 1,
      });
    }
    setCart(cart);
    return cart;
  }

  function formatPrice(n) {
    return (Math.round(Number(n) * 100) / 100).toFixed(2) + ' د.ج';
  }

  function grandTotal(cart) {
    return cart.reduce(function (sum, line) {
      return sum + (Number(line.price) || 0) * (line.qty || 1);
    }, 0);
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  /** صفحة السلة: رسم القائمة والأزرار */
  function initCartPage() {
    var emptyEl = document.getElementById('cart-empty');
    var listEl = document.getElementById('cart-list');
    var summaryEl = document.getElementById('cart-summary');
    var totalEl = document.getElementById('cart-total');
    var checkoutBtn = document.getElementById('cart-checkout-btn');
    if (!emptyEl || !listEl || !summaryEl || !totalEl) return;

    var stockByProduct = {};
    var toastTimer = null;
    var toastEl = null;

    function ensureToast() {
      if (toastEl) return toastEl;
      toastEl = document.createElement('div');
      toastEl.className =
        'pointer-events-none fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 rounded-xl border border-brand-purple/25 bg-white/90 px-4 py-2 text-sm font-medium text-brand-dark shadow-lg shadow-brand-purple/15 backdrop-blur';
      document.body.appendChild(toastEl);
      return toastEl;
    }

    function showNotice(msg) {
      var el = ensureToast();
      el.textContent = msg;
      el.classList.remove('hidden');
      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(function () {
        el.classList.add('hidden');
      }, 2200);
    }

    function normalizeQty(n) {
      var v = Number(n);
      if (!Number.isFinite(v)) return 1;
      v = Math.floor(v);
      return v < 1 ? 1 : v;
    }

    function getLineStock(line) {
      if (!line) return null;
      var key = String(Number(line.id));
      if (!Object.prototype.hasOwnProperty.call(stockByProduct, key)) return null;
      var s = Number(stockByProduct[key]);
      if (!Number.isFinite(s)) return null;
      return s < 0 ? 0 : Math.floor(s);
    }

    function fetchStocks(cart) {
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
        })
        .catch(function () {
          return {};
        });
    }

    function clampCartToStock(cart, notify) {
      var changed = false;
      var next = [];
      cart.forEach(function (line) {
        var copy = Object.assign({}, line);
        copy.qty = normalizeQty(copy.qty);
        var stock = getLineStock(copy);

        if (stock !== null) {
          if (stock < 1) {
            changed = true;
            if (notify) {
              showNotice('المنتج "' + String(copy.name || '') + '" غير متوفر حاليا وتمت إزالته من السلة');
            }
            return;
          }
          if (copy.qty > stock) {
            changed = true;
            copy.qty = stock;
            if (notify) {
              showNotice('الحد الأقصى المتوفر حاليا هو ' + stock);
            }
          }
        }

        next.push(copy);
      });

      if (changed) setCart(next);
      return next;
    }

    function render() {
      var cart = clampCartToStock(getCart(), false);
      if (cart.length === 0) {
        emptyEl.classList.remove('hidden');
        listEl.classList.add('hidden');
        summaryEl.classList.add('hidden');
        if (checkoutBtn) checkoutBtn.classList.add('hidden');
        listEl.innerHTML = '';
        return;
      }
      emptyEl.classList.add('hidden');
      listEl.classList.remove('hidden');
      summaryEl.classList.remove('hidden');
      if (checkoutBtn) checkoutBtn.classList.remove('hidden');

      var total = 0;
      listEl.innerHTML = cart
        .map(function (line, idx) {
          var qty = line.qty || 1;
          var sub = (Number(line.price) || 0) * qty;
          total += sub;
          var img = line.image || 'assets/images/placeholder-laptop.svg';
          var safeImg = String(img).replace(/"/g, '&quot;');
          var stock = getLineStock(line);
          var hasStock = stock !== null;
          var plusDisabled = hasStock && qty >= stock;
          var stockHint = hasStock
            ? '<p class="mt-1 text-xs text-brand-dark/55">المتوفر حاليا: ' + stock + '</p>'
            : '';
          return (
            '<article class="glass-panel flex gap-4 rounded-2xl border border-white/50 p-4">' +
              '<a href="product.php?id=' +
              line.id +
              '" class="h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-brand-light/80">' +
              '<img src="' +
              safeImg +
              '" alt="" class="h-full w-full object-cover">' +
              '</a>' +
              '<div class="min-w-0 flex-1">' +
              '<a href="product.php?id=' +
              line.id +
              '" class="font-semibold text-brand-dark hover:text-brand-purple">' +
              escapeHtml(line.name) +
              '</a>' +
              '<p class="mt-1 text-sm text-brand-purple font-bold">' +
              formatPrice(line.price) +
              '</p>' +
              stockHint +
              '<div class="mt-3 flex flex-wrap items-center gap-2">' +
              '<button type="button" data-act="minus" data-i="' +
              idx +
              '" class="rounded-lg border border-brand-dark/20 px-2 py-1 text-sm hover:bg-white/60">−</button>' +
              '<input type="number" inputmode="numeric" min="1" ' +
              (hasStock ? 'max="' + stock + '" ' : '') +
              'value="' +
              qty +
              '" data-act="set" data-i="' +
              idx +
              '" class="w-16 rounded-lg border border-brand-dark/20 bg-white/70 px-2 py-1 text-center text-sm focus:border-brand-purple focus:outline-none">' +
              '<button type="button" data-act="plus" data-i="' +
              idx +
              '"' +
              (plusDisabled ? ' disabled' : '') +
              ' class="rounded-lg border border-brand-dark/20 px-2 py-1 text-sm hover:bg-white/60 disabled:cursor-not-allowed disabled:opacity-40">+</button>' +
              '<button type="button" data-act="remove" data-i="' +
              idx +
              '" class="mr-auto text-xs text-red-700 hover:underline">إزالة</button>' +
              '</div></div></article>'
          );
        })
        .join('');

      totalEl.textContent = formatPrice(total);

      listEl.querySelectorAll('button[data-act]').forEach(function (b) {
        b.addEventListener('click', function () {
          var i = parseInt(b.getAttribute('data-i'), 10);
          var act = b.getAttribute('data-act');
          var next = getCart().slice();
          if (!next[i]) return;
          var stock = getLineStock(next[i]);
          if (act === 'remove') next.splice(i, 1);
          else if (act === 'plus') {
            var current = normalizeQty(next[i].qty);
            if (stock !== null && current >= stock) {
              showNotice('الحد الأقصى المتوفر حاليا هو ' + stock);
              return;
            }
            next[i].qty = current + 1;
          }
          else if (act === 'minus') {
            next[i].qty = normalizeQty(next[i].qty) - 1;
            if (next[i].qty < 1) next.splice(i, 1);
          }
          setCart(next);
          render();
        });
      });

      listEl.querySelectorAll('input[data-act="set"]').forEach(function (input) {
        input.addEventListener('input', function () {
          var i = parseInt(input.getAttribute('data-i'), 10);
          var cartNow = getCart();
          if (!cartNow[i]) return;
          var stock = getLineStock(cartNow[i]);
          var v = input.value.replace(/\D/g, '');
          if (v === '') return;
          var n = parseInt(v, 10);
          if (!Number.isFinite(n)) return;
          if (stock !== null && n > stock) {
            input.value = String(stock);
            showNotice('الحد الأقصى المتوفر حاليا هو ' + stock);
          }
        });

        input.addEventListener('change', function () {
          var i = parseInt(input.getAttribute('data-i'), 10);
          var next = getCart().slice();
          if (!next[i]) return;
          var stock = getLineStock(next[i]);
          var n = normalizeQty(input.value);
          if (stock !== null && n > stock) {
            n = stock;
            showNotice('الحد الأقصى المتوفر حاليا هو ' + stock);
          }
          if (n < 1) n = 1;
          next[i].qty = n;
          setCart(next);
          render();
        });
      });
    }

    function bootstrap() {
      var cart = getCart();
      fetchStocks(cart).then(function (stocks) {
        stockByProduct = stocks || {};
        var adjusted = clampCartToStock(getCart(), true);
        if (adjusted.length !== cart.length) {
          cart = adjusted;
        }
        render();
      });
    }

    bootstrap();
  }

  global.HQCart = {
    STORAGE_KEY: STORAGE_KEY,
    getCart: getCart,
    setCart: setCart,
    addToCart: addToCart,
    formatPrice: formatPrice,
    grandTotal: grandTotal,
    escapeHtml: escapeHtml,
    initCartPage: initCartPage,
  };
})(typeof window !== 'undefined' ? window : this);
