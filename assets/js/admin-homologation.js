(function () {
    var cfg = window.homlityHomologation || {};

    document.querySelectorAll('.homlity-delete-mapping').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id         = this.getAttribute('data-id');
            var confirmMsg = this.getAttribute('data-confirm') || '¿Eliminar?';
            var row        = this.closest('tr');

            if (!window.confirm(confirmMsg)) { return; }

            fetch(cfg.endpoint + id, {
                method:  'DELETE',
                headers: { 'X-WP-Nonce': cfg.nonce }
            }).then(function (r) {
                if (r.ok) {
                    row.style.opacity = '0.4';
                    setTimeout(function () { row.remove(); }, 300);
                } else {
                    alert(cfg.errMsg);
                }
            }).catch(function () {
                alert(cfg.errMsg);
            });
        });
    });
})();
