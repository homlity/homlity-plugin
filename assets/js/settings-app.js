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

    if (config.nonce && typeof apiFetch.createNonceMiddleware === 'function') {
        apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
    }

    const root = document.getElementById('homlity-real-estate-settings-app');
    if (!root) {
        return;
    }

    function normalizeSettings(values) {
        const defaults = config.defaults || {};
        const merged = Object.assign({}, defaults, values || {});

        merged.listing_fields = Array.isArray(merged.listing_fields) ? merged.listing_fields.slice() : [];
        merged.listing_fields = optionOrder.filter((value) => merged.listing_fields.includes(value));
        merged.enable_analytics = !!merged.enable_analytics;

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

    function App() {
        const initial = normalizeSettings(config.settings);
        const [settings, setSettings] = useState(initial);
        const [baseline, setBaseline] = useState(JSON.stringify(initial));
        const [status, setStatus] = useState('idle');
        const [message, setMessage] = useState('');
        const [activeTab, setActiveTab] = useState('general');
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
                el(
                    'div',
                    { key: 'toolbar', className: 'homlity-settings__toolbar' },
                    [
                        el(
                            'div',
                            { key: 'copy', className: 'homlity-settings__toolbar-copy' },
                            [
                                el('h1', { key: 'title', className: 'homlity-settings__toolbar-title' }, config.pageTitle),
                                el(
                                    'div',
                                    { key: 'meta', className: 'homlity-settings__toolbar-meta' },
                                    [
                                        el('span', {
                                            key: 'badge',
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
                                        el('div', { key: 'swatch', className: 'homlity-settings__preview-swatch homlity-settings__preview-swatch--inline' }, [
                                            el('span', { key: 'label' }, __('Color activo', 'homlity-real-estate')),
                                            el('strong', { key: 'value' }, normalized.primary_color),
                                        ]),
                                    ]
                                ),
                            ]
                        ),
                        el(
                            'div',
                            { key: 'actions', className: 'homlity-settings__actions homlity-settings__actions--toolbar' },
                            [
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
                        ),
                    ]
                ),

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
                                { className: 'homlity-settings__field-grid homlity-settings__field-grid--four' },
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
                    ]
                ),
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
