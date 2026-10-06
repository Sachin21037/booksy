/**
 * Booksy - Interactive JavaScript Enhancements
 * Concept UI: Automatic Hero Carousel, Header Search Pill with Live AJAX Autocomplete,
 * Rich Wishlist System, Toast Notifications, List/Grid View Switcher, Promo Codes, Drag & Drop Upload
 */

// ============================================================================
// 1. Global Toast Notification System
// ============================================================================
function showToast(type, title, message) {
  let container = document.getElementById('booksyToastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'booksyToastContainer';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `booksy-toast toast-${type}`;

  const iconClass = type === 'success' ? 'bi-check-circle-fill text-success' :
                    (type === 'danger' ? 'bi-exclamation-octagon-fill text-danger' :
                    (type === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-info-circle-fill text-info'));

  toast.innerHTML = `
    <i class="bi ${iconClass} fs-4"></i>
    <div class="booksy-toast-content">
      <div class="booksy-toast-title">${title}</div>
      <div>${message}</div>
    </div>
    <button type="button" class="booksy-toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"></div>
  `;

  const closeBtn = toast.querySelector('.booksy-toast-close');
  closeBtn.addEventListener('click', () => {
    toast.remove();
  });

  container.appendChild(toast);

  setTimeout(() => {
    if (toast.parentNode) {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }
  }, 4000);
}

// ============================================================================
// 2. Global Header Category Selector
// ============================================================================
function selectHeaderCategory(slug, name) {
  const hiddenInput = document.getElementById('headerSearchCategoryInput');
  const labelSpan = document.getElementById('headerSelectedCatLabel');
  if (hiddenInput) hiddenInput.value = slug;
  if (labelSpan) labelSpan.textContent = name;
  
  // Re-trigger live search if input has text
  const searchInput = document.querySelector('.search-input');
  if (searchInput && searchInput.value.trim().length >= 2) {
    searchInput.dispatchEvent(new Event('input'));
  }
}

// ============================================================================
// 3. Live Search Autocomplete (Debounced AJAX query)
// ============================================================================
let liveSearchDebounceTimer = null;
let currentHighlightedIndex = -1;

function initLiveSearch() {
  const searchForm = document.getElementById('headerSearchForm');
  const searchInput = searchForm ? searchForm.querySelector('.search-input') : null;
  if (!searchForm || !searchInput) return;

  // Create or get live search dropdown container
  let dropdown = searchForm.querySelector('.live-search-dropdown');
  if (!dropdown) {
    dropdown = document.createElement('div');
    dropdown.className = 'live-search-dropdown';
    searchForm.appendChild(dropdown);
  }

  searchInput.addEventListener('input', function () {
    const query = this.value.trim();
    const catInput = document.getElementById('headerSearchCategoryInput');
    const category = catInput ? catInput.value.trim() : '';

    clearTimeout(liveSearchDebounceTimer);
    currentHighlightedIndex = -1;

    if (query.length < 2) {
      dropdown.classList.remove('show');
      dropdown.innerHTML = '';
      return;
    }

    liveSearchDebounceTimer = setTimeout(() => {
      fetch(`api/search-suggest.php?q=${encodeURIComponent(query)}&cat=${encodeURIComponent(category)}`)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success' && data.results && data.results.length > 0) {
            let html = `<div class="live-search-header"><span>Matching Books (${data.count})</span><span class="text-teal">Press Enter to browse all</span></div>`;
            data.results.forEach((item, idx) => {
              html += `
                <a href="${item.url}" class="live-search-item" data-index="${idx}">
                  <img src="${item.cover || 'uploads/default_book.svg'}" alt="${item.title}" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                  <div class="overflow-hidden">
                    <div class="live-search-item-title">${item.title}</div>
                    <div class="live-search-item-author">By ${item.author} • <span class="text-teal">${item.condition}</span></div>
                  </div>
                  <div class="live-search-item-price">${item.formatted_price}</div>
                </a>
              `;
            });
            html += `
              <div class="live-search-footer">
                <a href="index.php?search=${encodeURIComponent(query)}${category ? '&category=' + encodeURIComponent(category) : ''}">
                  View all results for "<strong>${query}</strong>" &rarr;
                </a>
              </div>
            `;
            dropdown.innerHTML = html;
            dropdown.classList.add('show');
          } else {
            dropdown.innerHTML = `
              <div class="p-3 text-center text-muted small">
                <i class="bi bi-search text-muted d-block mb-1"></i>
                No matching books found for "<strong>${query}</strong>".
              </div>
            `;
            dropdown.classList.add('show');
          }
        })
        .catch(() => {
          dropdown.classList.remove('show');
        });
    }, 220);
  });

  // Keyboard navigation for search suggestions
  searchInput.addEventListener('keydown', function (e) {
    const items = dropdown.querySelectorAll('.live-search-item');
    if (!dropdown.classList.contains('show') || items.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      currentHighlightedIndex = (currentHighlightedIndex + 1) % items.length;
      updateHighlightedItem(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      currentHighlightedIndex = (currentHighlightedIndex - 1 + items.length) % items.length;
      updateHighlightedItem(items);
    } else if (e.key === 'Enter') {
      if (currentHighlightedIndex >= 0 && items[currentHighlightedIndex]) {
        e.preventDefault();
        window.location.href = items[currentHighlightedIndex].href;
      }
    } else if (e.key === 'Escape') {
      dropdown.classList.remove('show');
    }
  });

  function updateHighlightedItem(items) {
    items.forEach((item, idx) => {
      if (idx === currentHighlightedIndex) {
        item.classList.add('highlighted');
        item.scrollIntoView({ block: 'nearest' });
      } else {
        item.classList.remove('highlighted');
      }
    });
  }

  // Close dropdown on outside click
  document.addEventListener('click', function (e) {
    if (!searchForm.contains(e.target)) {
      dropdown.classList.remove('show');
    }
  });
}

// ============================================================================
// 4. Hero Showcase Carousel Logic
// ============================================================================
let currentSlideIndex = 0;
let heroAutoplayTimer = null;
const AUTOPLAY_DELAY_MS = 5000;

function goToHeroSlide(index) {
  if (typeof heroSlidesData === 'undefined' || !heroSlidesData.length) return;
  currentSlideIndex = (index + heroSlidesData.length) % heroSlidesData.length;
  renderHeroSlide(currentSlideIndex);
}

function changeHeroSlide(direction) {
  if (typeof heroSlidesData === 'undefined' || !heroSlidesData.length) return;
  currentSlideIndex = (currentSlideIndex + direction + heroSlidesData.length) % heroSlidesData.length;
  renderHeroSlide(currentSlideIndex);
}

function renderHeroSlide(index) {
  if (typeof heroSlidesData === 'undefined' || !heroSlidesData.length) return;
  const slide = heroSlidesData[index];

  const heroTag = document.getElementById('heroTag');
  const heroTitle = document.getElementById('heroTitle');
  const heroDesc = document.getElementById('heroDesc');
  const heroMainBtn = document.getElementById('heroMainBtn');
  const heroImage = document.getElementById('heroImage');
  const heroTextContainer = document.getElementById('heroTextContainer');

  if (heroImage) {
    heroImage.style.opacity = '0';
    heroImage.style.transform = 'scale(0.96)';
  }
  if (heroTextContainer) {
    heroTextContainer.style.opacity = '0.4';
  }

  setTimeout(() => {
    if (heroTag) heroTag.textContent = slide.tag;
    if (heroTitle) heroTitle.textContent = slide.title;
    if (heroDesc) heroDesc.textContent = slide.desc;
    if (heroMainBtn) {
      heroMainBtn.href = slide.btn_link;
      const btnSpan = heroMainBtn.querySelector('span');
      if (btnSpan) btnSpan.textContent = slide.btn_text;
    }
    if (heroImage) {
      heroImage.src = slide.img;
      heroImage.style.opacity = '1';
      heroImage.style.transform = 'scale(1)';
    }
    if (heroTextContainer) {
      heroTextContainer.style.opacity = '1';
    }
  }, 180);

  const dots = document.querySelectorAll('.hero-dot');
  dots.forEach((dot, dotIdx) => {
    if (dotIdx === index) {
      dot.classList.add('active');
    } else {
      dot.classList.remove('active');
    }
  });
}

function startHeroAutoplay() {
  if (typeof heroSlidesData === 'undefined' || !heroSlidesData.length) return;
  stopHeroAutoplay();
  heroAutoplayTimer = setInterval(() => {
    changeHeroSlide(1);
  }, AUTOPLAY_DELAY_MS);
}

function stopHeroAutoplay() {
  if (heroAutoplayTimer) {
    clearInterval(heroAutoplayTimer);
    heroAutoplayTimer = null;
  }
}

function pauseHeroAutoplay() {
  stopHeroAutoplay();
}

function resumeHeroAutoplay() {
  startHeroAutoplay();
}

function resetHeroTimer() {
  stopHeroAutoplay();
  startHeroAutoplay();
}

// ============================================================================
// 5. Rich Wishlist Storage & Interactive Functions
// ============================================================================
function getWishlist() {
  try {
    return JSON.parse(localStorage.getItem('booksy_wishlist_rich')) || [];
  } catch (e) {
    return [];
  }
}

function saveWishlist(list) {
  localStorage.setItem('booksy_wishlist_rich', JSON.stringify(list));
  updateWishlistUI();
}

function toggleWishlistItem(button, bookId, bookData = {}) {
  let list = getWishlist();
  const existingIdx = list.findIndex(item => item.id === bookId);

  if (existingIdx > -1) {
    const title = list[existingIdx].title || 'Book';
    list.splice(existingIdx, 1);
    button.classList.remove('active');
    const icon = button.querySelector('i');
    if (icon) {
      icon.classList.remove('bi-heart-fill');
      icon.classList.add('bi-heart');
    }
    showToast('info', 'Wishlist Updated', `Removed "<strong>${title}</strong>" from your wishlist.`);
  } else {
    const bookTitle = button.getAttribute('data-book-title') || bookData.title || 'Book';
    const bookPrice = button.getAttribute('data-book-price') || bookData.price || '0.00';
    const bookCover = button.getAttribute('data-book-cover') || bookData.cover || 'uploads/default_book.svg';
    const bookAuthor = button.getAttribute('data-book-author') || bookData.author || '';

    list.push({
      id: bookId,
      title: bookTitle,
      price: bookPrice,
      cover: bookCover,
      author: bookAuthor
    });

    button.classList.add('active');
    const icon = button.querySelector('i');
    if (icon) {
      icon.classList.remove('bi-heart');
      icon.classList.add('bi-heart-fill');
    }
    showToast('success', 'Saved to Wishlist', `Added "<strong>${bookTitle}</strong>" to your wishlist!`);
  }
  saveWishlist(list);
}

function updateWishlistUI() {
  const list = getWishlist();
  const navBadge = document.getElementById('wishlistNavCount');
  const navBadgeMobile = document.getElementById('wishlistNavCountMobile');
  const bottomNavBadge = document.getElementById('bottomNavWishlistBadge');

  [navBadge, navBadgeMobile, bottomNavBadge].forEach(badge => {
    if (badge) {
      if (list.length > 0) {
        badge.textContent = list.length;
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }
    }
  });

  const ids = list.map(item => item.id);
  document.querySelectorAll('.btn-wishlist-toggle').forEach(btn => {
    const id = parseInt(btn.getAttribute('data-book-id'));
    const icon = btn.querySelector('i');
    if (ids.includes(id)) {
      btn.classList.add('active');
      if (icon) {
        icon.classList.remove('bi-heart');
        icon.classList.add('bi-heart-fill');
      }
    } else {
      btn.classList.remove('active');
      if (icon) {
        icon.classList.remove('bi-heart-fill');
        icon.classList.add('bi-heart');
      }
    }
  });
}

function toggleWishlistModal() {
  const list = getWishlist();
  const modalBody = document.getElementById('wishlistModalBody');
  if (!modalBody) return;

  if (list.length === 0) {
    modalBody.innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-heart text-muted mb-3" style="font-size: 3rem;"></i>
        <h5 class="fw-bold text-navy mb-2">Your wishlist is empty</h5>
        <p class="text-muted small mb-4 mx-auto" style="max-width: 320px;">
          Click the heart icon on any book across our marketplace to save it here for quick access later.
        </p>
        <button class="btn btn-booksy-primary fw-bold px-4 py-2" data-bs-dismiss="modal">Explore Catalog</button>
      </div>
    `;
  } else {
    modalBody.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <span class="fw-bold text-navy"><i class="bi bi-heart-fill text-danger me-1"></i> ${list.length} Saved Book${list.length > 1 ? 's' : ''}</span>
        <button type="button" class="btn btn-link text-danger text-decoration-none p-0 small" onclick="saveWishlist([]); toggleWishlistModal();">Clear All</button>
      </div>
      <div class="d-flex flex-column gap-2" style="max-height: 380px; overflow-y: auto;">
        ${list.map(item => `
          <div class="card border-0 bg-light p-2.5 rounded-3 d-flex flex-row align-items-center justify-content-between gap-3 border">
            <div class="d-flex align-items-center gap-2.5 overflow-hidden">
              <img src="${item.cover || 'uploads/default_book.svg'}" alt="${item.title}" class="rounded" style="width: 42px; height: 55px; object-fit: contain; background: #17324D;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
              <div class="overflow-hidden">
                <a href="book-details.php?id=${item.id}" class="fw-bold text-navy text-decoration-none text-truncate d-block small mb-0">${item.title}</a>
                <div class="text-muted small" style="font-size: 0.72rem;">By ${item.author || 'Seller'}</div>
                <div class="fw-bold text-teal small">Rs. ${parseFloat(item.price || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
              </div>
            </div>
            <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
              <form method="POST" action="cart.php" class="m-0">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="book_id" value="${item.id}">
                <button type="submit" class="btn btn-sm btn-booksy-primary py-1 px-2" title="Move to Cart">
                  <i class="bi bi-cart-plus"></i>
                </button>
              </form>
              <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" title="Remove" onclick="removeWishlistItemDirect(${item.id})">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        `).join('')}
      </div>
      <div class="mt-3 pt-3 border-top text-center">
        <a href="index.php" class="btn btn-booksy-navy btn-sm fw-semibold w-100" data-bs-dismiss="modal">Continue Browsing</a>
      </div>
    `;
  }

  const modalEl = document.getElementById('wishlistModal');
  if (modalEl) {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function removeWishlistItemDirect(bookId) {
  let list = getWishlist();
  list = list.filter(item => item.id !== bookId);
  saveWishlist(list);
  toggleWishlistModal();
}

// ============================================================================
// 6. Catalog View Mode Switcher (Grid vs. List View)
// ============================================================================
function initViewModeSwitcher() {
  const gridBtn = document.getElementById('viewModeGrid');
  const listBtn = document.getElementById('viewModeList');
  const catalogContainer = document.getElementById('bookProductGridContainer');
  if (!gridBtn || !listBtn || !catalogContainer) return;

  const savedMode = localStorage.getItem('booksy_catalog_view') || 'grid';
  applyViewMode(savedMode);

  gridBtn.addEventListener('click', () => {
    applyViewMode('grid');
    localStorage.setItem('booksy_catalog_view', 'grid');
  });

  listBtn.addEventListener('click', () => {
    applyViewMode('list');
    localStorage.setItem('booksy_catalog_view', 'list');
  });

  function applyViewMode(mode) {
    if (mode === 'list') {
      catalogContainer.classList.add('catalog-list-view');
      listBtn.classList.add('active');
      gridBtn.classList.remove('active');
    } else {
      catalogContainer.classList.remove('catalog-list-view');
      gridBtn.classList.add('active');
      listBtn.classList.remove('active');
    }
  }
}

// ============================================================================
// 7. Shopping Cart Quantity Management & Promo Voucher Engine
// ============================================================================
const PROMO_CODES = {
  'BOOKSY10': { discountPercent: 10, description: '10% Off Welcome Voucher' },
  'STUDENT500': { discountFixed: 500, description: 'Rs. 500 Campus Student Discount' },
  'FREESHIP': { freeShipping: true, description: 'Free Courier Delivery Coupon' }
};

let cartQtyDebounceTimers = {};

/**
 * Step quantity on book-details.php
 */
function stepDetailQty(delta) {
  const input = document.getElementById('productDetailQty');
  if (!input) return;
  let val = parseInt(input.value, 10) || 1;
  val = Math.max(1, Math.min(99, val + delta));
  input.value = val;
}

function applyQuickPromo(code) {
  const input = document.getElementById('promoCodeInput');
  if (!input) return;
  input.value = code;
  applyPromoCode(false);
}

function removePromoCode() {
  const input = document.getElementById('promoCodeInput');
  if (input) input.value = '';
  
  const feedback = document.getElementById('promoFeedback');
  if (feedback) {
    feedback.className = 'small mt-1 text-muted';
    feedback.innerHTML = `Click any voucher badge above to instantly apply discount.`;
  }
  
  const discountRow = document.getElementById('promoDiscountRow');
  if (discountRow) discountRow.classList.add('d-none');
  
  const rawSubtotalEl = document.getElementById('cartRawSubtotal');
  const finalTotalSpan = document.getElementById('checkoutFinalTotal');
  const orderBtnText = document.getElementById('orderBtnText');
  
  if (rawSubtotalEl && finalTotalSpan) {
    const subtotal = parseFloat(rawSubtotalEl.value) || 0;
    const formatted = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    finalTotalSpan.textContent = `Rs. ${formatted}`;
    if (orderBtnText) {
      orderBtnText.textContent = `Confirm & Place Order (Rs. ${formatted})`;
    }
    triggerPricePulse(finalTotalSpan);
  }
  showToast('info', 'Voucher Removed', 'The promo code discount has been removed.');
}

function triggerPricePulse(el) {
  if (!el) return;
  el.classList.remove('price-pulse');
  void el.offsetWidth; // Trigger DOM reflow to restart CSS animation
  el.classList.add('price-pulse');
}

function togglePaymentBox(type) {
  const cardBox = document.getElementById('cardDetailsBox');
  const bankBox = document.getElementById('bankDetailsBox');
  const payhereBox = document.getElementById('payhereDetailsBox');
  const codBox = document.getElementById('codDetailsBox');
  const btnText = document.getElementById('orderBtnText');
  const totalText = document.getElementById('checkoutFinalTotal')?.textContent || '';

  const cards = document.querySelectorAll('.payment-method-card');
  cards.forEach(card => card.classList.remove('selected'));

  // Hide all first
  if (cardBox) cardBox.classList.add('d-none');
  if (bankBox) bankBox.classList.add('d-none');
  if (payhereBox) payhereBox.classList.add('d-none');
  if (codBox) codBox.classList.add('d-none');

  if (type === 'card' || type === 'Credit / Debit Card') {
    if (cardBox) cardBox.classList.remove('d-none');
    if (btnText) btnText.textContent = `Pay ${totalText} with Card`;
  } else if (type === 'bank' || type === 'Bank Transfer') {
    if (bankBox) bankBox.classList.remove('d-none');
    if (btnText) btnText.textContent = `Confirm Order & Submit Bank Transfer (${totalText})`;
  } else if (type === 'payhere' || type === 'Online Payment (PayHere)') {
    if (payhereBox) payhereBox.classList.remove('d-none');
    if (btnText) btnText.textContent = 'Proceed to PayHere Gateway';
  } else {
    if (codBox) codBox.classList.remove('d-none');
    if (btnText) btnText.textContent = `Confirm & Place Order (${totalText})`;
  }

  const checkedRadio = document.querySelector('input[name="payment_mode"]:checked');
  if (checkedRadio) {
    const parentCard = checkedRadio.closest('.payment-method-card');
    if (parentCard) parentCard.classList.add('selected');
  }
}

/**
 * Live Card Number Formatting & Brand Detection
 */
function formatCardNumber(input) {
  if (!input) return;
  let val = input.value.replace(/\D/g, '').substring(0, 16);
  let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
  input.value = formatted;

  // Detect card brand
  const badgeVisa = document.getElementById('badgeVisa');
  const badgeMastercard = document.getElementById('badgeMastercard');
  const badgeAmex = document.getElementById('badgeAmex');
  const iconContainer = document.getElementById('cardNumberIcon');

  [badgeVisa, badgeMastercard, badgeAmex].forEach(b => b?.classList.remove('active-brand'));

  if (val.startsWith('4')) {
    badgeVisa?.classList.add('active-brand');
    if (iconContainer) iconContainer.innerHTML = '<i class="bi bi-credit-card text-primary"></i>';
  } else if (/^(5[1-5]|2[2-7])/.test(val)) {
    badgeMastercard?.classList.add('active-brand');
    if (iconContainer) iconContainer.innerHTML = '<i class="bi bi-credit-card-2-front text-danger"></i>';
  } else if (/^(34|37)/.test(val)) {
    badgeAmex?.classList.add('active-brand');
    if (iconContainer) iconContainer.innerHTML = '<i class="bi bi-credit-card-2-back text-info"></i>';
  } else {
    if (iconContainer) iconContainer.innerHTML = '<i class="bi bi-credit-card text-muted"></i>';
  }
}

/**
 * Live Card Expiry Date (MM / YY) Formatting
 */
function formatCardExpiry(input) {
  if (!input) return;
  let val = input.value.replace(/\D/g, '').substring(0, 4);
  if (val.length >= 2) {
    let month = parseInt(val.substring(0, 2), 10);
    if (month > 12) month = 12;
    if (month === 0) month = 1;
    let monthStr = month < 10 ? '0' + month : '' + month;
    input.value = monthStr + ' / ' + val.substring(2);
  } else {
    input.value = val;
  }
}

/**
 * Live CVV Number Formatting
 */
function formatCardCVV(input) {
  if (!input) return;
  input.value = input.value.replace(/\D/g, '').substring(0, 4);
}

/**
 * Instant Demo Card Filler for fast testing
 */
function fillDemoCard() {
  const nameInput = document.getElementById('cardNameInput');
  const numInput = document.getElementById('cardNumberInput');
  const expInput = document.getElementById('cardExpiryInput');
  const cvvInput = document.getElementById('cardCvvInput');

  if (nameInput) nameInput.value = 'KAMAL PERERA';
  if (numInput) {
    numInput.value = '4111 2222 3333 4444';
    formatCardNumber(numInput);
  }
  if (expInput) expInput.value = '12 / 28';
  if (cvvInput) cvvInput.value = '123';

  if (typeof showToast === 'function') {
    showToast('info', 'Sample Card Loaded', 'Card details populated successfully.');
  }
}

/**
 * Bank Deposit Slip Selection Feedback
 */
function onBankSlipSelected(input) {
  const feedback = document.getElementById('bankSlipFeedback');
  if (!feedback) return;
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
    feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Attached: ${file.name} (${sizeMb} MB)</span>`;
  } else {
    feedback.innerHTML = 'Supported: JPG, PNG, PDF (Max 5MB)';
  }
}

function onDistrictChange(district) {
  const estimateBadge = document.getElementById('checkoutDeliveryEstimate');
  const districtTimes = {
    'Colombo': 'FREE • 1-2 Days',
    'Gampaha': 'FREE • 1-2 Days',
    'Kalutara': 'FREE • 2-3 Days',
    'Kandy': 'FREE • 2-3 Days',
    'Galle': 'FREE • 2-3 Days',
    'Matara': 'FREE • 2-3 Days',
    'Kurunegala': 'FREE • 2-4 Days'
  };
  if (estimateBadge) {
    estimateBadge.textContent = districtTimes[district] || 'FREE • 2-4 Days';
    triggerPricePulse(estimateBadge);
  }
}

/**
 * Step quantity by delta (+1 or -1) on cart.php
 */
function stepCartQty(bookId, delta) {
  const input = document.getElementById(`cartQtyInput_${bookId}`);
  if (!input) return;

  let currentQty = parseInt(input.value, 10) || 1;
  let newQty = currentQty + delta;

  if (newQty < 1) {
    if (confirm('Are you sure you want to remove this book from your shopping cart?')) {
      sendCartQtyUpdate(bookId, 0);
    }
    return;
  }

  newQty = Math.min(99, newQty);
  input.value = newQty;
  sendCartQtyUpdate(bookId, newQty);
}

/**
 * Handle direct change on cart qty input
 */
function onCartQtyInputChange(bookId) {
  const input = document.getElementById(`cartQtyInput_${bookId}`);
  if (!input) return;

  let val = parseInt(input.value, 10);
  if (isNaN(val) || val < 1) {
    if (val === 0) {
      if (confirm('Are you sure you want to remove this book from your shopping cart?')) {
        sendCartQtyUpdate(bookId, 0);
        return;
      }
    }
    val = 1;
    input.value = 1;
  }
  val = Math.min(99, val);
  input.value = val;
  sendCartQtyUpdate(bookId, val);
}

/**
 * Debounced handler for continuous input typing
 */
function onCartQtyInputDebounced(bookId) {
  if (cartQtyDebounceTimers[bookId]) {
    clearTimeout(cartQtyDebounceTimers[bookId]);
  }
  cartQtyDebounceTimers[bookId] = setTimeout(() => {
    onCartQtyInputChange(bookId);
  }, 350);
}

/**
 * Confirm item removal and update cart smoothly
 */
function confirmRemoveCartItem(event, bookId) {
  if (event) event.preventDefault();
  if (confirm('Are you sure you want to remove this book from your shopping cart?')) {
    sendCartQtyUpdate(bookId, 0);
  }
  return false;
}

/**
 * Synchronize quantity update with backend via AJAX and update UI in real time
 */
function sendCartQtyUpdate(bookId, qty) {
  const promoInput = document.getElementById('promoCodeInput');
  const promoCode = promoInput ? promoInput.value.trim().toUpperCase() : '';
  const row = document.getElementById(`cartItemRow_${bookId}`);

  // Immediate optimistic line total update
  if (row && qty > 0) {
    const unitPrice = parseFloat(row.getAttribute('data-unit-price')) || 0;
    const itemTotalEl = document.getElementById(`cartItemTotal_${bookId}`);
    if (itemTotalEl) {
      itemTotalEl.textContent = `Rs. ${(unitPrice * qty).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
      triggerPricePulse(itemTotalEl);
    }
  }

  const formData = new FormData();
  formData.append('action', 'update_qty');
  formData.append('book_id', bookId);
  formData.append('qty', qty);
  formData.append('promo_code', promoCode);
  formData.append('ajax', '1');

  fetch('cart.php', {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      if (data.action === 'removed') {
        // Remove row with smooth animation
        if (row) {
          row.style.transition = 'all 0.3s ease';
          row.style.opacity = '0';
          row.style.transform = 'scale(0.95)';
          setTimeout(() => {
            row.remove();
            if (data.is_empty) {
              window.location.reload();
            }
          }, 300);
        }
        showToast('info', 'Cart Updated', data.message);
      } else {
        // Update line total and multiplier tag from server response
        const itemTotalEl = document.getElementById(`cartItemTotal_${bookId}`);
        if (itemTotalEl) {
          itemTotalEl.textContent = `Rs. ${data.item_subtotal}`;
          triggerPricePulse(itemTotalEl);
        }

        const multiplierBadge = document.getElementById(`cartMultiplier_${bookId}`);
        if (multiplierBadge) {
          if (data.qty > 1) {
            multiplierBadge.textContent = `${data.qty} × Rs. ${data.unit_price}`;
            multiplierBadge.classList.remove('d-none');
          } else {
            multiplierBadge.classList.add('d-none');
          }
        }
      }

      // Update Subtotals and Counts
      const rawSubtotalEl = document.getElementById('cartRawSubtotal');
      if (rawSubtotalEl) rawSubtotalEl.value = data.raw_subtotal;

      const subtotalBanner = document.getElementById('cartSubtotalBanner');
      if (subtotalBanner) {
        subtotalBanner.textContent = `Rs. ${data.cart_subtotal}`;
        triggerPricePulse(subtotalBanner);
      }

      const summarySubtotal = document.getElementById('checkoutSummarySubtotal');
      if (summarySubtotal) {
        summarySubtotal.textContent = `Rs. ${data.cart_subtotal}`;
        triggerPricePulse(summarySubtotal);
      }

      const countText = document.getElementById('cartSubtotalCountText');
      if (countText) countText.textContent = `${data.cart_count} item${data.cart_count > 1 ? 's' : ''}`;

      const uniqueCount = document.getElementById('cartHeaderUniqueCount');
      if (uniqueCount) uniqueCount.textContent = data.cart_unique;

      const totalCopiesEl = document.getElementById('cartHeaderTotalCopies');
      if (totalCopiesEl) totalCopiesEl.textContent = data.cart_count;

      const summaryItemsCountEl = document.getElementById('checkoutSummaryItemsCount');
      if (summaryItemsCountEl) summaryItemsCountEl.textContent = `${data.cart_unique} books (${data.cart_count} copies)`;

      // Update Header Badges
      const headerBadge = document.getElementById('headerCartBadge');
      if (headerBadge) {
        headerBadge.textContent = data.cart_count;
        if (data.cart_count > 0) headerBadge.classList.remove('d-none');
        else headerBadge.classList.add('d-none');
      }

      const mobileBadge = document.getElementById('headerCartBadgeMobile');
      if (mobileBadge) {
        mobileBadge.textContent = data.cart_count;
        if (data.cart_count > 0) mobileBadge.classList.remove('d-none');
        else mobileBadge.classList.add('d-none');
      }

      // Recalculate Promo Code and Total Amount silently
      applyPromoCode(true);
    } else {
      showToast('danger', 'Error', data.message || 'Could not update quantity.');
    }
  })
  .catch(err => {
    console.error('Cart update error:', err);
    window.location.href = `cart.php?update_qty=1&book_id=${bookId}&qty=${qty}`;
  });
}

function applyPromoCode(silent = false) {
  const input = document.getElementById('promoCodeInput');
  const feedback = document.getElementById('promoFeedback');
  const discountRow = document.getElementById('promoDiscountRow');
  const discountVal = document.getElementById('promoDiscountAmount');
  const finalTotalSpan = document.getElementById('checkoutFinalTotal');
  const orderBtnText = document.getElementById('orderBtnText');
  const rawSubtotalEl = document.getElementById('cartRawSubtotal');

  if (!rawSubtotalEl || !finalTotalSpan) return;

  const subtotal = parseFloat(rawSubtotalEl.value) || 0;
  const code = input ? input.value.trim().toUpperCase() : '';

  if (code && PROMO_CODES[code]) {
    const promo = PROMO_CODES[code];
    let discount = 0;

    if (promo.discountPercent) {
      discount = (subtotal * promo.discountPercent) / 100;
    } else if (promo.discountFixed) {
      discount = Math.min(subtotal, promo.discountFixed);
    }

    const finalTotal = Math.max(0, subtotal - discount);
    const formattedTotal = finalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (feedback) {
      feedback.className = 'small text-success mt-1 fw-bold';
      feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Voucher applied: ${promo.description} (-Rs. ${discount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;
    }

    if (discountRow && discountVal) {
      discountRow.classList.remove('d-none');
      discountVal.textContent = `-Rs. ${discount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    finalTotalSpan.textContent = `Rs. ${formattedTotal}`;
    if (orderBtnText) {
      orderBtnText.textContent = `Confirm & Place Order (Rs. ${formattedTotal})`;
    }
    triggerPricePulse(finalTotalSpan);

    if (!silent) {
      showToast('success', 'Promo Code Applied!', `Saved Rs. ${discount.toFixed(2)} with code ${code}!`);
    }
  } else {
    if (discountRow) discountRow.classList.add('d-none');
    const formattedSubtotal = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    finalTotalSpan.textContent = `Rs. ${formattedSubtotal}`;
    if (orderBtnText) {
      orderBtnText.textContent = `Confirm & Place Order (Rs. ${formattedSubtotal})`;
    }
    if (code && !silent) {
      if (feedback) {
        feedback.className = 'small text-danger mt-1';
        feedback.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> Invalid coupon code. Try <strong>BOOKSY10</strong> or <strong>STUDENT500</strong>.`;
      }
    }
  }
}

// ============================================================================
// 8. Social Share & Copy Link Helper
// ============================================================================
function copyListingLink(url, customMessage) {
  const linkToCopy = url || window.location.href;
  navigator.clipboard.writeText(linkToCopy).then(() => {
    showToast('success', 'Link Copied!', customMessage || 'Listing link has been copied to your clipboard.');
  }).catch(() => {
    prompt('Copy this link:', linkToCopy);
  });
}

// ============================================================================
// 9. Interactive Sri Lankan District Delivery Calculator
// ============================================================================
function calculateDeliveryEstimate(district) {
  const resultBox = document.getElementById('deliveryEstimateResult');
  if (!resultBox) return;

  const districtEstimates = {
    'Colombo': { time: '1 - 2 Business Days', fee: 'FREE (Promo)', courier: 'Direct City Express' },
    'Gampaha': { time: '1 - 2 Business Days', fee: 'FREE (Promo)', courier: 'Direct City Express' },
    'Kalutara': { time: '2 - 3 Business Days', fee: 'FREE (Promo)', courier: 'Islandwide SpeedPost' },
    'Kandy': { time: '2 - 3 Business Days', fee: 'FREE (Promo)', courier: 'Central Express Courier' },
    'Galle': { time: '2 - 3 Business Days', fee: 'FREE (Promo)', courier: 'Southern Express' },
    'Matara': { time: '2 - 3 Business Days', fee: 'FREE (Promo)', courier: 'Southern Express' },
    'Kurunegala': { time: '2 - 4 Business Days', fee: 'FREE (Promo)', courier: 'Islandwide SpeedPost' }
  };

  const info = districtEstimates[district] || { time: '3 - 5 Business Days', fee: 'FREE (Promo)', courier: 'Islandwide Courier Partner' };

  resultBox.innerHTML = `
    <div class="card border-0 bg-white p-3 rounded-3 shadow-sm border mt-2">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="small text-muted"><i class="bi bi-truck text-teal me-1"></i> Delivery to <strong>${district}</strong>:</span>
        <span class="badge bg-success">${info.fee}</span>
      </div>
      <div class="fw-bold text-navy small mb-1"><i class="bi bi-clock-history me-1 text-teal"></i> Estimated Delivery: ${info.time}</div>
      <div class="small text-muted" style="font-size: 0.75rem;">Shipped via ${info.courier} with Cash on Delivery tracking.</div>
    </div>
  `;
}

// ============================================================================
// 10. Quick Listing Status Toggle (for Sellers in my-listings.php)
// ============================================================================
function toggleListingStatus(button, bookId) {
  button.disabled = true;
  button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

  fetch('api/toggle-status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `book_id=${encodeURIComponent(bookId)}`
  })
  .then(res => res.json())
  .then(data => {
    button.disabled = false;
    if (data.status === 'success') {
      const isNowAvailable = data.new_status === 'available';
      const statusBadge = document.getElementById(`statusBadge_${bookId}`);
      if (statusBadge) {
        if (isNowAvailable) {
          statusBadge.className = 'badge bg-success';
          statusBadge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Available';
          button.className = 'btn btn-outline-warning btn-sm';
          button.innerHTML = '<i class="bi bi-bag-check me-1"></i> Mark Sold';
        } else {
          statusBadge.className = 'badge bg-secondary';
          statusBadge.innerHTML = '<i class="bi bi-bag-check me-1"></i> Sold';
          button.className = 'btn btn-outline-success btn-sm';
          button.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Re-list';
        }
      }
      showToast('success', 'Status Updated', data.message);
    } else {
      button.innerHTML = '<i class="bi bi-toggles"></i> Toggle';
      showToast('danger', 'Error', data.message || 'Could not update status.');
    }
  })
  .catch(() => {
    button.disabled = false;
    button.innerHTML = '<i class="bi bi-toggles"></i> Toggle';
    showToast('danger', 'Network Error', 'Could not reach the server.');
  });
}

// ============================================================================
// 11. Drag & Drop Image Upload Handler
// ============================================================================
function initDragAndDropUpload() {
  const dropZone = document.getElementById('dragDropZone');
  const fileInput = document.getElementById('listingImage');
  const previewImg = document.getElementById('previewImage');
  const dropZoneText = document.getElementById('dropZoneText');

  if (!dropZone || !fileInput) return;

  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.add('dragover');
    }, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.remove('dragover');
    }, false);
  });

  dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files && files.length > 0) {
      fileInput.files = files;
      fileInput.dispatchEvent(new Event('change'));
    }
  });

  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
      if (!file.type.match('image.*')) {
        showToast('danger', 'Invalid File', 'Please select a valid image file (JPG, PNG, WebP).');
        this.value = '';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        showToast('warning', 'File Too Large', 'Image size exceeds 5MB limit. Please choose a smaller photo.');
        this.value = '';
        return;
      }

      if (dropZoneText) {
        dropZoneText.innerHTML = `<strong>Selected:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        if (previewImg) previewImg.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  });
}

// ============================================================================
// 12. Floating Back to Top Button
// ============================================================================
function initBackToTop() {
  const btn = document.getElementById('btnBackToTop');
  if (!btn) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 350) {
      btn.classList.add('visible');
    } else {
      btn.classList.remove('visible');
    }
  });

  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ============================================================================
// 13. DOM Content Loaded Initializer
// ============================================================================
document.addEventListener('DOMContentLoaded', function () {
  // Start Hero Autoplay
  startHeroAutoplay();

  // Initialize Live Search Autocomplete
  initLiveSearch();

  // Update Wishlist
  updateWishlistUI();

  // Initialize View Mode Switcher
  initViewModeSwitcher();

  // Initialize Drag and Drop Upload
  initDragAndDropUpload();

  // Initialize Back To Top
  initBackToTop();

  // Password Visibility Toggle
  const togglePassBtns = document.querySelectorAll('.btn-toggle-password');
  togglePassBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      const input = this.closest('.input-group').querySelector('input');
      const icon = this.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    });
  });

  // Live Book Preview in sell.php / edit-book.php
  const inputTitle = document.getElementById('listingTitle');
  const inputAuthor = document.getElementById('listingAuthor');
  const inputPrice = document.getElementById('listingPrice');
  const selectCondition = document.getElementById('listingCondition');
  const selectCategory = document.getElementById('listingCategory');

  const previewTitle = document.getElementById('previewTitle');
  const previewAuthor = document.getElementById('previewAuthor');
  const previewPrice = document.getElementById('previewPrice');
  const previewCondition = document.getElementById('previewCondition');
  const previewCategory = document.getElementById('previewCategory');

  if (inputTitle && previewTitle) {
    inputTitle.addEventListener('input', function () {
      previewTitle.textContent = this.value.trim() || 'Book Title';
    });
  }

  if (inputAuthor && previewAuthor) {
    inputAuthor.addEventListener('input', function () {
      previewAuthor.textContent = this.value.trim() ? `By ${this.value.trim()}` : 'By Author Name';
    });
  }

  if (inputPrice && previewPrice) {
    inputPrice.addEventListener('input', function () {
      const val = parseFloat(this.value);
      previewPrice.textContent = isNaN(val) ? 'Rs. 0.00' : `Rs. ${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    });
  }

  if (selectCondition && previewCondition) {
    const updateConditionBadge = () => {
      const cond = selectCondition.value || 'Good';
      previewCondition.textContent = cond;
      previewCondition.className = 'badge badge-condition';
      if (cond === 'Brand New') previewCondition.classList.add('badge-brand-new');
      else if (cond === 'Like New') previewCondition.classList.add('badge-like-new');
      else if (cond === 'Good') previewCondition.classList.add('badge-good');
      else if (cond === 'Fair') previewCondition.classList.add('badge-fair');
      else previewCondition.classList.add('badge-poor');
    };
    selectCondition.addEventListener('change', updateConditionBadge);
  }

  if (selectCategory && previewCategory) {
    selectCategory.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];
      previewCategory.textContent = selectedOption && selectedOption.value ? selectedOption.text : 'Category';
    });
  }

  // Payment Method Selection in Checkout
  const paymentRadios = document.querySelectorAll('input[name="payment_mode"]');
  paymentRadios.forEach(radio => {
    radio.addEventListener('change', function () {
      togglePaymentBox(this.value);
    });
  });

  // Tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
