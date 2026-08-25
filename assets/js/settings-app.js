(function () {
    const config = window.homlityPluginSettingsApp;
    if (!config) {
        return;
    }

    const apiFetch = window.wp && window.wp.apiFetch;
    const element = window.wp && window.wp.element;
    const i18n = window.wp && window.wp.i18n;

    if (!apiFetch || !element) {
        return;
    }

    const { createElement: el, Fragment, useEffect, useState } = element;
    const __ = i18n && i18n.__ ? i18n.__ : (value) => value;
    const optionOrder = (config.listingFieldOptions || []).map((option) => option.value);
    const locationTaxonomies = config.locationTaxonomies || {};
    const simulatorFields = config.simulatorFields || {};
    const shareMessageFields = config.shareMessageFields || {};

    if (config.nonce && typeof apiFetch.createNonceMiddleware === 'function') {
        apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
    }

    const root = document.getElementById('homlity-real-estate-settings-app');
    if (!root) {
        return;
    }
    const initialTab = ['general', 'social', 'arriendo', 'venta', 'consignment', 'versions', 'incidents'].includes(root.dataset.activeTab)
        ? root.dataset.activeTab
        : 'general';

    function normalizeSettings(values) {
        const defaults = config.defaults || {};
        const merged = Object.assign({}, defaults, values || {});

        merged.listing_fields = Array.isArray(merged.listing_fields) ? merged.listing_fields.slice() : [];
        merged.listing_fields = optionOrder.filter((value) => merged.listing_fields.includes(value));
        merged.enable_analytics = !!merged.enable_analytics;
        merged.preselect_location_in_search = !!merged.preselect_location_in_search;
        merged.share_messages = Object.assign(
            {},
            defaults.share_messages || {},
            merged.share_messages && typeof merged.share_messages === 'object' ? merged.share_messages : {}
        );

        ['default_country', 'default_state', 'default_city', 'default_neighborhood', 'archive_per_page'].forEach((key) => {
            if (merged[key] === '' || merged[key] === null || typeof merged[key] === 'undefined') {
                merged[key] = key === 'archive_per_page' ? defaults.archive_per_page : 0;
                return;
            }

            const numeric = parseInt(merged[key], 10);
            merged[key] = Number.isNaN(numeric) ? 0 : numeric;
        });

        const simulatorDefaults = defaults.simulators || {};
        const simulatorValues = merged.simulators && typeof merged.simulators === 'object' ? merged.simulators : {};
        merged.simulators = {
            arriendo: Object.assign({}, simulatorDefaults.arriendo || {}, simulatorValues.arriendo || {}),
            venta: Object.assign({}, simulatorDefaults.venta || {}, simulatorValues.venta || {}),
        };

        return merged;
    }

    function classNames() {
        return Array.prototype.slice.call(arguments).filter(Boolean).join(' ');
    }

    function Field(props) {
        return el(
            'label',
            { className: classNames('homlity-settings__field', props.full && 'homlity-settings__field--full') },
            [
                el('span', { key: 'label', className: 'homlity-settings__field-label' }, props.label),
                props.hint ? el('span', { key: 'hint', className: 'homlity-settings__field-hint' }, props.hint) : null,
                el(Fragment, { key: 'control' }, props.children),
            ]
        );
    }

    function Input(props) {
        return el(Field, {
            label: props.label,
            hint: props.hint,
            full: !!props.full,
            children: el('input', {
                className: 'homlity-settings__input',
                type: props.type || 'text',
                value: props.value,
                min: props.min,
                placeholder: props.placeholder,
                onChange: props.onChange,
            }),
        });
    }

    function Select(props) {
        return el(Field, {
            label: props.label,
            hint: props.hint,
            full: !!props.full,
            children: el(
                'select',
                {
                    className: 'homlity-settings__input homlity-settings__select',
                    value: props.value,
                    onChange: props.onChange,
                    disabled: !!props.disabled,
                },
                (props.options || []).map((option) =>
                    el('option', { key: option.value, value: option.value }, option.label)
                )
            ),
        });
    }

    function TextArea(props) {
        return el(Field, {
            label: props.label,
            hint: props.hint,
            full: true,
            children: el('textarea', {
                className: 'homlity-settings__input homlity-settings__textarea',
                value: props.value,
                rows: props.rows || 4,
                onChange: props.onChange,
            }),
        });
    }

    function CheckboxField(props) {
        return el(Field, {
            label: props.label,
            hint: props.hint,
            full: !!props.full,
            children: el(
                'label',
                { className: 'homlity-settings__checkbox' },
                [
                    el('input', {
                        key: 'input',
                        type: 'checkbox',
                        checked: !!props.checked,
                        onChange: props.onChange,
                    }),
                    el('span', { key: 'text' }, props.checkboxLabel || props.label),
                ]
            ),
        });
    }

    function ColorField(props) {
        const pickerValue = /^#[0-9a-fA-F]{6}$/.test(props.value) ? props.value : '#ff6752';

        return el(Field, {
            label: props.label,
            hint: props.hint,
            children: el(
                'div',
                { className: 'homlity-settings__color-row' },
                [
                    el('input', {
                        key: 'picker',
                        className: 'homlity-settings__color-picker',
                        type: 'color',
                        value: pickerValue,
                        onChange: props.onChange,
                    }),
                    el('input', {
                        key: 'hex',
                        className: 'homlity-settings__input',
                        type: 'text',
                        value: props.value,
                        placeholder: '#ff6752',
                        onChange: props.onChange,
                    }),
                ]
            ),
        });
    }

    function Section(props) {
        return el(
            'section',
            { className: classNames('homlity-settings__section', props.className) },
            [
                el(
                    'div',
                    { key: 'header', className: 'homlity-settings__section-header' },
                    [
                        el('span', { key: 'eyebrow', className: 'homlity-settings__eyebrow' }, props.eyebrow),
                        el('h2', { key: 'title', className: 'homlity-settings__section-title' }, props.title),
                        props.description
                            ? el('p', { key: 'description', className: 'homlity-settings__section-description' }, props.description)
                            : null,
                    ]
                ),
                el('div', { key: 'content', className: 'homlity-settings__section-content' }, props.children),
            ]
        );
    }

    function ListingOption(props) {
        return el(
            'button',
            {
                type: 'button',
                className: classNames('homlity-settings__toggle-card', props.active && 'is-active'),
                onClick: props.onClick,
            },
            [
                el('span', { key: 'dot', className: 'homlity-settings__toggle-dot' }),
                el(
                    'div',
                    { key: 'copy', className: 'homlity-settings__toggle-copy' },
                    [
                        el('strong', { key: 'label' }, props.label),
                        el('span', { key: 'description' }, props.description),
                    ]
                ),
            ]
        );
    }

    function Toggle(props) {
        return el(
            'label',
            { className: 'homlity-settings__toggle-field' },
            [
                el('input', {
                    key: 'input',
                    type: 'checkbox',
                    className: 'homlity-settings__toggle-input',
                    checked: !!props.checked,
                    onChange: props.onChange,
                }),
                el(
                    'span',
                    { key: 'copy', className: 'homlity-settings__toggle-copy' },
                    [
                        el('strong', { key: 'label' }, props.label),
                        props.hint ? el('span', { key: 'hint', className: 'homlity-settings__field-hint' }, props.hint) : null,
                    ]
                ),
            ]
        );
    }

    function LocationSelect(props) {
        return el(Select, {
            label: props.label,
            hint: props.hint,
            value: props.value ? String(props.value) : '',
            disabled: props.disabled,
            options: [{ value: '', label: props.placeholder }].concat(
                (props.options || []).map((option) => ({
                    value: String(option.id),
                    label: option.name,
                }))
            ),
            onChange: props.onChange,
        });
    }

    function VersionManager() {
        const [plugins, setPlugins] = useState([]);
        const [selected, setSelected] = useState({});
        const [loading, setLoading] = useState(true);
        const [installing, setInstalling] = useState('');
        const [notice, setNotice] = useState({ type: '', message: '' });

        function loadVersions(refresh, preserveNotice) {
            setLoading(true);
            if (!preserveNotice) {
                setNotice({ type: '', message: '' });
            }
            const suffix = refresh ? '?refresh=1' : '';

            return apiFetch({ path: config.pluginVersionsPath + suffix }).then((response) => {
                const items = Array.isArray(response.plugins) ? response.plugins : [];
                const defaults = {};
                items.forEach((plugin) => {
                    const release = (plugin.versions || []).find((item) => item.installable);
                    if (release) {
                        defaults[plugin.plugin] = release.version;
                    }
                });
                setPlugins(items);
                setSelected(defaults);
                if (response.message && !preserveNotice) {
                    setNotice({ type: 'info', message: response.message });
                }
            }).catch((error) => {
                setNotice({
                    type: 'error',
                    message: (error && error.message) || __('No fue posible consultar las versiones.', 'homlity-real-estate'),
                });
            }).finally(() => setLoading(false));
        }

        useEffect(() => {
            loadVersions(false);
        }, []);

        function installVersion(plugin) {
            const target = selected[plugin.plugin];
            const release = (plugin.versions || []).find((item) => item.version === target);
            if (!release) {
                return;
            }

            const action = release.direction === 'downgrade' ? __('bajar', 'homlity-real-estate') : __('subir', 'homlity-real-estate');
            const confirmed = window.confirm(
                __('Antes de continuar, confirma que tienes un respaldo reciente de archivos y base de datos. WordPress creará además un respaldo temporal del plugin. ¿Deseas ', 'homlity-real-estate')
                + action + ' ' + plugin.name + ' '
                + __('de la versión ', 'homlity-real-estate') + plugin.current_version
                + __(' a la versión ', 'homlity-real-estate') + target + '?'
            );
            if (!confirmed) {
                return;
            }

            setInstalling(plugin.plugin);
            setNotice({ type: 'info', message: __('Descargando, verificando e instalando la versión seleccionada…', 'homlity-real-estate') });
            apiFetch({
                path: config.pluginVersionInstallPath,
                method: 'POST',
                data: { plugin: plugin.plugin, version: target, confirm: true },
            }).then((response) => {
                setNotice({ type: 'success', message: response.message || __('Versión instalada correctamente.', 'homlity-real-estate') });
                return loadVersions(true, true);
            }).catch((error) => {
                setNotice({
                    type: 'error',
                    message: (error && error.message) || __('No fue posible cambiar la versión.', 'homlity-real-estate'),
                });
            }).finally(() => setInstalling(''));
        }

        return el(
            'div',
            { className: 'homlity-versions' },
            [
                el(
                    'div',
                    { key: 'toolbar', className: 'homlity-versions__toolbar' },
                    [
                        el('p', { key: 'copy' }, __('Solo se muestran plugins Homlity activos. Cada cambio se valida nuevamente contra Homi y usa el mecanismo de respaldo temporal de WordPress.', 'homlity-real-estate')),
                        el('button', {
                            key: 'refresh',
                            type: 'button',
                            className: 'homlity-settings__button homlity-settings__button--ghost',
                            disabled: loading || !!installing,
                            onClick: () => loadVersions(true),
                        }, loading ? __('Consultando…', 'homlity-real-estate') : __('Actualizar catálogo', 'homlity-real-estate')),
                    ]
                ),
                notice.message ? el(
                    'div',
                    {
                        key: 'notice',
                        className: classNames(
                            'homlity-versions__notice',
                            notice.type === 'error' && 'is-error',
                            notice.type === 'success' && 'is-success'
                        ),
                    },
                    notice.message
                ) : null,
                loading && plugins.length === 0
                    ? el('div', { key: 'loading', className: 'homlity-versions__empty' }, __('Consultando Homi…', 'homlity-real-estate'))
                    : null,
                !loading && plugins.length === 0
                    ? el('div', { key: 'empty', className: 'homlity-versions__empty' }, __('No hay plugins Homlity activos para administrar.', 'homlity-real-estate'))
                    : null,
                el(
                    'div',
                    { key: 'list', className: 'homlity-versions__list' },
                    plugins.map((plugin) => {
                        const installable = (plugin.versions || []).filter((release) => release.installable);
                        const target = selected[plugin.plugin] || '';
                        const release = installable.find((item) => item.version === target);
                        const busy = installing === plugin.plugin;
                        const directionLabel = release && release.direction === 'downgrade'
                            ? __('Downgrade', 'homlity-real-estate')
                            : __('Upgrade', 'homlity-real-estate');

                        return el(
                            'article',
                            { key: plugin.plugin, className: 'homlity-versions__card' },
                            [
                                el(
                                    'div',
                                    { key: 'header', className: 'homlity-versions__card-header' },
                                    [
                                        el('div', { key: 'identity' }, [
                                            el('h3', { key: 'name' }, plugin.name),
                                            el('code', { key: 'slug' }, plugin.product_slug),
                                        ]),
                                        el('div', { key: 'badges', className: 'homlity-versions__badges' }, [
                                            plugin.network_active
                                                ? el('span', { key: 'network', className: 'homlity-versions__badge' }, __('Red', 'homlity-real-estate'))
                                                : null,
                                            el('span', { key: 'current', className: 'homlity-versions__badge is-current' }, __('Actual: ', 'homlity-real-estate') + plugin.current_version),
                                        ]),
                                    ]
                                ),
                                el('p', { key: 'message', className: 'homlity-versions__message' }, plugin.message),
                                plugin.catalog_mode === 'latest'
                                    ? el('p', { key: 'limited', className: 'homlity-versions__warning' }, __('Homi no ofrece todavía el historial para este producto; los downgrades aparecerán cuando publique /versions.', 'homlity-real-estate'))
                                    : null,
                                installable.length > 0
                                    ? el(
                                        'div',
                                        { key: 'controls', className: 'homlity-versions__controls' },
                                        [
                                            el('label', { key: 'select-wrap', className: 'homlity-versions__select-wrap' }, [
                                                el('span', { key: 'label' }, __('Versión destino', 'homlity-real-estate')),
                                                el(
                                                    'select',
                                                    {
                                                        key: 'select',
                                                        className: 'homlity-settings__input homlity-settings__select',
                                                        value: target,
                                                        disabled: !!installing,
                                                        onChange: (event) => setSelected((current) => Object.assign({}, current, { [plugin.plugin]: event.target.value })),
                                                    },
                                                    installable.map((item) => el(
                                                        'option',
                                                        { key: item.version, value: item.version },
                                                        item.version + (item.direction === 'downgrade'
                                                            ? ' — ' + __('downgrade', 'homlity-real-estate')
                                                            : ' — ' + __('upgrade', 'homlity-real-estate'))
                                                    ))
                                                ),
                                            ]),
                                            el(
                                                'button',
                                                {
                                                    key: 'install',
                                                    type: 'button',
                                                    className: classNames(
                                                        'homlity-settings__button',
                                                        release && release.direction === 'downgrade'
                                                            ? 'homlity-versions__button--downgrade'
                                                            : 'homlity-settings__button--primary'
                                                    ),
                                                    disabled: !!installing || !release,
                                                    onClick: () => installVersion(plugin),
                                                },
                                                busy ? __('Instalando…', 'homlity-real-estate') : directionLabel
                                            ),
                                        ]
                                    )
                                    : el('div', { key: 'unavailable', className: 'homlity-versions__unavailable' }, __('No hay otra versión instalable autorizada por Homi.', 'homlity-real-estate')),
                                release ? el(
                                    'div',
                                    { key: 'meta', className: 'homlity-versions__release-meta' },
                                    [
                                        el('span', { key: 'integrity' }, release.integrity_verified
                                            ? __('SHA-256 disponible', 'homlity-real-estate')
                                            : __('Sin checksum publicado', 'homlity-real-estate')),
                                        release.requires_wp ? el('span', { key: 'wp' }, 'WordPress ≥ ' + release.requires_wp) : null,
                                        release.requires_php ? el('span', { key: 'php' }, 'PHP ≥ ' + release.requires_php) : null,
                                    ]
                                ) : null,
                            ]
                        );
                    })
                ),
            ]
        );
    }

    function IncidentDiagnostics() {
        const [data, setData] = useState(null);
        const [loading, setLoading] = useState(true);
        const [testing, setTesting] = useState(false);
        const [notice, setNotice] = useState({ type: '', message: '' });

        function load() {
            setLoading(true);
            return apiFetch({ path: config.errorDiagnosticsPath }).then((response) => {
                setData(response);
            }).catch((error) => {
                setNotice({
                    type: 'error',
                    message: (error && error.message) || __('No fue posible cargar el diagnóstico.', 'homlity-real-estate'),
                });
            }).finally(() => setLoading(false));
        }

        useEffect(() => {
            load();
        }, []);

        function testConnection() {
            setTesting(true);
            setNotice({ type: '', message: '' });
            apiFetch({ path: config.errorConnectionTestPath, method: 'POST' }).then((response) => {
                setNotice({ type: response.success ? 'success' : 'error', message: response.message || '' });
                return load();
            }).catch((error) => {
                setNotice({
                    type: 'error',
                    message: (error && error.message) || __('La validación de conexión falló.', 'homlity-real-estate'),
                });
            }).finally(() => setTesting(false));
        }

        if (loading && !data) {
            return el('div', { className: 'homlity-incidents__empty' }, __('Cargando diagnóstico…', 'homlity-real-estate'));
        }

        const queue = (data && data.queue) || {};
        const reporter = (data && data.reporter) || {};
        const plugins = (data && Array.isArray(data.plugins)) ? data.plugins : [];
        const metric = (label, value, className) => el('div', { className: classNames('homlity-incidents__metric', className) }, [
            el('span', { key: 'label' }, label),
            el('strong', { key: 'value' }, value || '—'),
        ]);

        return el('div', { className: 'homlity-incidents' }, [
            el('div', { key: 'toolbar', className: 'homlity-incidents__toolbar' }, [
                el('p', { key: 'copy' }, __('El colector solo conserva errores fatales o fallos finales de sincronización originados en plugins oficiales. Los datos mostrados están enmascarados.', 'homlity-real-estate')),
                el('div', { key: 'buttons', className: 'homlity-incidents__buttons' }, [
                    el('button', {
                        key: 'refresh', type: 'button', className: 'homlity-settings__button homlity-settings__button--ghost',
                        disabled: loading || testing, onClick: load,
                    }, loading ? __('Actualizando…', 'homlity-real-estate') : __('Actualizar', 'homlity-real-estate')),
                    el('button', {
                        key: 'test', type: 'button', className: 'homlity-settings__button homlity-settings__button--primary',
                        disabled: loading || testing, onClick: testConnection,
                    }, testing ? __('Validando…', 'homlity-real-estate') : __('Probar conexión de incidencias', 'homlity-real-estate')),
                ]),
            ]),
            notice.message ? el('div', {
                key: 'notice',
                className: classNames('homlity-versions__notice', notice.type === 'error' && 'is-error', notice.type === 'success' && 'is-success'),
            }, notice.message) : null,
            el('div', { key: 'metrics', className: 'homlity-incidents__metrics' }, [
                metric(__('Reporter', 'homlity-real-estate'), reporter.status === 'enabled' ? __('Activo', 'homlity-real-estate') : (reporter.status || '—')),
                metric(__('Colector', 'homlity-real-estate'), reporter.collector ? reporter.collector + '@' + (reporter.version || 'unknown') : '—'),
                metric(__('En cola', 'homlity-real-estate'), String(queue.queued || 0)),
                metric(__('Bloqueados por licencia', 'homlity-real-estate'), String(queue.blocked || 0), queue.blocked ? 'is-warning' : ''),
                metric(__('Último envío', 'homlity-real-estate'), queue.last_success_at),
                metric(__('Próximo reintento', 'homlity-real-estate'), queue.next_retry_at || (data && data.schedule)),
                metric(__('Último estado HTTP', 'homlity-real-estate'), queue.last_http_status ? String(queue.last_http_status) : '—'),
                metric(__('Diagnóstico local', 'homlity-real-estate'), queue.last_local_error || __('Sin incidencias', 'homlity-real-estate'), queue.last_local_error ? 'is-warning' : ''),
            ]),
            queue.license_revalidation_required ? el('p', { key: 'license-warning', className: 'homlity-incidents__warning' }, __('Homi respondió 401/403. Los eventos afectados no se reintentarán hasta revalidar la licencia.', 'homlity-real-estate')) : null,
            el('div', { key: 'table-wrap', className: 'homlity-incidents__table-wrap' }, [
                el('table', { key: 'table', className: 'widefat striped homlity-incidents__table' }, [
                    el('thead', { key: 'head' }, el('tr', null, [
                        el('th', { key: 'plugin' }, __('Plugin', 'homlity-real-estate')),
                        el('th', { key: 'version' }, __('Versión', 'homlity-real-estate')),
                        el('th', { key: 'license' }, __('Licencia', 'homlity-real-estate')),
                        el('th', { key: 'site' }, __('Site ID', 'homlity-real-estate')),
                        el('th', { key: 'ready' }, __('Listo', 'homlity-real-estate')),
                    ])),
                    el('tbody', { key: 'body' }, plugins.length ? plugins.map((plugin) => el('tr', { key: plugin.plugin }, [
                        el('td', { key: 'plugin' }, el('code', null, plugin.plugin)),
                        el('td', { key: 'version' }, plugin.version || '—'),
                        el('td', { key: 'license' }, plugin.license || '—'),
                        el('td', { key: 'site' }, plugin.site_id || '—'),
                        el('td', { key: 'ready' }, plugin.license_valid ? __('Sí', 'homlity-real-estate') : __('No', 'homlity-real-estate')),
                    ])) : el('tr', null, el('td', { colSpan: 5 }, __('No se detectaron plugins oficiales.', 'homlity-real-estate')))),
                ]),
            ]),
        ]);
    }

    function App() {
        const initial = normalizeSettings(config.settings);
        const [settings, setSettings] = useState(initial);
        const [baseline, setBaseline] = useState(JSON.stringify(initial));
        const [status, setStatus] = useState('idle');
        const [message, setMessage] = useState('');
        const [activeTab, setActiveTab] = useState(initialTab);
        const [locationOptions, setLocationOptions] = useState({
            country: [],
            state: [],
            city: [],
            neighborhood: [],
        });

        const normalized = normalizeSettings(settings);
        const serialized = JSON.stringify(normalized);
        const isDirty = serialized !== baseline;

        function fetchLocationTerms(level, parentId) {
            const taxonomy = locationTaxonomies[level];
            if (!taxonomy) {
                return Promise.resolve([]);
            }

            const path = config.locationTermsPath + '?taxonomy=' + encodeURIComponent(taxonomy) + '&parent=' + encodeURIComponent(parentId || 0);
            return apiFetch({ path }).then((response) => (Array.isArray(response) ? response : []));
        }

        useEffect(() => {
            fetchLocationTerms('country', 0).then((terms) => {
                setLocationOptions((current) => Object.assign({}, current, { country: terms }));
            });
        }, []);

        useEffect(() => {
            if (!normalized.default_country) {
                setLocationOptions((current) => Object.assign({}, current, { state: [], city: [], neighborhood: [] }));
                return;
            }

            fetchLocationTerms('state', normalized.default_country).then((terms) => {
                setLocationOptions((current) => Object.assign({}, current, { state: terms }));
            });
        }, [normalized.default_country]);

        useEffect(() => {
            if (!normalized.default_state) {
                setLocationOptions((current) => Object.assign({}, current, { city: [], neighborhood: [] }));
                return;
            }

            fetchLocationTerms('city', normalized.default_state).then((terms) => {
                setLocationOptions((current) => Object.assign({}, current, { city: terms }));
            });
        }, [normalized.default_state]);

        useEffect(() => {
            if (!normalized.default_city) {
                setLocationOptions((current) => Object.assign({}, current, { neighborhood: [] }));
                return;
            }

            fetchLocationTerms('neighborhood', normalized.default_city).then((terms) => {
                setLocationOptions((current) => Object.assign({}, current, { neighborhood: terms }));
            });
        }, [normalized.default_city]);

        function updateField(key, value) {
            setSettings((current) => Object.assign({}, current, { [key]: value }));
        }

        function updateNumberField(key, value) {
            updateField(key, value === '' ? '' : parseInt(value, 10) || 0);
        }

        function updateSimulatorField(section, key, value) {
            setSettings((current) => Object.assign({}, current, {
                simulators: Object.assign({}, current.simulators || {}, {
                    [section]: Object.assign({}, (current.simulators && current.simulators[section]) || {}, {
                        [key]: value,
                    }),
                }),
            }));
        }

        function updateShareMessage(platform, value) {
            setSettings((current) => Object.assign({}, current, {
                share_messages: Object.assign({}, current.share_messages || {}, {
                    [platform]: value,
                }),
            }));
        }

        function updateLocationField(level, value) {
            const numericValue = value === '' ? 0 : parseInt(value, 10) || 0;

            setSettings((current) => {
                const next = Object.assign({}, current);

                if (level === 'country') {
                    next.default_country = numericValue;
                    next.default_state = 0;
                    next.default_city = 0;
                    next.default_neighborhood = 0;
                } else if (level === 'state') {
                    next.default_state = numericValue;
                    next.default_city = 0;
                    next.default_neighborhood = 0;
                } else if (level === 'city') {
                    next.default_city = numericValue;
                    next.default_neighborhood = 0;
                } else if (level === 'neighborhood') {
                    next.default_neighborhood = numericValue;
                }

                return next;
            });
        }

        function toggleListingField(value) {
            setSettings((current) => {
                const active = Array.isArray(current.listing_fields) ? current.listing_fields.slice() : [];
                const next = active.includes(value)
                    ? active.filter((item) => item !== value)
                    : active.concat(value);

                return Object.assign({}, current, {
                    listing_fields: optionOrder.filter((item) => next.includes(item)),
                });
            });
        }

        function resetToDefaults() {
            const next = normalizeSettings(config.defaults);
            setSettings(next);
            setStatus('idle');
            setMessage(__('Valores restablecidos localmente. Guarda para aplicar los cambios.', 'homlity-real-estate'));
        }

        function saveSettings() {
            setStatus('saving');
            setMessage(__('Guardando configuración…', 'homlity-real-estate'));

            apiFetch({
                path: config.savePath,
                method: 'POST',
                data: { settings: normalized },
            }).then((response) => {
                const next = normalizeSettings(response.settings);
                setSettings(next);
                setBaseline(JSON.stringify(next));
                setStatus('saved');
                setMessage(response.message || __('Configuración guardada correctamente.', 'homlity-real-estate'));
            }).catch((error) => {
                setStatus('error');
                setMessage((error && error.message) || __('No fue posible guardar la configuración.', 'homlity-real-estate'));
            });
        }

        function renderSimulatorField(sectionKey, fieldKey, fieldConfig) {
            const fieldType = fieldConfig.type || 'text';
            const sectionValues = ((normalized.simulators || {})[sectionKey]) || {};
            const value = Object.prototype.hasOwnProperty.call(sectionValues, fieldKey) ? sectionValues[fieldKey] : '';

            if (fieldType === 'checkbox') {
                return el(CheckboxField, {
                    key: fieldKey,
                    label: fieldConfig.label,
                    hint: fieldConfig.help,
                    checked: value === '1',
                    checkboxLabel: fieldConfig.checkboxLabel,
                    onChange: (event) => updateSimulatorField(sectionKey, fieldKey, event.target.checked ? '1' : '0'),
                });
            }

            if (fieldType === 'select') {
                return el(Select, {
                    key: fieldKey,
                    label: fieldConfig.label,
                    hint: fieldConfig.help,
                    value: value,
                    options: Object.keys(fieldConfig.options || {}).map((optionValue) => ({
                        value: optionValue,
                        label: fieldConfig.options[optionValue],
                    })),
                    onChange: (event) => updateSimulatorField(sectionKey, fieldKey, event.target.value),
                });
            }

            if (fieldType === 'textarea') {
                return el(TextArea, {
                    key: fieldKey,
                    label: fieldConfig.label,
                    hint: fieldConfig.help,
                    value: value,
                    onChange: (event) => updateSimulatorField(sectionKey, fieldKey, event.target.value),
                });
            }

            return el(Input, {
                key: fieldKey,
                label: fieldConfig.label,
                hint: fieldConfig.help,
                value: value,
                onChange: (event) => updateSimulatorField(sectionKey, fieldKey, event.target.value),
            });
        }

        return el(
            'div',
            {
                className: 'homlity-settings-page',
                style: {
                    '--homlity-settings-preview-color': normalized.primary_color || '#ff6752',
                },
            },
            [
                message
                    ? el(
                        'div',
                        { key: 'notice', className: classNames('homlity-settings__notice', status === 'error' && 'is-error', status === 'saved' && 'is-success') },
                        message
                    )
                    : null,

                el(
                    'div',
                    { key: 'tabs', className: 'homlity-settings__tabs' },
                    [
                        el('button', {
                            key: 'general',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'general' && 'is-active'),
                            onClick: () => setActiveTab('general'),
                        }, __('General', 'homlity-real-estate')),
                        el('button', {
                            key: 'social',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'social' && 'is-active'),
                            onClick: () => setActiveTab('social'),
                        }, __('Mensajes sociales', 'homlity-real-estate')),
                        el('button', {
                            key: 'arriendo',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'arriendo' && 'is-active'),
                            onClick: () => setActiveTab('arriendo'),
                        }, __('Simulador arriendo', 'homlity-real-estate')),
                        el('button', {
                            key: 'venta',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'venta' && 'is-active'),
                            onClick: () => setActiveTab('venta'),
                        }, __('Simulador venta', 'homlity-real-estate')),
                        el('button', {
                            key: 'consignment',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'consignment' && 'is-active'),
                            onClick: () => setActiveTab('consignment'),
                        }, __('Consignación', 'homlity-real-estate')),
                        el('button', {
                            key: 'versions',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'versions' && 'is-active'),
                            onClick: () => setActiveTab('versions'),
                        }, __('Versiones', 'homlity-real-estate')),
                        el('button', {
                            key: 'incidents',
                            type: 'button',
                            className: classNames('homlity-settings__tab', activeTab === 'incidents' && 'is-active'),
                            onClick: () => setActiveTab('incidents'),
                        }, __('Incidencias', 'homlity-real-estate')),
                    ]
                ),

                el(
                    'div',
                    { key: 'grid', className: 'homlity-settings__grid' },
                    [
                        activeTab === 'general' ? el(
                            Section,
                            {
                                key: 'experience',
                                eyebrow: __('General', 'homlity-real-estate'),
                                title: __('Configuración principal', 'homlity-real-estate'),
                            },
                            el(
                                'div',
                                { className: 'homlity-settings__field-grid homlity-settings__field-grid--four' },
                                [
                                    el(ColorField, {
                                        key: 'primary_color',
                                        label: __('Color principal de la inmobiliaria', 'homlity-real-estate'),
                                        value: normalized.primary_color,
                                        onChange: (event) => updateField('primary_color', event.target.value),
                                    }),
                                    el(Select, {
                                        key: 'currency',
                                        label: __('Moneda por defecto', 'homlity-real-estate'),
                                        value: normalized.base_currency,
                                        options: (config.currencies || []).map((currency) => ({ value: currency, label: currency })),
                                        onChange: (event) => updateField('base_currency', event.target.value),
                                    }),
                                    el(Select, {
                                        key: 'map_provider',
                                        label: __('Mapa por defecto', 'homlity-real-estate'),
                                        value: normalized.default_map_provider,
                                        options: config.mapProviderOptions || [],
                                        onChange: (event) => updateField('default_map_provider', event.target.value),
                                    }),
                                    el(Select, {
                                        key: 'gallery_mode',
                                        label: __('Slider detalle inmueble', 'homlity-real-estate'),
                                        value: normalized.detail_gallery_mode,
                                        options: config.galleryModeOptions || [],
                                        onChange: (event) => updateField('detail_gallery_mode', event.target.value),
                                    }),
                                ]
                            )
                        ) : null,

                        activeTab === 'general' ? el(
                            Section,
                            {
                                key: 'location',
                                eyebrow: __('Geografía', 'homlity-real-estate'),
                                title: __('Ubicación base', 'homlity-real-estate'),
                            },
                            el(
                                'div',
                                { key: 'levels', className: 'homlity-settings__field-grid homlity-settings__field-grid--four' },
                                [
                                    el(LocationSelect, {
                                        key: 'country',
                                        label: __('País', 'homlity-real-estate'),
                                        value: normalized.default_country,
                                        options: locationOptions.country,
                                        placeholder: __('Selecciona país', 'homlity-real-estate'),
                                        onChange: (event) => updateLocationField('country', event.target.value),
                                    }),
                                    el(LocationSelect, {
                                        key: 'state',
                                        label: __('Departamento / Provincia', 'homlity-real-estate'),
                                        value: normalized.default_state,
                                        options: locationOptions.state,
                                        placeholder: __('Selecciona departamento / provincia', 'homlity-real-estate'),
                                        disabled: !normalized.default_country,
                                        onChange: (event) => updateLocationField('state', event.target.value),
                                    }),
                                    el(LocationSelect, {
                                        key: 'city',
                                        label: __('Ciudad / Municipio', 'homlity-real-estate'),
                                        value: normalized.default_city,
                                        options: locationOptions.city,
                                        placeholder: __('Selecciona ciudad / municipio', 'homlity-real-estate'),
                                        disabled: !normalized.default_state,
                                        onChange: (event) => updateLocationField('city', event.target.value),
                                    }),
                                    el(LocationSelect, {
                                        key: 'neighborhood',
                                        label: __('Barrio', 'homlity-real-estate'),
                                        value: normalized.default_neighborhood,
                                        options: locationOptions.neighborhood,
                                        placeholder: __('Selecciona barrio', 'homlity-real-estate'),
                                        disabled: !normalized.default_city,
                                        onChange: (event) => updateLocationField('neighborhood', event.target.value),
                                    }),
                                ]
                            ),
                            el(
                                'div',
                                { key: 'preselect', className: 'homlity-settings__field-grid homlity-settings__field-grid--one' },
                                el(Toggle, {
                                    key: 'preselect_location_in_search',
                                    label: __('Preseleccionar esta ubicación en el buscador', 'homlity-real-estate'),
                                    hint: __('El buscador de inmuebles aparecerá con la ubicación de arriba ya elegida. El visitante puede cambiarla o quitarla, y si llega con una búsqueda propia se respeta la suya.', 'homlity-real-estate'),
                                    checked: normalized.preselect_location_in_search,
                                    onChange: (event) => updateField('preselect_location_in_search', event.target.checked),
                                })
                            )
                        ) : null,

                        activeTab === 'general' ? el(
                            Section,
                            {
                                key: 'listing',
                                eyebrow: __('Catálogo', 'homlity-real-estate'),
                                title: __('Configuración del catálogo', 'homlity-real-estate'),
                            },
                            [
                                el(
                                    'div',
                                    { key: 'fields', className: 'homlity-settings__field-grid homlity-settings__field-grid--two' },
                                    [
                                        el(Input, {
                                            key: 'per-page',
                                            label: __('Inmuebles por página', 'homlity-real-estate'),
                                            type: 'number',
                                            min: 1,
                                            value: normalized.archive_per_page,
                                            onChange: (event) => updateNumberField('archive_per_page', event.target.value),
                                        }),
                                        el(Select, {
                                            key: 'order',
                                            label: __('Orden por defecto', 'homlity-real-estate'),
                                            value: normalized.archive_order,
                                            options: config.archiveOrderOptions || [],
                                            onChange: (event) => updateField('archive_order', event.target.value),
                                        }),
                                    ]
                                ),
                                el(
                                    'div',
                                    { key: 'toggles', className: 'homlity-settings__toggle-grid' },
                                    (config.listingFieldOptions || []).map((option) =>
                                        el(ListingOption, {
                                            key: option.value,
                                            label: option.label,
                                            description: option.description,
                                            active: normalized.listing_fields.includes(option.value),
                                            onClick: () => toggleListingField(option.value),
                                        })
                                    )
                                ),
                            ]
                        ) : null,

                        activeTab === 'general' ? el(
                            Section,
                            {
                                key: 'privacy',
                                eyebrow: __('Privacidad', 'homlity-real-estate'),
                                title: __('Analítica y seguimiento', 'homlity-real-estate'),
                                description: __('Desactivado por defecto. Al habilitarlo, la analítica de inmuebles se almacena localmente. Eres responsable de obtener el consentimiento de los visitantes según la normativa aplicable (RGPD, CCPA, etc.).', 'homlity-real-estate'),
                            },
                            el(
                                'div',
                                { className: 'homlity-settings__field-grid homlity-settings__field-grid--one' },
                                el(Toggle, {
                                    key: 'enable_analytics',
                                    label: __('Activar analítica interna de inmuebles', 'homlity-real-estate'),
                                    hint: __('Registra visitas, clics de contacto y descargas de fichas técnicas. Los datos se guardan en la base de datos local y nunca se envían a servidores externos.', 'homlity-real-estate'),
                                    checked: normalized.enable_analytics,
                                    onChange: (event) => updateField('enable_analytics', event.target.checked),
                                })
                            )
                        ) : null,

                        activeTab === 'social' ? el(
                            Section,
                            {
                                key: 'social-messages',
                                eyebrow: __('Compartir inmuebles', 'homlity-real-estate'),
                                title: __('Mensajes predeterminados por red social', 'homlity-real-estate'),
                                description: __('Personaliza el mensaje utilizado al compartir un inmueble. Variables disponibles: {title}, {code}, {url}, {summary}, {bedrooms}, {bathrooms}, {parking}, {area}, {price}. La URL se enviará una sola vez.', 'homlity-real-estate'),
                            },
                            el(
                                'div',
                                { className: 'homlity-settings__field-grid homlity-settings__field-grid--two' },
                                Object.keys(shareMessageFields).map((platform) => {
                                    const field = shareMessageFields[platform] || {};
                                    return el(TextArea, {
                                        key: platform,
                                        label: field.label || platform,
                                        hint: field.description || '',
                                        rows: platform === 'email' || platform === 'whatsapp' ? 5 : 3,
                                        value: (normalized.share_messages && normalized.share_messages[platform]) || '',
                                        onChange: (event) => updateShareMessage(platform, event.target.value),
                                    });
                                })
                            )
                        ) : null,

                        activeTab === 'arriendo' && simulatorFields.arriendo ? el(
                            Section,
                            {
                                key: 'simulator-rent',
                                eyebrow: __('Simuladores', 'homlity-real-estate'),
                                title: simulatorFields.arriendo.title,
                                description: __('Ajusta los valores por defecto y el comportamiento del simulador de arriendo. Shortcodes: [homlity_simulador modo="arriendo"] y [homlity_simulador_arriendo].', 'homlity-real-estate'),
                            },
                            el(
                                'div',
                                { className: 'homlity-settings__field-grid homlity-settings__field-grid--two' },
                                Object.keys(simulatorFields.arriendo.fields || {}).map((fieldKey) =>
                                    renderSimulatorField('arriendo', fieldKey, simulatorFields.arriendo.fields[fieldKey])
                                )
                            )
                        ) : null,

                        activeTab === 'venta' && simulatorFields.venta ? el(
                            Section,
                            {
                                key: 'simulator-sale',
                                eyebrow: __('Simuladores', 'homlity-real-estate'),
                                title: simulatorFields.venta.title,
                                description: __('Define los porcentajes y etiquetas base del simulador de venta. Shortcodes: [homlity_simulador modo="venta"] y [homlity_simulador_venta].', 'homlity-real-estate'),
                            },
                            el(
                                'div',
                                { className: 'homlity-settings__field-grid homlity-settings__field-grid--two' },
                                Object.keys(simulatorFields.venta.fields || {}).map((fieldKey) =>
                                    renderSimulatorField('venta', fieldKey, simulatorFields.venta.fields[fieldKey])
                                )
                            )
                        ) : null,

                        activeTab === 'consignment' ? el(
                            Section,
                            {
                                key: 'consignment-settings',
                                eyebrow: __('Consignación', 'homlity-real-estate'),
                                title: __('Configuración de consignación', 'homlity-real-estate'),
                                description: __('Administra el formulario público de consignación de inmuebles desde esta misma pantalla.', 'homlity-real-estate'),
                            },
                            el('div', {
                                className: 'homlity-settings__consignment',
                                dangerouslySetInnerHTML: { __html: config.consignmentHtml || '' },
                            })
                        ) : null,
                        activeTab === 'versions' ? el(
                            Section,
                            {
                                key: 'plugin-versions',
                                className: 'homlity-settings__section--wide',
                                eyebrow: __('Mantenimiento', 'homlity-real-estate'),
                                title: __('Versiones de plugins Homlity', 'homlity-real-estate'),
                                description: __('Realiza upgrades o vuelve a una versión anterior publicada y autorizada por Homi.', 'homlity-real-estate'),
                            },
                            el(VersionManager)
                        ) : null,
                        activeTab === 'incidents' ? el(
                            Section,
                            {
                                key: 'error-diagnostics',
                                className: 'homlity-settings__section--wide',
                                eyebrow: __('Soporte', 'homlity-real-estate'),
                                title: __('Diagnóstico de incidencias Homlity', 'homlity-real-estate'),
                                description: __('Estado local de captura y entrega segura de errores a Homi.', 'homlity-real-estate'),
                            },
                            el(IncidentDiagnostics)
                        ) : null,
                    ]
                ),
                !['versions', 'incidents'].includes(activeTab) ? el(
                    'div',
                    { key: 'bottom-actions', className: 'homlity-settings__actions homlity-settings__actions--bottom' },
                    [
                        el('span', {
                            key: 'status',
                            className: classNames(
                                'homlity-settings__status',
                                status === 'saved' && 'is-saved',
                                status === 'error' && 'is-error',
                                status === 'saving' && 'is-saving',
                                isDirty && status !== 'saving' && status !== 'error' && 'is-dirty'
                            ),
                        }, status === 'saving'
                            ? __('Guardando…', 'homlity-real-estate')
                            : status === 'saved'
                                ? __('Sincronizado', 'homlity-real-estate')
                                : status === 'error'
                                    ? __('Error', 'homlity-real-estate')
                                    : isDirty
                                        ? __('Cambios pendientes', 'homlity-real-estate')
                                        : __('Estable', 'homlity-real-estate')),
                        el(
                            'button',
                            {
                                key: 'save',
                                type: 'button',
                                className: 'homlity-settings__button homlity-settings__button--primary',
                                onClick: saveSettings,
                                disabled: status === 'saving',
                            },
                            status === 'saving' ? __('Guardando…', 'homlity-real-estate') : config.saveLabel
                        ),
                        el(
                            'button',
                            {
                                key: 'reset',
                                type: 'button',
                                className: 'homlity-settings__button homlity-settings__button--ghost',
                                onClick: resetToDefaults,
                                disabled: status === 'saving',
                            },
                            config.resetLabel
                        ),
                    ]
                ) : null,
            ]
        );
    }

    const app = el(App);
    if (typeof element.createRoot === 'function') {
        element.createRoot(root).render(app);
    } else if (typeof element.render === 'function') {
        element.render(app, root);
    }
}());
