/* ═══════════════════════════════════════
   PIPA — main.js  v2.0
═══════════════════════════════════════ */

/* ── Navbar scroll ── */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 60);
}, { passive: true });

/* ── Burger menu ── */
const burger   = document.getElementById('burger');
const navLinks = document.getElementById('navLinks');

burger.addEventListener('click', () => {
  burger.classList.toggle('open');
  navLinks.classList.toggle('open');
});

navLinks.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => {
    burger.classList.remove('open');
    navLinks.classList.remove('open');
  });
});

document.addEventListener('click', (e) => {
  if (!burger.contains(e.target) && !navLinks.contains(e.target)) {
    burger.classList.remove('open');
    navLinks.classList.remove('open');
  }
});


/* ── Modals ── */
function showOrder(item) {
  const title = document.getElementById('modalTitle');
  title.textContent = item ? `Заказать: ${item}` : 'Оформить заказ';
  document.getElementById('orderProduct').value = item || '';
  document.getElementById('orderModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function showNotify(item) {
  const title = document.getElementById('notifyTitle');
  title.textContent = `Уведомить: ${item}`;
  document.getElementById('notifyModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

async function postAPI(data) {
  try {
    const r = await fetch('/api/submit.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) });
    return r.ok;
  } catch { return false; }
}

function submitNotify(e) {
  e.preventDefault();
  const input = e.target.querySelector('input');
  const btn   = e.target.querySelector('button[type="submit"]');
  const title = document.getElementById('notifyTitle');
  input.disabled = true;
  btn.textContent = 'Записываю…';

  postAPI({ email: input.value, type: 'notify', product: title.textContent.replace('Уведомить: ','') }).then(ok => {
    closeModal('notifyModal');
    showToast(ok ? 'Готово! Скоро свяжемся с вами в Telegram или MAX' : 'Ошибка, попробуй позже');
    input.disabled = false;
    input.value = '';
    btn.textContent = 'Подписаться';
  });
}

function showPrivacy() {
  document.getElementById('privacyModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
}

// Overlay click to close
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
});

// Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    document.body.style.overflow = '';
  }
});


/* ── Toast ── */
function showToast(msg, duration = 3200) {
  const toast = document.getElementById('toast');
  document.getElementById('toastMsg').textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), duration);
}


/* ── Email submit ── */
function submitEmail(e) {
  e.preventDefault();
  const inputs = e.target.querySelectorAll('input');
  const btn    = e.target.querySelector('button[type="submit"]');
  inputs.forEach(i => i.disabled = true);
  btn.textContent = 'Отправляем…';

  postAPI({ name: inputs[0].value, phone: inputs[1].value, product: document.getElementById('orderProduct').value, type: 'subscribe' }).then(ok => {
    closeModal('orderModal');
    showToast('Готово! Скоро свяжемся с вами в Telegram или MAX');
    inputs.forEach(i => { i.disabled = false; i.value = ''; });
    document.getElementById('orderProduct').value = '';
    btn.textContent = 'Оставить заявку';
  });
}


/* ── Copy promo ── */
function copyPromo() {
  const btn = document.getElementById('copyBtn');
  const code = 'PIPA20';

  const doCopy = () => {
    btn.innerHTML = '<i class="fas fa-check"></i> Скопировано!';
    btn.classList.add('copied');
    showToast('Промокод PIPA20 скопирован!');
    setTimeout(() => {
      btn.innerHTML = '<i class="fas fa-copy"></i> Скопировать';
      btn.classList.remove('copied');
    }, 2800);
  };

  if (navigator.clipboard) {
    navigator.clipboard.writeText(code).then(doCopy).catch(() => fallbackCopy(code, doCopy));
  } else {
    fallbackCopy(code, doCopy);
  }
}

function fallbackCopy(text, cb) {
  const el = document.createElement('textarea');
  el.value = text;
  el.style.position = 'fixed';
  el.style.opacity = '0';
  document.body.appendChild(el);
  el.select();
  try { document.execCommand('copy'); cb(); } catch(e) {}
  document.body.removeChild(el);
}


/* ── Scroll Reveal ── */
const revealObs = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      const delay = entry.target.dataset.revealDelay || 0;
      setTimeout(() => {
        entry.target.classList.add('revealed');
      }, delay * 120);
      revealObs.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -32px 0px' });

function initReveal() {
  document.querySelectorAll('.caps-grid .cap-card').forEach((el, i) => {
    el.setAttribute('data-reveal', '');
    el.dataset.revealDelay = i;
  });
  document.querySelectorAll('.reviews-grid .review-card').forEach((el, i) => {
    el.setAttribute('data-reveal', '');
    el.dataset.revealDelay = i;
  });
  document.querySelectorAll('.vprops-grid .vprop').forEach((el, i) => {
    el.setAttribute('data-reveal', '');
    el.dataset.revealDelay = i;
  });

  document.querySelectorAll('[data-reveal]').forEach(el => revealObs.observe(el));
}


/* ── Smooth scroll offset ── */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    const id = this.getAttribute('href');
    if (id === '#') return;
    const target = document.querySelector(id);
    if (!target) return;
    e.preventDefault();
    const top = target.getBoundingClientRect().top + window.pageYOffset - 72;
    window.scrollTo({ top, behavior: 'smooth' });
  });
});


/* ── Active nav highlight ── */
function initActiveNav() {
  const sections = document.querySelectorAll('section[id]');
  const links    = document.querySelectorAll('.nav-links a[href^="#"]');

  const obs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        links.forEach(l => l.removeAttribute('style'));
        const active = [...links].find(l => l.getAttribute('href') === `#${entry.target.id}`);
        if (active) {
          active.style.color = '#FFFFFF';
          active.style.background = 'rgba(232,0,61,.15)';
        }
      }
    });
  }, { threshold: 0.35 });

  sections.forEach(s => obs.observe(s));
}


/* ── Hero image hover parallax ── */
function initParallax() {
  const img = document.querySelector('.hero-cap-img');
  if (!img) return;
  const wrap = img.closest('.hero-right');
  if (!wrap) return;

  wrap.addEventListener('mousemove', (e) => {
    const rect = wrap.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width  - 0.5;
    const y = (e.clientY - rect.top)  / rect.height - 0.5;
    img.style.transform = `translateX(${x * 12}px) translateY(${y * 8}px) scale(1.02)`;
  });
  wrap.addEventListener('mouseleave', () => {
    img.style.transform = '';
  });
}


/* ── Ticker duplication ── */
function initTicker() {
  const track = document.querySelector('.marquee-track');
  if (!track) return;
  track.innerHTML += track.innerHTML;
}


/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  initReveal();
  initActiveNav();
  initParallax();
  initTicker();
});
