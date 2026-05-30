// ── Toast auto-dismiss ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('app-toast') || document.getElementById('admin-toast');
  if (toast) setTimeout(() => toast.style.opacity = '0', 3500);

  // Password toggle
  document.querySelectorAll('.pwd-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = document.getElementById(btn.dataset.target);
      if (!inp) return;
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.innerHTML = isText
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    });
  });

  // Password strength
  const pwdInput = document.getElementById('regPwd') || document.getElementById('newPwd');
  const strength = document.getElementById('pwdStrength');
  if (pwdInput && strength) {
    pwdInput.addEventListener('input', () => {
      const v = pwdInput.value;
      let level = 0;
      if (v.length >= 8) level++;
      if (/[A-Z]/.test(v)) level++;
      if (/[0-9]/.test(v)) level++;
      if (/[^A-Za-z0-9]/.test(v)) level++;
      strength.dataset.level = level;
    });
  }

  // Role option click
  document.querySelectorAll('.role-option').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('.role-option').forEach(o => o.classList.remove('selected'));
      el.classList.add('selected');
    });
  });

  // Exp chip click
  document.querySelectorAll('.exp-chip').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('.exp-chip').forEach(o => o.classList.remove('active'));
      el.classList.add('active');
    });
  });
});

// ── Quick chip for inquiry ───────────────────────────────────────────────────
function addChip(btn, text) {
  const ta = document.getElementById('inqMessage');
  if (!ta) return;
  const sep = ta.value && !ta.value.endsWith(' ') && !ta.value.endsWith('\n') ? '. ' : '';
  ta.value += sep + text;
  ta.focus();
  btn.style.background = 'var(--accent-light)';
  btn.style.borderColor = 'var(--accent)';
  btn.style.color = 'var(--accent)';
}
