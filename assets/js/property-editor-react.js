(function () {
  'use strict';

  var wpEl = window.wp && window.wp.element;
  if (!wpEl || !window.homlityPropertyEditorApp) return;

  var h = wpEl.createElement;
  var useEffect = wpEl.useEffect;
  var useMemo = wpEl.useMemo;
  var useState = wpEl.useState;
  var useRef = wpEl.useRef;

  var app = window.homlityPropertyEditorApp;
  var restRoot = (app.restUrl || '').replace(/\/$/, '');
  var nonce = app.nonce || '';

  function api(path, params) {
    var url = new URL(restRoot + path);
    Object.keys(params || {}).forEach(function (k) {
      if (params[k] !== '' && params[k] !== null && params[k] !== undefined) {
        url.searchParams.set(k, params[k]);
      }
    });
    return fetch(url.toString(), { headers: { 'X-WP-Nonce': nonce } }).then(function (r) { return r.json(); });
  }

  function root() {
    var postStuff = document.getElementById('poststuff');
    if (!postStuff) return null;
    var el = document.getElementById('homlity-property-editor-react-root');
    if (!el) {
      el = document.createElement('div');
      el.id = 'homlity-property-editor-react-root';
      postStuff.parentNode.insertBefore(el, postStuff);
    }
    return el;
  }

  function syncCore(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.value = value;
    if (id === 'content' && window.tinymce && window.tinymce.get('content')) {
      window.tinymce.get('content').setContent(value || '');
    }
  }

  function Select(props) {
    var opts = props.options || [];
    return h('select', {
      name: props.name,
      value: props.value,
      required: !!props.required,
      onChange: function (e) { props.onChange(e.target.value); }
    },
      h('option', { value: '' }, props.placeholder || 'Selecciona'),
      opts.map(function (o) { return h('option', { key: o.id, value: String(o.id) }, o.name || o.label); })
    );
  }

  function Input(props) {
    return h('input', {
      type: props.type || 'text',
      name: props.name,
      value: props.value,
      placeholder: props.placeholder || '',
      required: !!props.required,
      readOnly: !!props.readOnly,
      onChange: function (e) { props.onChange && props.onChange(e.target.value); }
    });
  }

  function normalizeUrlListInput(value) {
    return String(value || '')
      .split(/\r?\n|,/)
      .map(function (v) { return String(v || '').trim(); })
      .filter(function (v, i, arr) { return v && arr.indexOf(v) === i; });
  }

  function normalizeUrlListFromMeta(value) {
    function collectFromAny(input) {
      if (Array.isArray(input)) {
        return input.reduce(function (acc, item) {
          if (typeof item === 'string') {
            acc.push(item);
            return acc;
          }
          if (item && typeof item === 'object') {
            if (typeof item.url === 'string') {
              acc.push(item.url);
            } else {
              Object.keys(item).forEach(function (k) {
                var maybe = item[k];
                if (typeof maybe === 'string') acc.push(maybe);
              });
            }
          }
          return acc;
        }, []);
      }

      if (input && typeof input === 'object') {
        return Object.keys(input).map(function (k) { return input[k]; }).filter(function (v) { return typeof v === 'string'; });
      }

      if (typeof input === 'string') {
        var raw = input.trim();
        if (!raw) return [];
        if (raw[0] === '[' || raw[0] === '{') {
          try {
            return collectFromAny(JSON.parse(raw));
          } catch (e) {
            return normalizeUrlListInput(raw);
          }
        }
        return normalizeUrlListInput(raw);
      }

      return [];
    }

    return collectFromAny(value)
      .map(function (v) { return String(v || '').trim(); })
      .filter(function (v, i, arr) { return v && arr.indexOf(v) === i; });
  }

  function normalizeSingleUrlFromMeta(value) {
    var urls = normalizeUrlListFromMeta(value);
    if (urls.length) return urls[0];
    return String(value || '').trim();
  }

  function Field(props) {
    return h('label', { className: 'hpe-field' + (props.error ? ' hpe-field-has-error' : '') },
      h('span', { className: 'hpe-label' }, props.label, props.required ? h('em', { className: 'hpe-required' }, ' *') : null),
      props.children,
      props.error ? h('small', { className: 'hpe-field-error' }, props.error) : null
    );
  }

  function MultiSelectChips(props) {
    var options = props.options || [];
    var selectedValues = (props.value || []).map(function (v) { return String(v); });
    var onChange = props.onChange;
    var placeholder = props.placeholder || 'Buscar...';
    var _a = useState(''), query = _a[0], setQuery = _a[1];
    var _b = useState(false), open = _b[0], setOpen = _b[1];
    var wrapRef = useRef(null);

    useEffect(function () {
      function closeWhenOutside(event) {
        if (wrapRef.current && !wrapRef.current.contains(event.target)) {
          setOpen(false);
        }
      }

      document.addEventListener('pointerdown', closeWhenOutside);
      document.addEventListener('focusin', closeWhenOutside);

      return function () {
        document.removeEventListener('pointerdown', closeWhenOutside);
        document.removeEventListener('focusin', closeWhenOutside);
      };
    }, []);

    var selectedMap = {};
    selectedValues.forEach(function (id) { selectedMap[String(id)] = true; });

    var selectedItems = options.filter(function (o) { return !!selectedMap[String(o.id)]; });
    var filteredItems = options.filter(function (o) {
      if (selectedMap[String(o.id)]) return false;
      if (!query) return true;
      return String(o.name || '').toLowerCase().indexOf(query.toLowerCase()) >= 0;
    });

    function addItem(id) {
      var sid = String(id);
      if (selectedMap[sid]) return;
      onChange(selectedValues.concat([sid]));
      setQuery('');
      setOpen(true);
    }

    function removeItem(id) {
      var sid = String(id);
      onChange(selectedValues.filter(function (v) { return String(v) !== sid; }));
    }

    return h('div', { className: 'hpe-ms-wrap', ref: wrapRef },
      h('div', {
        className: 'hpe-ms-control',
        onClick: function () { setOpen(true); }
      },
        selectedItems.map(function (item) {
          return h('span', { key: String(item.id), className: 'hpe-ms-chip' },
            h('span', null, item.name),
            h('button', { type: 'button', onClick: function (e) { e.stopPropagation(); removeItem(item.id); } }, '×')
          );
        }),
        h('input', {
          type: 'text',
          value: query,
          placeholder: placeholder,
          onFocus: function () { setOpen(true); },
          onChange: function (e) { setQuery(e.target.value); setOpen(true); },
          onKeyDown: function (e) {
            if (e.key === 'Escape') {
              setOpen(false);
              e.currentTarget.blur();
            }
          }
        })
      ),
      open && h('div', { className: 'hpe-ms-menu' },
        filteredItems.length
          ? filteredItems.map(function (item) {
              return h('button', {
                key: String(item.id),
                type: 'button',
                className: 'hpe-ms-option',
                onClick: function () { addItem(item.id); }
              }, item.name);
            })
          : h('div', { className: 'hpe-ms-empty' }, 'Sin coincidencias')
      )
    );
  }

  function App() {
    var selected = app.selected || {};
    var opts = app.options || {};
    var meta = app.meta || {};

    var _a = useState(app.title || ''), title = _a[0], setTitle = _a[1];
    var _b = useState(app.excerpt || ''), excerpt = _b[0], setExcerpt = _b[1];
    var _c = useState(app.content || ''), content = _c[0], setContent = _c[1];

    var _d = useState(String(selected.operation || '')), operation = _d[0], setOperation = _d[1];
    var _e = useState(String(selected.type || '')), type = _e[0], setType = _e[1];
    var _f = useState(String(selected.country || '')), country = _f[0], setCountry = _f[1];
    var _g = useState(String(selected.state || '')), state = _g[0], setState = _g[1];
    var _h = useState(String(selected.city || '')), city = _h[0], setCity = _h[1];
    var _i = useState(String(selected.neighborhood || '')), neighborhood = _i[0], setNeighborhood = _i[1];

    var _j = useState(opts.states || []), stateOptions = _j[0], setStateOptions = _j[1];
    var _k = useState(opts.cities || []), cityOptions = _k[0], setCityOptions = _k[1];
    var _l = useState(opts.neighborhoods || []), neighborhoodOptions = _l[0], setNeighborhoodOptions = _l[1];

    var _m = useState(String(meta.code || '')), code = _m[0], setCode = _m[1];
    var _n = useState(String(meta.age || '')), yearBuilt = _n[0], setYearBuilt = _n[1];
    var _o = useState(String(meta.address || '')), address = _o[0], setAddress = _o[1];
    var _p = useState(String(meta.latitude || '')), latitude = _p[0], setLatitude = _p[1];
    var _q = useState(String(meta.longitude || '')), longitude = _q[0], setLongitude = _q[1];

    var _r = useState(String(meta.price_sale || '')), priceSale = _r[0], setPriceSale = _r[1];
    var _s = useState(String(meta.currency_sale || (opts.currencies || ['USD'])[0])), currencySale = _s[0], setCurrencySale = _s[1];
    var _t = useState(String(meta.price_rent || '')), priceRent = _t[0], setPriceRent = _t[1];
    var _u = useState(String(meta.currency_rent || (opts.currencies || ['USD'])[0])), currencyRent = _u[0], setCurrencyRent = _u[1];
    var _v = useState(String(meta.price_admin || '')), priceAdmin = _v[0], setPriceAdmin = _v[1];
    var _w = useState(String(meta.currency_admin || (opts.currencies || ['USD'])[0])), currencyAdmin = _w[0], setCurrencyAdmin = _w[1];
    var _x = useState(!!Number(meta.admin_included || 0)), adminIncluded = _x[0], setAdminIncluded = _x[1];
    var _x2 = useState(String(meta.price_admin || '') !== '' && String(meta.price_admin || '0') !== '0'), hasAdminFee = _x2[0], setHasAdminFee = _x2[1];
    var _x3 = useState(String(meta.price_valid_until || '')), priceValidUntil = _x3[0], setPriceValidUntil = _x3[1];

    var _y = useState(String(meta.area || '')), area = _y[0], setArea = _y[1];
    var _z = useState(String(meta.area_lot || '')), areaLot = _z[0], setAreaLot = _z[1];
    var _aa = useState(String(meta.area_private || '')), areaPrivate = _aa[0], setAreaPrivate = _aa[1];
    var _ab = useState(String(meta.area_built || '')), areaBuilt = _ab[0], setAreaBuilt = _ab[1];
    var _ac = useState(String(meta.bedrooms || '')), bedrooms = _ac[0], setBedrooms = _ac[1];
    var _ad = useState(String(meta.bathrooms || '')), bathrooms = _ad[0], setBathrooms = _ad[1];
    var _ae = useState(String(meta.parking || '')), parking = _ae[0], setParking = _ae[1];
    var _af = useState(String(selected.condition || '')), condition = _af[0], setCondition = _af[1];
    var _af2 = useState((selected.features || []).map(function (id) { return String(id); })), selectedFeatures = _af2[0], setSelectedFeatures = _af2[1];
    var _af3 = useState((selected.nearby || []).map(function (id) { return String(id); })), selectedNearby = _af3[0], setSelectedNearby = _af3[1];
    var _af4 = useState((selected.tags || []).map(function (id) { return String(id); })), selectedTags = _af4[0], setSelectedTags = _af4[1];

    var defaultAgent = String(meta.agent_id || '');
    var _ag = useState(String(meta.gallery || '')), gallery = _ag[0], setGallery = _ag[1];
    var _ah = useState(defaultAgent), agentId = _ah[0], setAgentId = _ah[1];
    var _ah2 = useState(normalizeUrlListFromMeta(meta.videos).join('\n')), videosText = _ah2[0], setVideosText = _ah2[1];
    var _ah3 = useState(normalizeUrlListFromMeta(meta.photos_360).join('\n')), photos360Text = _ah3[0], setPhotos360Text = _ah3[1];
    var _ah4 = useState(normalizeUrlListFromMeta(meta.tour_360).join('\n')), tours360Text = _ah4[0], setTours360Text = _ah4[1];
    var _ah5 = useState(normalizeSingleUrlFromMeta(meta.brochure)), brochure = _ah5[0], setBrochure = _ah5[1];
    var _ak = useState(!!Number(meta.featured || 0)), featured = _ak[0], setFeatured = _ak[1];
    var mapNodeRef = useRef(null);
    var mapRef = useRef(null);
    var markerRef = useRef(null);
    var radiusRef = useRef(null);
    var lastAutoGeocodeKeyRef = useRef('');
    var contentEditorTextareaRef = useRef(null);
    var contentEditorInstanceRef = useRef(null);

    var initialExistingItems = useMemo(function () {
      return (opts.gallery_items || []).map(function (item) {
        var hasId = Number(item.id || 0) > 0;
        var safeUrl = String(item.url || '');
        return {
          token: hasId ? ('id:' + String(item.id)) : ('url:' + safeUrl),
          id: hasId ? String(item.id) : '',
          url: safeUrl
        };
      });
    }, []);
    var _al = useState(initialExistingItems), existingGalleryItems = _al[0], setExistingGalleryItems = _al[1];
    var _am = useState(initialExistingItems.map(function (i) { return i.token; })), galleryOrder = _am[0], setGalleryOrder = _am[1];
    var _an = useState(initialExistingItems.length ? initialExistingItems[0].token : ''), featuredGalleryToken = _an[0], setFeaturedGalleryToken = _an[1];
    var _ao = useState([]), selectedGalleryTokens = _ao[0], setSelectedGalleryTokens = _ao[1];
    var _ap = useState(''), draggingToken = _ap[0], setDraggingToken = _ap[1];
    var _aq = useState((app.validation && app.validation.fields) ? app.validation.fields : []), errorFields = _aq[0], setErrorFields = _aq[1];
    var _ar = useState('main'), activeTab = _ar[0], setActiveTab = _ar[1];
    var _as = useState('photos'), mediaTab = _as[0], setMediaTab = _as[1];
    var _at = useState(false), mediaCardOpen = _at[0], setMediaCardOpen = _at[1];
    var _au = useState(false), featuresCardOpen = _au[0], setFeaturesCardOpen = _au[1];
    var visitStats = app.visitStats || { totals: {}, daily: [], recent: [] };
    var syncInfo = app.sync || {};
    var isSyncedProperty = !!syncInfo.isSynced;
    var visitTotals = visitStats.totals || {};
    var dailyVisits = Array.isArray(visitStats.daily) ? visitStats.daily : [];
    var recentVisits = Array.isArray(visitStats.recent) ? visitStats.recent : [];
    var maxDailyVisit = dailyVisits.reduce(function (max, row) { return Math.max(max, Number(row.visits || 0)); }, 0) || 1;

    var currencyOptions = (opts.currencies || []).map(function (c) { return { id: c, name: c }; });
    var users = (opts.users || []).map(function (u) { return { id: u.id, name: u.label }; });
    var featureGroups = opts.feature_groups || [];
    var nearbyOptions = opts.nearby_options || [];
    var tagOptions = opts.tag_options || [];
    var selectedOperation = (opts.operations || []).find(function (o) { return String(o.id) === String(operation); });
    var operationSlug = String((selectedOperation && (selectedOperation.slug || selectedOperation.name)) || '').toLowerCase();
    var operationBaseId = Number((selectedOperation && selectedOperation.base_id) || 0);
    var isSwap = operationBaseId === 4 || operationSlug.indexOf('permuta') !== -1 || operationSlug.indexOf('swap') !== -1;
    var isRent = operationBaseId === 1 || operationBaseId === 3 || operationSlug.indexOf('arr') !== -1 || operationSlug.indexOf('alquil') !== -1 || operationSlug.indexOf('rent') !== -1;
    var isSale = operationBaseId === 2 || operationBaseId === 3 || operationSlug.indexOf('vent') !== -1 || operationSlug.indexOf('sale') !== -1;
    var showRentPrice = isRent || isSwap;
    var showSalePrice = isSale || isSwap;
    var canHaveAdminFee = showRentPrice || showSalePrice;

    // Capture initial media state so the closure below ([] deps, runs once) can reference them.
    // We only want to fill fields that PHP couldn't populate from primary meta — specifically those
    // still empty after the initial DB read. Fields that already have a value (from the DB via
    // app.meta) must NOT be overwritten, so we compare against the initial state captured here.
    var initialVideosText   = normalizeUrlListFromMeta(meta.videos).join('\n');
    var initialPhotos360Text = normalizeUrlListFromMeta(meta.photos_360).join('\n');
    var initialTours360Text = normalizeUrlListFromMeta(meta.tour_360).join('\n');
    var initialBrochure     = normalizeSingleUrlFromMeta(meta.brochure);

    useEffect(function () {
      var postId = Number(app.postId || 0);
      if (!postId) return;

      api('/homlity-real-estate/v1/property-media-sync', { post_id: postId }).then(function (res) {
        if (!res || res.ok !== true) return;

        var videos    = normalizeUrlListFromMeta(res.videos);
        var photos360 = normalizeUrlListFromMeta(res.photos_360);
        var tours360  = normalizeUrlListFromMeta(res.tour_360);
        var brochureUrl = normalizeSingleUrlFromMeta(res.brochure);

        // Only fill fields that were empty at mount time (primary DB meta was absent).
        // Never overwrite a value already loaded from the database — that would hide
        // edits the user made since the page loaded or restore old CRM data on top of
        // a value they intentionally cleared.
        if (videos.length    && !initialVideosText.trim())    setVideosText(videos.join('\n'));
        if (photos360.length && !initialPhotos360Text.trim()) setPhotos360Text(photos360.join('\n'));
        if (tours360.length  && !initialTours360Text.trim())  setTours360Text(tours360.join('\n'));
        if (brochureUrl      && !initialBrochure.trim())      setBrochure(brochureUrl);
      });
    }, []);

    useEffect(function () {
      syncCore('title', title);
      syncCore('excerpt', excerpt);
      syncCore('content', content);
    }, [title, excerpt, content]);

    useEffect(function () {
      if (!contentEditorTextareaRef.current || contentEditorInstanceRef.current) return;
      if (typeof window.ClassicEditor === 'undefined') return;

      window.ClassicEditor
        .create(contentEditorTextareaRef.current)
        .then(function (editor) {
          contentEditorInstanceRef.current = editor;
          editor.setData(content || '');
          editor.model.document.on('change:data', function () {
            var html = editor.getData();
            setContent(html);
          });
        })
        .catch(function () {
          // Fallback silently to plain textarea.
        });

      return function () {
        if (contentEditorInstanceRef.current && typeof contentEditorInstanceRef.current.destroy === 'function') {
          contentEditorInstanceRef.current.destroy();
          contentEditorInstanceRef.current = null;
        }
      };
    }, []);

    useEffect(function () {
      var form = document.getElementById('post');
      if (!form) return;
      function onSubmit(e) {
        var missingKeys = [];
        var missingLabels = [];
        if (!String(title || '').trim()) { missingKeys.push('title'); missingLabels.push('Título'); }
        if (!String(operation || '').trim()) { missingKeys.push('operation'); missingLabels.push('Gestión'); }
        if (!String(type || '').trim()) { missingKeys.push('type'); missingLabels.push('Tipo de inmueble'); }
        if (!String(country || '').trim()) { missingKeys.push('country'); missingLabels.push('País'); }
        if (!String(state || '').trim()) { missingKeys.push('state'); missingLabels.push('Departamento/Provincia'); }
        if (!String(city || '').trim()) { missingKeys.push('city'); missingLabels.push('Ciudad'); }
        if (!String(address || '').trim()) { missingKeys.push('address'); missingLabels.push('Dirección'); }
        if (!String(latitude || '').trim()) { missingKeys.push('latitude'); missingLabels.push('Latitud'); }
        if (!String(longitude || '').trim()) { missingKeys.push('longitude'); missingLabels.push('Longitud'); }
        if (isRent && !String(priceRent || '').trim()) { missingKeys.push('price_rent'); missingLabels.push('Precio arriendo'); }
        if (isSale && !String(priceSale || '').trim()) { missingKeys.push('price_sale'); missingLabels.push('Precio venta'); }

        if (missingKeys.length) {
          e.preventDefault();
          setErrorFields(missingKeys);
          alert('Completa los campos obligatorios:\n- ' + missingLabels.join('\n- '));
        }
      }
      form.addEventListener('submit', onSubmit);
      return function () { form.removeEventListener('submit', onSubmit); };
    }, [title, operation, type, country, state, city, address, latitude, longitude, isRent, isSale, priceRent, priceSale]);

    useEffect(function () {
      if (!app.taxonomies) return;
      if (!country) {
        setStateOptions([]); setState(''); setCityOptions([]); setCity(''); setNeighborhoodOptions([]); setNeighborhood('');
        return;
      }
      api('/homlity-real-estate/v1/location-terms', { taxonomy: app.taxonomies.state, parent: country }).then(function (rows) {
        setStateOptions(rows || []);
        if (!(rows || []).some(function (t) { return String(t.id) === String(state); })) {
          setState(''); setCity(''); setNeighborhood(''); setCityOptions([]); setNeighborhoodOptions([]);
        }
      });
    }, [country]);

    useEffect(function () {
      if (!app.taxonomies || !state) { setCityOptions([]); setCity(''); setNeighborhoodOptions([]); setNeighborhood(''); return; }
      api('/homlity-real-estate/v1/location-terms', { taxonomy: app.taxonomies.city, parent: state }).then(function (rows) {
        setCityOptions(rows || []);
        if (!(rows || []).some(function (t) { return String(t.id) === String(city); })) {
          setCity(''); setNeighborhood(''); setNeighborhoodOptions([]);
        }
      });
    }, [state]);

    useEffect(function () {
      if (!app.taxonomies || !city) { setNeighborhoodOptions([]); setNeighborhood(''); return; }
      api('/homlity-real-estate/v1/location-terms', { taxonomy: app.taxonomies.neighborhood, parent: city }).then(function (rows) {
        setNeighborhoodOptions(rows || []);
        if (!(rows || []).some(function (t) { return String(t.id) === String(neighborhood); })) {
          setNeighborhood('');
        }
      });
    }, [city]);

    useEffect(function () {
      if (isSyncedProperty) return;
      api('/homlity-real-estate/v1/property-next-code', { operation: operation || 0, type: type || 0, post_id: app.postId || 0 })
        .then(function (res) {
          if (res && res.code) setCode(String(res.code));
        });
    }, [operation, type, isSyncedProperty]);

    useEffect(function () {
      if (typeof window.L === 'undefined' || !mapNodeRef.current || mapRef.current) return;

      var parsedLat = parseFloat(latitude);
      var parsedLng = parseFloat(longitude);
      var startLat = Number.isFinite(parsedLat) ? parsedLat : 4.5709;
      var startLng = Number.isFinite(parsedLng) ? parsedLng : -74.2973;

      var map = window.L.map(mapNodeRef.current).setView([startLat, startLng], 13);
      window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      var marker = window.L.marker([startLat, startLng], { draggable: true }).addTo(map);
      var radius = window.L.circle([startLat, startLng], {
        radius: 500,
        color: '#2563eb',
        weight: 1,
        fillColor: '#60a5fa',
        fillOpacity: 0.12
      }).addTo(map);

      marker.on('dragend', function () {
        var pos = marker.getLatLng();
        setLatitude(String(Number(pos.lat).toFixed(6)));
        setLongitude(String(Number(pos.lng).toFixed(6)));
      });

      map.on('click', function (evt) {
        marker.setLatLng(evt.latlng);
        setLatitude(String(Number(evt.latlng.lat).toFixed(6)));
        setLongitude(String(Number(evt.latlng.lng).toFixed(6)));
      });

      mapRef.current = map;
      markerRef.current = marker;
      radiusRef.current = radius;
      setTimeout(function () { map.invalidateSize(); }, 0);

      return function () {
        map.remove();
        mapRef.current = null;
        markerRef.current = null;
        radiusRef.current = null;
      };
    }, []);

    useEffect(function () {
      if (!mapRef.current || !markerRef.current) return;
      var latNum = parseFloat(latitude);
      var lngNum = parseFloat(longitude);
      if (!Number.isFinite(latNum) || !Number.isFinite(lngNum)) return;
      markerRef.current.setLatLng([latNum, lngNum]);
      if (radiusRef.current) {
        radiusRef.current.setLatLng([latNum, lngNum]);
        mapRef.current.fitBounds(radiusRef.current.getBounds(), { padding: [20, 20] });
      } else {
        mapRef.current.setView([latNum, lngNum], mapRef.current.getZoom() || 16);
      }
    }, [latitude, longitude]);

    function selectedName(rows, id) {
      var found = (rows || []).find(function (r) { return String(r.id) === String(id); });
      return found ? found.name : '';
    }

    function openWpMediaPicker() {
      if (!window.wp || !window.wp.media) return;
      var frame = window.wp.media({
        title: 'Seleccionar fotos del inmueble',
        library: { type: 'image' },
        button: { text: 'Usar estas fotos' },
        multiple: true
      });
      frame.on('select', function () {
        var items = frame.state().get('selection').toJSON() || [];
        if (!items.length) return;
        var currentMap = {};
        existingGalleryItems.forEach(function (i) { currentMap[i.id] = true; });
        var nextExisting = existingGalleryItems.slice();
        var addedTokens = [];
        items.forEach(function (m) {
          var id = String(m.id || '');
          if (!id || currentMap[id]) return;
          currentMap[id] = true;
          var url = (m.sizes && m.sizes.medium && m.sizes.medium.url) || m.url || '';
          var token = 'id:' + id;
          nextExisting.push({ token: token, id: id, url: url });
          addedTokens.push(token);
        });
        if (!addedTokens.length) return;
        setExistingGalleryItems(nextExisting);
        setGalleryOrder(galleryOrder.concat(addedTokens));
        if (!featuredGalleryToken) {
          setFeaturedGalleryToken(addedTokens[0]);
        }
      });
      frame.open();
    }

    function removeGalleryItem(token) {
      var nextExisting = existingGalleryItems.filter(function (i) { return i.token !== token; });
      var nextOrder = galleryOrder.filter(function (t) { return t !== token; });
      var nextFeatured = featuredGalleryToken === token ? (nextOrder[0] || '') : featuredGalleryToken;
      setExistingGalleryItems(nextExisting);
      setGalleryOrder(nextOrder);
      setFeaturedGalleryToken(nextFeatured);
      setSelectedGalleryTokens(selectedGalleryTokens.filter(function (t) { return t !== token; }));
    }

    function moveGalleryToken(token, direction) {
      var idx = galleryOrder.indexOf(token);
      if (idx < 0) return;
      var target = direction === 'up' ? idx - 1 : idx + 1;
      if (target < 0 || target >= galleryOrder.length) return;
      var next = galleryOrder.slice();
      var temp = next[idx];
      next[idx] = next[target];
      next[target] = temp;
      setGalleryOrder(next);
    }

    function reorderGalleryTokens(sourceToken, targetToken) {
      if (!sourceToken || !targetToken || sourceToken === targetToken) return;
      var sourceIndex = galleryOrder.indexOf(sourceToken);
      var targetIndex = galleryOrder.indexOf(targetToken);
      if (sourceIndex < 0 || targetIndex < 0) return;
      var next = galleryOrder.slice();
      next.splice(sourceIndex, 1);
      next.splice(targetIndex, 0, sourceToken);
      setGalleryOrder(next);
    }

    function toggleTokenSelection(token) {
      if (selectedGalleryTokens.indexOf(token) >= 0) {
        setSelectedGalleryTokens(selectedGalleryTokens.filter(function (t) { return t !== token; }));
      } else {
        setSelectedGalleryTokens(selectedGalleryTokens.concat([token]));
      }
    }

    function toggleSelectAllGallery() {
      if (selectedGalleryTokens.length === galleryOrder.length) {
        setSelectedGalleryTokens([]);
        return;
      }
      setSelectedGalleryTokens(galleryOrder.slice());
    }

    function removeSelectedGallery() {
      if (!selectedGalleryTokens.length) return;
      var selectedMap = {};
      selectedGalleryTokens.forEach(function (t) { selectedMap[t] = true; });
      var nextExisting = existingGalleryItems.filter(function (i) { return !selectedMap[i.token]; });
      var nextOrder = galleryOrder.filter(function (t) { return !selectedMap[t]; });
      var nextFeatured = selectedMap[featuredGalleryToken] ? (nextOrder[0] || '') : featuredGalleryToken;
      setExistingGalleryItems(nextExisting);
      setGalleryOrder(nextOrder);
      setFeaturedGalleryToken(nextFeatured);
      setSelectedGalleryTokens([]);
    }

    function itemByToken(token) {
      var existing = existingGalleryItems.find(function (i) { return i.token === token; });
      if (existing) return existing;
      return null;
    }

    function geocodeByParts(parts, showAlertOnFail) {
      var cleanParts = (parts || []).filter(function (p) { return !!String(p || '').trim(); });
      if (!cleanParts.length) {
        if (showAlertOnFail) alert('Ingresa una dirección o selecciona ubicación para calcular coordenadas.');
        return Promise.resolve(false);
      }

      var payload = {
        address: cleanParts[0] || '',
        neighborhood: cleanParts[1] || '',
        city: cleanParts[2] || '',
        state: cleanParts[3] || '',
        country: cleanParts[4] || ''
      };

      function applyCoords(lat, lng) {
        setLatitude(String(Number(lat).toFixed(6)));
        setLongitude(String(Number(lng).toFixed(6)));
      }

      return api('/homlity-real-estate/v1/property-geocode', payload).then(function (res) {
        if (res && res.lat && res.lng) {
          applyCoords(res.lat, res.lng);
          return true;
        }

        var url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('q', cleanParts.join(', '));
        url.searchParams.set('format', 'json');
        url.searchParams.set('limit', '1');

        return fetch(url.toString(), { headers: { 'Accept-Language': 'es' } })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            if (rows && rows[0] && rows[0].lat && rows[0].lon) {
              applyCoords(rows[0].lat, rows[0].lon);
              return true;
            }
            if (showAlertOnFail) alert('No se pudo calcular latitud/longitud con la información actual.');
            return false;
          })
          .catch(function () {
            if (showAlertOnFail) alert('No se pudo calcular latitud/longitud en este momento.');
            return false;
          });
      }).catch(function () {
        if (showAlertOnFail) alert('No se pudo calcular latitud/longitud en este momento.');
        return false;
      });
    }

    function geocodeNow() {
      var nName = selectedName(neighborhoodOptions.length ? neighborhoodOptions : opts.neighborhoods, neighborhood);
      var ciName = selectedName(cityOptions.length ? cityOptions : opts.cities, city);
      var sName = selectedName(stateOptions.length ? stateOptions : opts.states, state);
      var cName = selectedName(opts.countries, country);
      geocodeByParts([address, nName, ciName, sName, cName], true);
    }

    useEffect(function () {
      var cName = selectedName(opts.countries, country);
      var sName = selectedName(stateOptions.length ? stateOptions : opts.states, state);
      var ciName = selectedName(cityOptions.length ? cityOptions : opts.cities, city);
      var nName = selectedName(neighborhoodOptions.length ? neighborhoodOptions : opts.neighborhoods, neighborhood);
      var key = [cName, sName, ciName, nName].filter(Boolean).join('|');
      if (!key || lastAutoGeocodeKeyRef.current === key) return;
      lastAutoGeocodeKeyRef.current = key;
      geocodeByParts(['', nName, ciName, sName, cName], false);
    }, [country, state, city, neighborhood, stateOptions, cityOptions, neighborhoodOptions]);

    useEffect(function () {
      var addr = String(address || '').trim();
      if (addr.length < 6) return;
      var cName = selectedName(opts.countries, country);
      var sName = selectedName(stateOptions.length ? stateOptions : opts.states, state);
      var ciName = selectedName(cityOptions.length ? cityOptions : opts.cities, city);
      var nName = selectedName(neighborhoodOptions.length ? neighborhoodOptions : opts.neighborhoods, neighborhood);
      var timeout = setTimeout(function () {
        geocodeByParts([addr, nName, ciName, sName, cName], false);
      }, 600);
      return function () { clearTimeout(timeout); };
    }, [address, country, state, city, neighborhood, stateOptions, cityOptions, neighborhoodOptions]);

    function hidden(name, value) { return h('input', { type: 'hidden', name: name, value: value }); }
    function hasError(key) { return errorFields.indexOf(String(key)) >= 0; }

    function toggleFeature(termId) {
      var id = String(termId);
      if (selectedFeatures.indexOf(id) >= 0) {
        setSelectedFeatures(selectedFeatures.filter(function (v) { return v !== id; }));
      } else {
        setSelectedFeatures(selectedFeatures.concat([id]));
      }
    }

    function setNearbyValues(values) { setSelectedNearby((values || []).map(function (v) { return String(v); })); }

    function submitToWordPress(mode) {
      var form = document.getElementById('post');
      if (!form) return;
      var statusInput = form.querySelector('#post_status');
      if (statusInput && mode === 'draft') {
        statusInput.value = 'draft';
      } else if (statusInput && mode === 'publish') {
        statusInput.value = 'publish';
      }

      var trigger = null;
      if (mode === 'draft') {
        trigger = form.querySelector('#save-post');
      } else {
        trigger = form.querySelector('#publish');
      }

      if (trigger && typeof trigger.click === 'function') {
        trigger.click();
      } else {
        form.submit();
      }
    }

    function openPropertyUrl(url) {
      if (!url) {
        alert('Primero guarda el inmueble para generar su URL.');
        return;
      }
      window.open(url, '_blank');
    }

    function deleteProperty() {
      if (!app.deleteLink) {
        alert('Primero guarda el inmueble para poder eliminarlo.');
        return;
      }
      if (!window.confirm('¿Seguro que deseas eliminar este inmueble?')) return;
      window.location.href = app.deleteLink;
    }

    return h('div', { className: 'hpe-wrap' },
      h('div', { className: 'hpe-banner-grid' },
        h('a', {
          className: 'hpe-homlity-banner',
          href: app.registrationUrl || 'https://homi.homlity.com/registro-inmobiliaria-gratis',
          target: '_blank',
          rel: 'noopener noreferrer',
          'aria-label': 'Registrarse gratis en Homlity'
        },
          h('img', {
            src: app.bannerUrl || '',
            alt: 'Impulsa tu inmobiliaria con Homlity'
          })
        ),
        h('a', {
          className: 'hpe-homlity-banner',
          href: app.directoryRegistrationUrl || 'https://homi.homlity.com/register',
          target: '_blank',
          rel: 'noopener noreferrer',
          'aria-label': 'Registrarse gratis en el Directorio Inmobiliario de Homlity'
        },
          h('img', {
            src: app.directoryBannerUrl || '',
            alt: 'Únete al Directorio Inmobiliario de Homlity'
          })
        )
      ),
      hidden('homlity_react_editor_nonce', app.reactNonce || ''),
      hidden('post_title', title),
      hidden('excerpt', excerpt),
      hidden('content', content),
      h('div', { className: 'hpe-editor-tabs' },
        h('button', { type: 'button', className: 'button' + (activeTab === 'main' ? ' button-primary' : ''), onClick: function () { setActiveTab('main'); } }, 'Formulario'),
        h('button', { type: 'button', className: 'button' + (activeTab === 'visits' ? ' button-primary' : ''), onClick: function () { setActiveTab('visits'); } }, 'Informe de visitas')
      ),
      h('div', { className: 'hpe-grid hpe-grid--tab-' + activeTab },
        h('section', { className: 'hpe-card hpe-card--overview hpe-span-3' },
          h('div', { className: 'hpe-overview-grid' },
            h('div', { className: 'hpe-overview-section hpe-overview-section--basic' },
              h('h2', null, 'Información básica'),
              h(Field, { label: 'Título', required: true, error: hasError('title') ? 'Este campo es obligatorio.' : '' }, h('input', { type: 'text', value: title, required: true, onChange: function (e) { setTitle(e.target.value); } })),
              h(Field, { label: 'Descripción corta' }, h('textarea', { rows: 3, value: excerpt, onChange: function (e) { setExcerpt(e.target.value); } })),
              h('p', { className: 'description' }, 'Describe el inmueble con enfoque comercial: ubicación, distribución, beneficios y estado.'),
              h(Field, null, h('textarea', { ref: contentEditorTextareaRef, rows: 8, value: content, onChange: function (e) { setContent(e.target.value); } }))
            ),
            h('div', { className: 'hpe-overview-section hpe-overview-section--commercial' },
              h('h2', null, 'Configuración comercial'),
              h('div', { className: 'hpe-commercial-fields' },
                h(Field, { label: 'Tipo de inmueble', required: true, error: hasError('type') ? 'Este campo es obligatorio.' : '' }, h(Select, { name: 'property_type', value: type, required: true, onChange: setType, options: opts.types || [] })),
                h(Field, { label: 'Gestión', required: true, error: hasError('operation') ? 'Este campo es obligatorio.' : '' }, h(Select, { name: 'property_operation', value: operation, required: true, onChange: setOperation, options: opts.operations || [] })),
                h(Field, { label: 'Estado inmueble' }, h(Select, { name: 'property_condition', value: condition, onChange: setCondition, options: opts.condition_options || [], placeholder: 'Sin especificar' })),
                h(Field, { label: 'Código (auto)' }, h(Input, { name: 'property_code', value: code, readOnly: true })),
                h(Field, { label: 'Año de construido' }, h(Input, { name: 'property_age', value: yearBuilt, type: 'number', onChange: setYearBuilt })),
                h(Field, { label: 'Usuario asesor' }, h(Select, {
                  name: 'property_agent_id',
                  value: agentId,
                  onChange: setAgentId,
                  options: users,
                  placeholder: 'Seleccionar asesor'
                }))
              ),
              h('p', { className: 'description' }, 'Relaciona un usuario Asesor o Administrador. Sus datos de contacto se toman automáticamente del perfil.'),
              h('label', { className: 'hpe-check' }, h('input', { type: 'checkbox', checked: featured, onChange: function (e) { setFeatured(e.target.checked); } }), 'Inmueble destacado'),
              hidden('property_featured', featured ? '1' : '0')
            ),
            h('div', { className: 'hpe-overview-section hpe-overview-section--prices' },
              h('h2', null, 'Precios'),
              (!showRentPrice && !showSalePrice) && h('p', null, 'Selecciona la gestión para configurar precios.'),
              showRentPrice && h('div', { className: 'hpe-prices' },
                h(Field, { label: 'Precio arriendo', required: isRent, error: hasError('price_rent') ? 'Este campo es obligatorio.' : '' }, h(Input, { type: 'number', name: 'property_price_rent', required: isRent, value: priceRent, onChange: setPriceRent })),
                h(Field, { label: 'Moneda arriendo' }, h(Select, { name: 'property_currency_rent', value: currencyRent, onChange: setCurrencyRent, options: currencyOptions }))
              ),
              showSalePrice && h('div', { className: 'hpe-prices' },
                h(Field, { label: 'Precio venta', required: isSale, error: hasError('price_sale') ? 'Este campo es obligatorio.' : '' }, h(Input, { type: 'number', name: 'property_price_sale', required: isSale, value: priceSale, onChange: setPriceSale })),
                h(Field, { label: 'Moneda venta' }, h(Select, { name: 'property_currency_sale', value: currencySale, onChange: setCurrencySale, options: currencyOptions }))
              ),
              canHaveAdminFee && h('label', { className: 'hpe-check' }, h('input', { type: 'checkbox', checked: hasAdminFee, onChange: function (e) { setHasAdminFee(e.target.checked); } }), 'Tiene administración'),
              (canHaveAdminFee && hasAdminFee) && h('div', { className: 'hpe-prices' },
                h(Field, { label: 'Precio administración' }, h(Input, { type: 'number', name: 'property_price_admin', value: priceAdmin, onChange: setPriceAdmin })),
                h(Field, { label: 'Moneda administración' }, h(Select, { name: 'property_currency_admin', value: currencyAdmin, onChange: setCurrencyAdmin, options: currencyOptions }))
              ),
              (showRentPrice && hasAdminFee) && h('label', { className: 'hpe-check' }, h('input', { type: 'checkbox', checked: adminIncluded, onChange: function (e) { setAdminIncluded(e.target.checked); } }), 'Administración incluida en arriendo'),
              h(Field, { label: 'Precio válido hasta' }, h(Input, { type: 'date', name: 'property_price_valid_until', value: priceValidUntil, onChange: setPriceValidUntil })),
              h('p', { className: 'description' }, 'Fecha opcional publicada como priceValidUntil en el Schema.'),
              canHaveAdminFee && !hasAdminFee && hidden('property_price_admin', ''),
              hidden('property_admin_included', (showRentPrice && hasAdminFee && adminIncluded) ? '1' : '0')
            )
          )
        ),
        h('div', { className: 'hpe-card-stack' },
          h('section', { className: 'hpe-card' },
            h('h2', null, 'Etiquetas'),
            h('p', { className: 'description' }, 'Selecciona una o varias etiquetas para clasificar el inmueble.'),
            h(MultiSelectChips, {
              options: tagOptions,
              value: selectedTags,
              onChange: setSelectedTags,
              placeholder: 'Buscar etiquetas...'
            }),
            hidden('property_tags', selectedTags.join(',')),
            h('p', { className: 'description' },
              h('a', {
                href: app.manageTagsUrl || '#',
                target: '_blank',
                rel: 'noopener noreferrer'
              }, 'Administrar etiquetas')
            )
          ),
          h('section', { className: 'hpe-card' },
            h('h2', null, 'Lugares cercanos'),
            h(MultiSelectChips, {
              options: nearbyOptions,
              value: selectedNearby,
              onChange: setNearbyValues,
              placeholder: 'Buscar lugares cercanos...'
            }),
            hidden('property_nearby', selectedNearby.join(','))
          )
        ),
        h('section', { className: 'hpe-card hpe-span-3 hpe-card--location-full' },
          h('h2', null, 'Ubicación'),
          h(Field, { label: 'Dirección', required: true, error: hasError('address') ? 'Este campo es obligatorio.' : '' }, h(Input, { name: 'property_address', required: true, value: address, onChange: setAddress })),
          h(Field, { label: 'País', required: true, error: hasError('country') ? 'Este campo es obligatorio.' : '' }, h(Select, { name: 'property_country', value: country, required: true, onChange: setCountry, options: opts.countries || [] })),
          h(Field, { label: 'Departamento/Provincia', required: true, error: hasError('state') ? 'Este campo es obligatorio.' : '' }, h(Select, { name: 'property_state', value: state, required: true, onChange: setState, options: stateOptions })),
          h(Field, { label: 'Ciudad', required: true, error: hasError('city') ? 'Este campo es obligatorio.' : '' }, h(Select, { name: 'property_city', value: city, required: true, onChange: setCity, options: cityOptions })),
          h(Field, { label: 'Barrio' }, h(Select, { name: 'property_neighborhood', value: neighborhood, onChange: setNeighborhood, options: neighborhoodOptions })),
          h('div', { className: 'hpe-prices' },
            h(Field, { label: 'Latitud', required: true, error: hasError('latitude') ? 'Este campo es obligatorio.' : '' }, h(Input, { name: 'property_latitude', required: true, value: latitude, onChange: setLatitude })),
            h(Field, { label: 'Longitud', required: true, error: hasError('longitude') ? 'Este campo es obligatorio.' : '' }, h(Input, { name: 'property_longitude', required: true, value: longitude, onChange: setLongitude }))
          ),
          h('button', { type: 'button', className: 'button button-primary', onClick: geocodeNow }, 'Calcular lat/lng por georreferenciación y dirección'),
          h('div', { className: 'hpe-map-wrap' },
            h('div', { ref: mapNodeRef, className: 'hpe-map-canvas' }),
            h('p', { className: 'description' }, 'Haz clic en el mapa o arrastra el pin para definir la ubicación exacta del inmueble.')
          )
        ),
        h('section', { className: 'hpe-card hpe-span-3 hpe-card--media-full hpe-card-collapsible' + (mediaCardOpen ? ' is-open' : ' is-collapsed') },
          h('div', { className: 'hpe-card-collapse-header' },
            h('h2', null, 'Medios y publicación'),
            h('button', {
              type: 'button',
              className: 'hpe-card-collapse-toggle',
              onClick: function () { setMediaCardOpen(!mediaCardOpen); },
              'aria-expanded': mediaCardOpen ? 'true' : 'false',
              'aria-label': mediaCardOpen ? 'Colapsar Medios y publicación' : 'Expandir Medios y publicación'
            }, h('span', { 'aria-hidden': 'true' }, mediaCardOpen ? '−' : '+'))
          ),
          h('div', { className: 'hpe-editor-tabs' },
            h('button', { type: 'button', className: 'button' + (mediaTab === 'photos' ? ' button-primary' : ''), onClick: function () { setMediaTab('photos'); } }, 'Fotos'),
            h('button', { type: 'button', className: 'button' + (mediaTab === 'videos' ? ' button-primary' : ''), onClick: function () { setMediaTab('videos'); } }, 'Videos'),
            h('button', { type: 'button', className: 'button' + (mediaTab === 'vr360' ? ' button-primary' : ''), onClick: function () { setMediaTab('vr360'); } }, '360'),
            h('button', { type: 'button', className: 'button' + (mediaTab === 'brochure' ? ' button-primary' : ''), onClick: function () { setMediaTab('brochure'); } }, 'Brochure')
          ),
          mediaTab === 'photos' && h('div', null,
            h(Field, { label: 'Fotos del inmueble' },
              h('button', { type: 'button', className: 'button', onClick: openWpMediaPicker }, 'Seleccionar desde Biblioteca de Medios')
            ),
            h('p', { className: 'description' }, 'Puedes agregar varias fotos desde WordPress, ordenarlas con drag & drop, marcar destacada y quitar una o varias.'),
            h('div', { className: 'hpe-gallery-toolbar' },
              h('label', { className: 'hpe-gallery-select-all' },
                h('input', {
                  type: 'checkbox',
                  checked: galleryOrder.length > 0 && selectedGalleryTokens.length === galleryOrder.length,
                  onChange: toggleSelectAllGallery
                }),
                h('span', null, 'Seleccionar todas')
              ),
              h('span', { className: 'hpe-gallery-selected-count' }, String(selectedGalleryTokens.length) + ' seleccionada(s)'),
              h('button', { type: 'button', className: 'hpe-trash-btn', onClick: removeSelectedGallery, disabled: !selectedGalleryTokens.length, 'aria-label': 'Quitar fotos seleccionadas', title: 'Quitar fotos seleccionadas' },
                h('span', { className: 'hpe-trash-icon', 'aria-hidden': 'true' }, '🗑')
              )
            ),
            h('div', { className: 'hpe-gallery-grid' },
              galleryOrder.map(function (token, index) {
                var item = itemByToken(token);
                if (!item) return null;
                var src = item.url || '';
                var isFeatured = featuredGalleryToken === token;
                var isSelected = selectedGalleryTokens.indexOf(token) >= 0;
                return h('div', {
                  key: token,
                  className: 'hpe-gallery-item' + (isFeatured ? ' is-featured' : '') + (isSelected ? ' is-selected' : ''),
                  draggable: true,
                  onDragStart: function () { setDraggingToken(token); },
                  onDragOver: function (e) { e.preventDefault(); },
                  onDrop: function (e) { e.preventDefault(); reorderGalleryTokens(draggingToken, token); setDraggingToken(''); }
                },
                  src ? h('img', { src: src, alt: item.name || ('Foto ' + (index + 1)) }) : null,
                  h('label', { className: 'hpe-gallery-check' },
                    h('input', {
                      type: 'checkbox',
                      checked: isSelected,
                      onChange: function () { toggleTokenSelection(token); }
                    })
                  ),
                  h('div', { className: 'hpe-gallery-actions' },
                    h('button', {
                      type: 'button',
                      className: 'hpe-featured-btn' + (isFeatured ? ' is-active' : ''),
                      onClick: function () { setFeaturedGalleryToken(token); },
                      'aria-label': isFeatured ? 'Imagen destacada' : 'Marcar como imagen destacada',
                      title: isFeatured ? 'Imagen destacada' : 'Marcar como imagen destacada'
                    }, h('span', { className: 'hpe-featured-icon', 'aria-hidden': 'true' }, isFeatured ? '★' : '☆')),
                    h('button', { type: 'button', className: 'hpe-trash-btn is-inline', onClick: function () { removeGalleryItem(token); }, 'aria-label': 'Quitar foto', title: 'Quitar foto' },
                      h('span', { className: 'hpe-trash-icon', 'aria-hidden': 'true' }, '🗑')
                    )
                  )
                );
              })
            )
          ),
          mediaTab === 'videos' && h('div', null,
            h(Field, { label: 'Video del inmueble (URL de YouTube, Vimeo u otro)' },
              h(Input, { value: videosText, onChange: setVideosText, placeholder: 'https://www.youtube.com/watch?v=...' })
            ),
            h('p', { className: 'description' }, 'Ingresa una URL. Se admiten YouTube, Vimeo y videos directos (.mp4). Si el inmueble está sincronizado con un CRM, el valor se completa automáticamente.')
          ),
          mediaTab === 'vr360' && h('div', null,
            h(Field, { label: 'Foto(s) 360° (una URL por línea)' },
              h('textarea', { rows: 4, value: photos360Text, onChange: function (e) { setPhotos360Text(e.target.value); }, placeholder: 'https://...' })
            ),
            h(Field, { label: 'Recorrido(s) 360° (una URL por línea)' },
              h('textarea', { rows: 4, value: tours360Text, onChange: function (e) { setTours360Text(e.target.value); }, placeholder: 'https://...' })
            ),
            h('p', { className: 'description' }, 'Acepta URLs de Matterport, Kuula, CloudPano u otras plataformas 360°. Si el inmueble viene de un CRM, estos campos se completan automáticamente.')
          ),
          mediaTab === 'brochure' && h('div', null,
            h(Field, { label: 'Brochure / ficha técnica (URL del PDF o documento)' },
              h(Input, { value: brochure, onChange: setBrochure, placeholder: 'https://...' })
            ),
            h('p', { className: 'description' }, 'URL directa al brochure del inmueble. Se muestra como enlace de descarga en la ficha del inmueble. Se completa automáticamente si el inmueble está sincronizado.')
          ),
          hidden('property_gallery', existingGalleryItems.map(function (i) { return i.id; }).filter(function (id) { return !!id; }).join(',')),
          hidden('property_gallery_existing', existingGalleryItems.map(function (i) { return i.id; }).filter(function (id) { return !!id; }).join(',')),
          hidden('property_gallery_order', galleryOrder.join(',')),
          hidden('property_gallery_featured', featuredGalleryToken),
          hidden('property_videos', JSON.stringify((function () {
            var list = normalizeUrlListInput(videosText);
            return list.length ? [list[0]] : [];
          })())),
          hidden('property_photos_360', JSON.stringify(normalizeUrlListInput(photos360Text))),
          hidden('property_tour_360', JSON.stringify(normalizeUrlListInput(tours360Text))),
          hidden('property_brochure', brochure)
        ),
        h('section', { className: 'hpe-card hpe-span-3 hpe-card--features-full hpe-card-collapsible' + (featuresCardOpen ? ' is-open' : ' is-collapsed') },
          h('div', { className: 'hpe-card-collapse-header' },
            h('h2', null, 'Características'),
            h('button', {
              type: 'button',
              className: 'hpe-card-collapse-toggle',
              onClick: function () { setFeaturesCardOpen(!featuresCardOpen); },
              'aria-expanded': featuresCardOpen ? 'true' : 'false',
              'aria-label': featuresCardOpen ? 'Colapsar Características' : 'Expandir Características'
            }, h('span', { 'aria-hidden': 'true' }, featuresCardOpen ? '−' : '+'))
          ),
          h('div', { className: 'hpe-prices' },
            h(Field, { label: 'Habitaciones' }, h(Input, { type: 'number', name: 'property_bedrooms', value: bedrooms, onChange: setBedrooms })),
            h(Field, { label: 'Baños' }, h(Input, { type: 'number', name: 'property_bathrooms', value: bathrooms, onChange: setBathrooms })),
            h(Field, { label: 'Parqueaderos' }, h(Input, { type: 'number', name: 'property_parking', value: parking, onChange: setParking })),
            h(Field, { label: 'Área total' }, h(Input, { name: 'property_area', value: area, onChange: setArea })),
            h(Field, { label: 'Área lote' }, h(Input, { name: 'property_area_lot', value: areaLot, onChange: setAreaLot })),
            h(Field, { label: 'Área privada' }, h(Input, { name: 'property_area_private', value: areaPrivate, onChange: setAreaPrivate })),
            h(Field, { label: 'Área construida' }, h(Input, { name: 'property_area_built', value: areaBuilt, onChange: setAreaBuilt }))
          ),
          h('div', { className: 'hpe-feature-items hpe-feature-items--all' },
            featureGroups.reduce(function (items, group) {
              return items.concat((group.items || []).map(function (item) {
                var id = String(item.id);
                var checked = selectedFeatures.indexOf(id) >= 0;
                return h('label', { key: id, className: 'hpe-feature-check' + (checked ? ' is-selected' : '') },
                  h('input', { type: 'checkbox', checked: checked, onChange: function () { toggleFeature(id); } }),
                  h('span', null, item.name)
                );
              }));
            }, [])
          ),
          hidden('property_features', selectedFeatures.join(','))
        ),
        h('section', { className: 'hpe-card hpe-span-3 hpe-card--visits' },
          h('h2', null, 'Informe de visitas del inmueble'),
          h('p', { className: 'description' }, 'Solo se cuentan visitas de usuarios no logeados. El mismo visitante no suma nuevamente en el mismo inmueble durante 24 horas.'),
          h('div', { className: 'hpe-visit-metrics' },
            h('div', { className: 'hpe-visit-metric' }, h('strong', null, String(visitTotals.all || 0)), h('span', null, 'Total histórico')),
            h('div', { className: 'hpe-visit-metric' }, h('strong', null, String(visitTotals.today || 0)), h('span', null, 'Hoy')),
            h('div', { className: 'hpe-visit-metric' }, h('strong', null, String(visitTotals.last7 || 0)), h('span', null, 'Últimos 7 días')),
            h('div', { className: 'hpe-visit-metric' }, h('strong', null, String(visitTotals.last30 || 0)), h('span', null, 'Últimos 30 días')),
            h('div', { className: 'hpe-visit-metric' }, h('strong', null, String(visitTotals.unique_visitors || 0)), h('span', null, 'Visitantes únicos'))
          ),
          h('h3', null, 'Gráfica diaria (30 días)'),
          dailyVisits.length ? h('div', { className: 'hpe-visit-chart' },
            dailyVisits.map(function (row, i) {
              var count = Number(row.visits || 0);
              var pct = Math.max(4, Math.round((count / maxDailyVisit) * 100));
              return h('div', { key: 'd-' + i, className: 'hpe-visit-chart-row' },
                h('span', { className: 'hpe-visit-chart-date' }, String(row.date || '')),
                h('div', { className: 'hpe-visit-chart-bar-wrap' },
                  h('span', { className: 'hpe-visit-chart-bar', style: { width: pct + '%' } })
                ),
                h('span', { className: 'hpe-visit-chart-count' }, String(count))
              );
            })
          ) : h('p', { className: 'description' }, 'Aún no hay visitas registradas para graficar.'),
          h('h3', null, 'Historial reciente'),
          recentVisits.length ? h('table', { className: 'widefat striped' },
            h('thead', null, h('tr', null,
              h('th', null, 'Fecha y hora'),
              h('th', null, 'Visitante')
            )),
            h('tbody', null,
              recentVisits.map(function (row, i) {
                return h('tr', { key: 'r-' + i },
                  h('td', null, String(row.visited_at || '')),
                  h('td', null, String(row.visitor || ''))
                );
              })
            )
          ) : h('p', { className: 'description' }, 'Sin historial reciente de visitas.')
        )
      ),
      h('div', { className: 'hpe-actions hpe-actions-bottom' },
        h('button', { type: 'button', className: 'button', onClick: function () { submitToWordPress('draft'); } }, 'Guardar borrador'),
        h('button', { type: 'button', className: 'button button-primary', onClick: function () { submitToWordPress('publish'); } }, (app.postStatus === 'publish' ? 'Actualizar inmueble' : 'Publicar inmueble')),
        h('button', { type: 'button', className: 'button', onClick: function () { openPropertyUrl(app.permalink || app.previewLink || ''); } }, 'Ver URL'),
        h('button', { type: 'button', className: 'button', onClick: function () { openPropertyUrl(app.previewLink || app.permalink || ''); } }, 'Ver en página'),
        h('button', { type: 'button', className: 'button hpe-btn-danger', onClick: deleteProperty }, 'Eliminar inmueble')
      )
    );
  }

  function hideOldUi() {
    document.body.classList.add('homlity-react-editor-active');
    var legacyScope = document.getElementById('post');
    if (!legacyScope) return;
    var fields = legacyScope.querySelectorAll('input[name^="property_"], select[name^="property_"], textarea[name^="property_"], input[name^="tax_input["], textarea[name^="tax_input["]');
    fields.forEach(function (el) {
      if (el && el.name && el.name.indexOf('_nonce_field') === -1) {
        el.disabled = true;
      }
    });
  }

  function boot() {
    var el = root();
    if (!el) return;
    hideOldUi();
    // React 18 (WP 6.2+) exposes createRoot on wp.element; fall back to the
    // legacy render() for older WordPress installations.
    if (wpEl.createRoot) {
      wpEl.createRoot(el).render(h(App));
    } else {
      wpEl.render(h(App), el);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
