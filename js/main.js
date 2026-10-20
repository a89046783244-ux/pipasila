/* PIPA — pipasila.ru. Без форм, cookies и аналитики. */

// Мобильное меню
const burger = document.getElementById('burger');
const nav = document.getElementById('nav');
burger.addEventListener('click', () => {
  const open = burger.getAttribute('aria-expanded') !== 'true';
  burger.setAttribute('aria-expanded', String(open));
  burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
  nav.classList.toggle('open', open);
});
nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  burger.setAttribute('aria-expanded', 'false');
  burger.setAttribute('aria-label', 'Открыть меню');
  nav.classList.remove('open');
}));

// Копирование промокода
const copyBtn = document.getElementById('copyBtn');
copyBtn.addEventListener('click', async () => {
  const code = document.getElementById('promoCode').textContent.trim();
  const label = copyBtn.querySelector('span');
  try {
    await navigator.clipboard.writeText(code);
  } catch {
    const t = document.createElement('textarea');
    t.value = code; t.style.position = 'fixed'; t.style.opacity = '0';
    document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); } catch {}
    t.remove();
  }
  label.textContent = 'Скопировано';
  setTimeout(() => { label.textContent = 'Скопировать'; }, 2500);
});

// Окно «Конфиденциальность»
const dlg = document.getElementById('privacy');
document.getElementById('privacyBtn').addEventListener('click', () => dlg.showModal());
document.getElementById('privacyClose').addEventListener('click', () => dlg.close());
dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
