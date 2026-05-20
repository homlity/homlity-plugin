(function () {
  function handleCopy(e) {
    var btn = e.target.closest('[data-copy-url]');
    if (!btn) return;
    e.preventDefault();
    var url = btn.dataset.copyUrl || '';
    if (!url) return;

    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).catch(function () {});
    } else {
      var ta = document.createElement('textarea');
      ta.value = url;
      ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      try { document.execCommand('copy'); } catch (err) {}
      document.body.removeChild(ta);
    }

    btn.classList.add('is-copied');
    setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
  }

  document.addEventListener('click', handleCopy);
})();
