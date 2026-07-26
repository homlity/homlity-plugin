(function () {
    'use strict';

    function createMultiSelect(select) {
        if (!select || select.dataset.enhanced === '1') return;
        select.dataset.enhanced = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'hpf-multi';

        var menuId = (select.id ? select.id + '-' : 'hpf-multi-') + Math.random().toString(36).slice(2) + '-menu';
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'hpf-multi__trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', menuId);

        var chips = document.createElement('div');
        chips.className = 'hpf-multi__chips';

        var arrow = document.createElement('span');
        arrow.className = 'hpf-multi__arrow';
        arrow.innerHTML = '&#9662;';

        trigger.appendChild(chips);
        trigger.appendChild(arrow);

        var menu = document.createElement('div');
        menu.className = 'hpf-multi__menu';
        menu.id = menuId;
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-multiselectable', 'true');

        var options = Array.prototype.slice.call(select.options).filter(function (opt) {
            return opt.value !== '';
        });

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

                var label = document.createElement('span');
                label.className = 'hpf-multi__chip-label';
                label.textContent = opt.text;

                var remove = document.createElement('span');
                remove.className = 'hpf-multi__chip-remove';
                remove.innerHTML = '&times;';
                remove.setAttribute('role', 'button');
                remove.setAttribute('tabindex', '0');
                remove.setAttribute('aria-label', 'Quitar ' + opt.text);

                function removeOption(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    opt.selected = false;
                    notifyChange();
                }

                remove.addEventListener('click', removeOption);
                remove.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        removeOption(e);
                    }
                });

                chip.appendChild(label);
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
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
                item.textContent = opt.text;
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    opt.selected = !opt.selected;
                    notifyChange();
                });
                menu.appendChild(item);
            });
        }

        function openMenu() {
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function closeMenu() {
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function () {
            if (wrapper.classList.contains('is-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                closeMenu();
            }
        });

        wrapper.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
                trigger.focus();
            }
        });

        function notifyChange() {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        select.addEventListener('change', function () {
            renderChips();
            renderMenu();
        });

        select.style.display = 'none';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);

        renderChips();
        renderMenu();
    }

    function bindFormSubmit(form) {
        if (!form || form.dataset.hpfBound === '1') {
            return;
        }
        form.dataset.hpfBound = '1';

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

            function seoValueOf(key) {
                if (!values[key] || !values[key].length) {
                    return '';
                }
                return values[key].map(function (value) {
                    return encodeURIComponent(value);
                }).join(',');
            }

            var pathParts = [];
            var gestion = seoValueOf('gestion');
            var tipo = seoValueOf('tipo');
            var ciudad = seoValueOf('ciudad');
            var barrios = seoValueOf('barrios');

            if (gestion) { pathParts.push('gestion', gestion); }
            if (tipo) { pathParts.push('tipo', tipo); }
            if (ciudad) { pathParts.push('ciudad', ciudad); }
            if (barrios) { pathParts.push('barrios', barrios); }

            var url = action + (pathParts.length ? '/' + pathParts.join('/') : '') + '/';

            var params = new URLSearchParams();
            Object.keys(values).forEach(function (key) {
                if (!values[key] || !values[key].length) return;
                if (key === 'gestion' || key === 'tipo' || key === 'ciudad' || key === 'barrios') {
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
    }

    function init(context) {
        var root = context && context.querySelectorAll ? context : document;

        root.querySelectorAll('.property-filter-multiselect').forEach(createMultiSelect);

        root.querySelectorAll('.property-filter-widget form').forEach(bindFormSubmit);

        root.querySelectorAll('.property-filter-widget--mobile-sidebar').forEach(function (widget) {
            if (widget.dataset.mobileBound === '1') return;
            widget.dataset.mobileBound = '1';

            var openBtn = widget.querySelector('[data-mobile-filter-open]');
            var closeBtn = widget.querySelector('[data-mobile-filter-close]');
            var sidebar = widget.querySelector('[data-mobile-filter-sidebar]');
            var overlay = widget.querySelector('[data-mobile-filter-overlay]');

            if (!openBtn || !closeBtn || !sidebar || !overlay) return;

            function openSidebar() {
                widget.classList.add('is-mobile-open');
                openBtn.setAttribute('aria-expanded', 'true');
                overlay.hidden = false;
                document.body.classList.add('homlity-mobile-filter-open');
            }

            function closeSidebar() {
                widget.classList.remove('is-mobile-open');
                openBtn.setAttribute('aria-expanded', 'false');
                overlay.hidden = true;
                document.body.classList.remove('homlity-mobile-filter-open');
            }

            openBtn.addEventListener('click', openSidebar);
            closeBtn.addEventListener('click', closeSidebar);
            overlay.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeSidebar();
                }
            });
        });
    }

    function bindElementorHooks() {
        if (typeof window.elementorFrontend === 'undefined' || !window.elementorFrontend.hooks) {
            return;
        }

        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
            if ($scope && $scope[0]) {
                init($scope[0]);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
            bindElementorHooks();
        });
    } else {
        init(document);
        bindElementorHooks();
    }
})();
