(function () {
  'use strict';

  /* Mobile menu */
  var body = document.body;
  var menuBtn = document.getElementById('menu-btn');
  var scrim = document.getElementById('scrim');
  function setNav(open) {
    body.classList.toggle('nav-open', open);
    if (menuBtn) menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (menuBtn) menuBtn.addEventListener('click', function () { setNav(!body.classList.contains('nav-open')); });
  if (scrim) scrim.addEventListener('click', function () { setNav(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });

  /* Dismiss messages */
  document.addEventListener('click', function (e) {
    var close = e.target.closest('.alert-close');
    if (close) close.closest('.alert').remove();
  });

  /* Confirmation dialog for risky actions */
  var dialog = document.getElementById('confirm-dialog');
  var pendingForm = null;
  function askConfirm(form, message) {
    if (!dialog || typeof dialog.showModal !== 'function') {
      if (window.confirm(message)) { form.dataset.confirmed = '1'; form.submit(); }
      return;
    }
    pendingForm = form;
    document.getElementById('confirm-text').textContent = message;
    dialog.showModal();
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.dataset || !form.dataset.confirm || form.dataset.confirmed === '1') return;
    if (form.dataset.confirmField) {
      var field = form.elements[form.dataset.confirmField];
      if (!field || field.value !== form.dataset.confirmValue) return;
    }
    e.preventDefault();
    askConfirm(form, form.dataset.confirm);
  });
  if (dialog) {
    document.getElementById('confirm-cancel').addEventListener('click', function () { pendingForm = null; dialog.close(); });
    document.getElementById('confirm-ok').addEventListener('click', function () {
      var f = pendingForm; pendingForm = null; dialog.close();
      if (f) { f.dataset.confirmed = '1'; f.submit(); }
    });
  }

  /* Prevent double submits */
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var btns = e.target.querySelectorAll('button[type="submit"]');
    setTimeout(function () { btns.forEach(function (b) { b.disabled = true; }); }, 0);
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
  });

  /* Show fields that depend on the selected role */
  var role = document.getElementById('role');
  var dependent = document.querySelectorAll('[data-show-for]');
  function syncRole() {
    if (!role) return;
    dependent.forEach(function (el) {
      var show = el.dataset.showFor.split(',').indexOf(role.value) !== -1;
      el.hidden = !show;
    });
  }
  if (role && dependent.length) { role.addEventListener('change', syncRole); syncRole(); }

  /* Print view */
  if (body.dataset.autoprint) window.addEventListener('load', function () { setTimeout(window.print, 300); });
})();
