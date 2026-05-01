(function () {
    'use strict';

    function createMultiSelect(select) {
        if (!select || select.dataset.enhanced === '1') return;
        select.dataset.enhanced = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'hpf-multi';

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'hpf-multi__trigger';

        var chips = document.createElement('div');
        chips.className = 'hpf-multi__chips';

        var arrow = document.createElement('span');
        arrow.className = 'hpf-multi__arrow';
        arrow.innerHTML = '&#9662;';

        trigger.appendChild(chips);
        trigger.appendChild(arrow);

        var menu = document.createElement('div');
        menu.className = 'hpf-multi__menu';

        var options = Array.prototype.slice.call(select.options).filter(function (opt) {
            return opt.value !== '';
        });

        function selectedValues() {
            return options.filter(function (opt) { return opt.selected; }).map(function (opt) { return opt.value; });
        }

        function renderChips() {
            chips.innerHTML = '';
            var selected = options.filter(function (opt) { return opt.selected; });

            if (!selected.length) {
                var placeholder = document.createElement('span');
                placeholder.className = 'hpf-multi__placeholder';
                placeholder.textContent = select.dataset.placeholder || 'Selecciona opciones';
                chips.appendChild(placeholder);
                return;
            }

            selected.forEach(function (opt) {
                var chip = document.createElement('span');
                chip.className = 'hpf-multi__chip';
                chip.textContent = opt.text;

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'hpf-multi__chip-remove';
                remove.innerHTML = '&times;';
                remove.setAttribute('aria-label', 'Quitar ' + opt.text);
                remove.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    opt.selected = false;
                    renderChips();
                    renderMenu();
                });

                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        }

        function renderMenu() {
            menu.innerHTML = '';
            options.forEach(function (opt) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'hpf-multi__item' + (opt.selected ? ' is-selected' : '');
                item.textContent = opt.text;
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    opt.selected = !opt.selected;
                    renderChips();
                    renderMenu();
                });
                menu.appendChild(item);
            });
        }

        trigger.addEventListener('click', function () {
            wrapper.classList.toggle('is-open');
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('is-open');
            }
        });

        select.style.display = 'none';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);

        renderChips();
        renderMenu();
    }

    function init() {
        document.querySelectorAll('.property-filter-multiselect').forEach(createMultiSelect);

        document.querySelectorAll('.property-filter-widget form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var data = new FormData(form);
                var values = {};

                data.forEach(function (value, key) {
                    var normalizedKey = key.replace(/\[\]$/, '');
                    if (!values[normalizedKey]) {
                        values[normalizedKey] = [];
                    }
                    if (value !== '') {
                        values[normalizedKey].push(String(value));
                    }
                });

                var action = form.getAttribute('action') || window.location.pathname;
                action = action.split('?')[0].replace(/\/+$/, '');

                function firstOf(key) {
                    return values[key] && values[key].length ? values[key][0] : '';
                }

                var pathParts = [];
                var gestion = firstOf('gestion');
                var tipo = firstOf('tipo');
                var ciudad = firstOf('ciudad');
                var barrios = firstOf('barrios');

                if (gestion) { pathParts.push('gestion', encodeURIComponent(gestion)); }
                if (tipo) { pathParts.push('tipo', encodeURIComponent(tipo)); }
                if (ciudad) { pathParts.push('ciudad', encodeURIComponent(ciudad)); }
                if (barrios) { pathParts.push('barrios', encodeURIComponent(barrios)); }

                var url = action + (pathParts.length ? '/' + pathParts.join('/') : '') + '/';

                var params = new URLSearchParams();
                Object.keys(values).forEach(function (key) {
                    if (!values[key] || !values[key].length) return;
                    if (key === 'gestion' || key === 'tipo' || key === 'ciudad' || key === 'barrios') {
                        if (values[key].length > 1) {
                            params.set(key, values[key].join(','));
                        }
                        return;
                    }

                    if (values[key].length === 1) {
                        params.set(key, values[key][0]);
                    } else {
                        params.set(key, values[key].join(','));
                    }
                });

                var qs = params.toString();
                window.location.href = qs ? (url + '?' + qs) : url;
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
