/* NewsFlash Admin UI - vanilla JS, no dependencies */
(function () {
  'use strict';

  // ---- Bulk-select counter (recommend tools table) ----
  function updateBulk() {
    var checked = document.querySelectorAll('.nfui-tool-cb:checked').length;
    var cnt = document.getElementById('nfui-sel-cnt');
    var bar = document.getElementById('nfui-bulk-bar');
    if (cnt) cnt.textContent = checked;
    if (bar) bar.classList.toggle('is-show', checked > 0);
  }
  window.nfuiUpdateBulk = updateBulk;

  // ---- Quick-edit toggle ----
  window.nfuiToggleQuick = function (id) {
    var el = document.getElementById('nfui-qe-' + id);
    if (el) el.classList.toggle('is-open');
  };

  // ---- Copy to clipboard ----
  window.nfuiCopy = function (text, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        if (btn) {
          var orig = btn.textContent;
          btn.textContent = '✓ 已复制';
          setTimeout(function () { btn.textContent = orig; }, 1500);
        }
      });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      if (btn) {
        var o = btn.textContent;
        btn.textContent = '✓ 已复制';
        setTimeout(function () { btn.textContent = o; }, 1500);
      }
    }
  };

  // ---- Check-all in tool list ----
  document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'nfui-check-all') {
      document.querySelectorAll('.nfui-tool-cb').forEach(function (c) { c.checked = e.target.checked; });
      updateBulk();
    }
  });
})();
