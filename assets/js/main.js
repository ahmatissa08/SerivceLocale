/* ============================================================
   ServiLocal — main.js
   ============================================================ */

// ── Scroll to top button ──────────────────────────────────
window.addEventListener('scroll', () => {
  const btn = document.getElementById('scrollTopBtn');
  if (btn) btn.classList.toggle('visible', window.scrollY > 400);
});

// ── Mobile burger menu ────────────────────────────────────
const burgerBtn = document.getElementById('burgerBtn');
const mobileNav = document.getElementById('mobileNav');
if (burgerBtn && mobileNav) {
  burgerBtn.addEventListener('click', () => {
    mobileNav.classList.toggle('open');
  });
}

// ── Toast notification ────────────────────────────────────
function showToast(msg, duration = 3000) {
  const toast = document.getElementById('toastMsg');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), duration);
}

// Show toast from URL param ?toast=message
(function () {
  const params = new URLSearchParams(window.location.search);
  const msg = params.get('toast');
  if (msg) {
    setTimeout(() => showToast(decodeURIComponent(msg)), 300);
    // Clean URL without reload
    const clean = window.location.pathname + window.location.search.replace(/[?&]toast=[^&]*/, '').replace(/^&/, '?');
    window.history.replaceState({}, '', clean || window.location.pathname);
  }
})();

// ── Star rating picker ────────────────────────────────────
function initStarPicker(containerId, inputId) {
  const container = document.getElementById(containerId);
  const input     = document.getElementById(inputId);
  if (!container || !input) return;

  const stars = container.querySelectorAll('span');
  stars.forEach((star, idx) => {
    star.addEventListener('click', () => {
      input.value = idx + 1;
      stars.forEach((s, i) => s.classList.toggle('lit', i <= idx));
    });
    star.addEventListener('mouseenter', () => {
      stars.forEach((s, i) => s.classList.toggle('lit', i <= idx));
    });
  });
  container.addEventListener('mouseleave', () => {
    const val = parseInt(input.value) || 0;
    stars.forEach((s, i) => s.classList.toggle('lit', i < val));
  });
}

// ── Confirm dialog helper ─────────────────────────────────
function confirmAction(msg, url) {
  if (confirm(msg)) {
    window.location.href = url;
  }
}

// ── Auto-dismiss alerts ───────────────────────────────────
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
  setTimeout(() => {
    alert.style.opacity = '0';
    alert.style.transition = 'opacity .4s';
    setTimeout(() => alert.remove(), 400);
  }, 4000);
});

// ── Search filter tags ────────────────────────────────────
document.querySelectorAll('.filter-tag').forEach(tag => {
  tag.addEventListener('click', function () {
    document.querySelectorAll('.filter-tag').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    const val = this.dataset.filter;
    const url = new URL(window.location);
    if (val === 'tous') url.searchParams.delete('filter');
    else url.searchParams.set('filter', val);
    window.location = url;
  });
});

// ── Preview profile picture before upload ────────────────
const avatarInput = document.getElementById('avatarInput');
if (avatarInput) {
  avatarInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById('avatarPreview');
      if (preview) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        const placeholder = document.getElementById('avatarPlaceholder');
        if (placeholder) placeholder.style.display = 'none';
      }
    };
    reader.readAsDataURL(file);
  });
}

// ── Category card filter (index page) ────────────────────
document.querySelectorAll('.cat-card[data-cat]').forEach(card => {
  card.addEventListener('click', function () {
    document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('active-cat'));
    this.classList.add('active-cat');
    const url = new URL(window.location);
    const cat = this.dataset.cat;
    if (cat === 'tous') url.searchParams.delete('cat');
    else url.searchParams.set('cat', cat);
    window.location = url;
  });
});

// ── Dynamic search (debounced) ────────────────────────────
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

const searchNameInput = document.getElementById('searchName');
if (searchNameInput) {
  searchNameInput.addEventListener('input', debounce(() => {
    document.getElementById('searchForm')?.submit();
  }, 500));
}

// ── Booking date min ──────────────────────────────────────
const dateInput = document.querySelector('input[name="booking_date"]');
if (dateInput) dateInput.min = new Date().toISOString().split('T')[0];

// ── Status action buttons (dashboard) ────────────────────
document.querySelectorAll('[data-action]').forEach(btn => {
  btn.addEventListener('click', function () {
    const action = this.dataset.action;
    const id     = this.dataset.id;
    const labels = { accept:'Accepter cette réservation ?', refuse:'Refuser cette réservation ?', cancel:'Annuler cette réservation ?' };
    if (confirm(labels[action] || 'Confirmer ?')) {
      window.location.href = `/servilocal/dashboard.php?action=${action}&id=${id}`;
    }
  });
});
