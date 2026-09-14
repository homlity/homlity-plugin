(function () {
    'use strict';

    function createSearchableSelect(select) {
        if (!select || select.dataset.enhanced === '1') return;
        select.dataset.enhanced = '1';
        var multiple = select.multiple;
        var label = select.dataset.placeholder || select.getAttribute('aria-label')
            || (select.labels && select.labels[0] ? select.labels[0].textContent : '')
            || (select.options[0] ? select.options[0].text : 'Selecciona opciones');
        var widget = select.closest('.property-filter-widget');
        var messages = widget ? widget.dataset : {};

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
        var list = document.createElement('div');
        list.id = menuId;
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', label);
        list.setAttribute('aria-multiselectable', multiple ? 'true' : 'false');

        var searchBox = document.createElement('div');
        searchBox.className = 'hpf-multi__search-box';
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'hpf-multi__search';
        search.placeholder = select.dataset.searchPlaceholder || messages.searchPlaceholder || 'Buscar opciones…';
        search.setAttribute('aria-label', search.placeholder + ' ' + label);
        search.setAttribute('aria-controls', menuId);
        search.autocomplete = 'off';
        search.addEventListener('input', renderMenu);
        search.addEventListener('keydown', function (e) {
            // Enter must not submit the property search while filtering options.
            if (e.key === 'Enter') e.preventDefault();
            if (e.key === 'ArrowDown') {
                var first = list.querySelector('button:not(:disabled)');
                if (first) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
        searchBox.appendChild(search);
        menu.appendChild(searchBox);
        var empty = document.createElement('div');
        empty.className = 'hpf-multi__empty';
        empty.setAttribute('role', 'status');
        menu.appendChild(list);
        if (empty) menu.appendChild(empty);

        function normalize(value) {
            return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        }

        var options = Array.prototype.slice.call(select.options).filter(function (opt) {
            return !multiple || opt.value !== '';
        });

        function renderSelection() {
            chips.innerHTML = '';
            var selected = options.filter(function (opt) { return opt.selected; });

            if (!selected.length) {
                var placeholder = document.createElement('span');
                placeholder.className = 'hpf-multi__placeholder';
                placeholder.textContent = label;
                chips.appendChild(placeholder);
                return;
            }

            var summary = document.createElement('span');
            summary.className = 'hpf-multi__summary';
            summary.textContent = selected.length === 1
                ? selected[0].text
                : selected.length + ' seleccionados';
            chips.appendChild(summary);
        }

        function renderMenu() {
            list.innerHTML = '';
            var query = search ? normalize(search.value) : '';
            options.forEach(function (opt) {
                if (opt.hidden || (query && normalize(opt.text).indexOf(query) === -1)) return;
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'hpf-multi__item' + (opt.selected ? ' is-selected' : '');
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
                item.textContent = opt.text;
                item.disabled = select.disabled || opt.disabled || (opt.parentElement.tagName === 'OPTGROUP' && opt.parentElement.disabled);
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    opt.selected = multiple ? !opt.selected : true;
                    notifyChange();
                    if (multiple) {
                        search.focus();
                    } else {
                        closeMenu();
                        trigger.focus();
                    }
                });
                list.appendChild(item);
            });
            if (empty) {
                empty.hidden = list.children.length > 0;
                empty.textContent = empty.hidden ? '' : (select.dataset.searchEmpty || messages.searchEmpty || 'No se encontraron opciones');
            }
        }

        function openMenu() {
            if (select.disabled) return;
            renderMenu();
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (search) search.focus();
        }

        function closeMenu() {
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (search && search.value) {
                search.value = '';
                renderMenu();
            }
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
            renderSelection();
            renderMenu();
        });

        select.addEventListener('hpf:refresh', function () {
            renderSelection();
            renderMenu();
        });

        trigger.disabled = select.disabled;
        select.style.display = 'none';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);

        renderSelection();
        renderMenu();
    }

    function bindGeoDependencies(widget) {
        if (!widget || widget.dataset.geoDependenciesBound === '1') return;
        widget.dataset.geoDependenciesBound = '1';

        var city = widget.querySelector('select[name="ciudad[]"], select[name="ciudad"]');
        var locality = widget.querySelector('select[name="localidades[]"], select[name="localidades"]');
        var neighborhood = widget.querySelector('select[name="barrios[]"], select[name="barrios"]');
        if (!locality && !neighborhood) return;

        function selectedValues(select, dataKey) {
            if (!select) return [];
            return Array.prototype.slice.call(select.options)
                .filter(function (option) { return option.selected && option.value !== ''; })
                .map(function (option) { return dataKey ? String(option.dataset[dataKey] || '') : String(option.value); })
                .filter(Boolean);
        }

        function update() {
            var cities = selectedValues(city);
            var localityChanged = false;

            if (locality) {
                Array.prototype.slice.call(locality.options).forEach(function (option) {
                    if (option.value === '') return;
                    var visible = !cities.length || cities.indexOf(String(option.dataset.citySlug || '')) !== -1;
                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        option.selected = false;
                        localityChanged = true;
                    }
                });
            }

            var localityIds = selectedValues(locality, 'localityId');
            var neighborhoodChanged = false;
            if (neighborhood) {
                Array.prototype.slice.call(neighborhood.options).forEach(function (option) {
                    if (option.value === '') return;
                    var cityMatches = !cities.length || cities.indexOf(String(option.dataset.citySlug || '')) !== -1;
                    var localityMatches = !localityIds.length
                        || localityIds.indexOf(String(option.dataset.localityId || '0')) !== -1;
                    var visible = cityMatches && localityMatches;
                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        option.selected = false;
                        neighborhoodChanged = true;
                    }
                });
            }

            if (locality) locality.dispatchEvent(new Event('hpf:refresh'));
            if (neighborhood) neighborhood.dispatchEvent(new Event('hpf:refresh'));
            if (localityChanged) locality.dispatchEvent(new Event('change', { bubbles: true }));
            if (neighborhoodChanged) neighborhood.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (city) city.addEventListener('change', update);
        if (locality) locality.addEventListener('change', update);
        update();
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

        root.querySelectorAll('.property-filter-widget').forEach(bindGeoDependencies);

        root.querySelectorAll('.property-filter-widget select, .property-filter-multiselect').forEach(createSearchableSelect);

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
