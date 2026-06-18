/* NewsFlash front-end v1.11.5 — hover + click tracking */
(function () {
  'use strict';

  console.log('[NewsFlash] front-end loaded. track URL =', window.NF_TRACK_URL || '(未注入)');

  // 卡片 hover 微动效（保留 v1.3.0 行为）
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.nf-card').forEach(function (card) {
      card.addEventListener('mouseenter', function () { this.style.transform = 'translateY(-2px)'; });
      card.addEventListener('mouseleave', function () { this.style.transform = ''; });
    });
  });

  // 推荐位 click 跟踪：用 sendBeacon 不阻塞 navigation
  function track(toolId) {
    if (!toolId || !window.NF_TRACK_URL) return;
    // tool_id / type 放进 URL query — 不依赖 body 解析，sendBeacon/fetch 都稳
    var sep = window.NF_TRACK_URL.indexOf('?') === -1 ? '?' : '&';
    var url = window.NF_TRACK_URL + sep + 'tool_id=' + encodeURIComponent(toolId) + '&type=click';
    var payload = JSON.stringify({ tool_id: toolId, type: 'click' });
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
        return;
      }
    } catch (e) { /* fallback */ }
    // Fallback: fire-and-forget fetch with keepalive
    try {
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true
      });
    } catch (e) { /* ignore */ }
  }

  // 事件委托：捕获推荐位卡片点击 — 在 onclick window.open 之前跑
  // （document 上 capture 阶段，比卡片自身 onclick 早执行）
  document.addEventListener('click', function (e) {
    var card = e.target.closest && e.target.closest('.nf-recommend-card[data-tool-id]');
    if (!card) return;
    console.log('[NewsFlash] 推荐位点击 → 上报 tool_id =', card.dataset.toolId);
    track(card.dataset.toolId);
  }, true);
})();
