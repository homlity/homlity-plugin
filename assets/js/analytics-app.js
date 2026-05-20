(function () {
  const cfg = window.homlityPluginAnalyticsApp;
  if (!cfg || !window.wp || !window.wp.element || !window.wp.apiFetch) return;

  const { createElement: h, useEffect, useMemo, useState } = window.wp.element;
  const apiFetch = window.wp.apiFetch;
  if (cfg.nonce && typeof apiFetch.createNonceMiddleware === 'function') {
    apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
  }

  const root = document.getElementById('homlity-real-estate-analytics-app');
  if (!root) return;

  function ChartBars(props) {
    const items = Array.isArray(props.items) ? props.items : [];
    const max = items.reduce((m, i) => Math.max(m, Number(i.visits || 0)), 0) || 1;
    if (!items.length) return h('p', { className: 'hpa-empty' }, 'Sin datos para este rango.');

    return h('div', { className: 'hpa-bars' }, items.map(function (item, idx) {
      const value = Number(item.visits || 0);
      const pct = Math.max(4, Math.round((value / max) * 100));
      return h('div', { key: idx, className: 'hpa-bar-row' }, [
        h('div', { key: 'l', className: 'hpa-bar-label' }, String(item.label || item.title || item.date || '-')),
        h('div', { key: 'b', className: 'hpa-bar-wrap' }, h('span', { className: 'hpa-bar', style: { width: pct + '%' } })),
        h('div', { key: 'v', className: 'hpa-bar-value' }, String(value))
      ]);
    }));
  }

  function LineChart(props) {
    const items = Array.isArray(props.items) ? props.items : [];
    const color = String(props.color || '#ff6752');
    const height = 220;
    const width = 1000;
    const pad = 28;
    if (!items.length) return h('p', { className: 'hpa-empty' }, 'Sin datos para este rango.');

    const values = items.map(function (i) { return Number(i.visits || 0); });
    const max = values.reduce(function (m, v) { return Math.max(m, v); }, 0) || 1;
    const usableW = width - pad * 2;
    const usableH = height - pad * 2;

    const points = items.map(function (item, idx) {
      const x = pad + (items.length <= 1 ? usableW / 2 : (idx / (items.length - 1)) * usableW);
      const y = pad + usableH - ((Number(item.visits || 0) / max) * usableH);
      return { x: x, y: y, label: String(item.label || item.date || '-'), value: Number(item.visits || 0) };
    });

    const linePoints = points.map(function (p) { return p.x + ',' + p.y; }).join(' ');
    const areaPoints = linePoints + ' ' + (pad + usableW) + ',' + (pad + usableH) + ' ' + pad + ',' + (pad + usableH);
    const last = points[points.length - 1];
    const lastLabel = last ? (last.label + ': ' + last.value) : '';

    return h('div', { className: 'hpa-line-chart' }, [
      h('svg', { key: 'svg', viewBox: '0 0 ' + width + ' ' + height, preserveAspectRatio: 'none' }, [
        h('line', { key: 'x-axis', x1: pad, y1: pad + usableH, x2: pad + usableW, y2: pad + usableH, className: 'hpa-line-axis' }),
        h('line', { key: 'y-axis', x1: pad, y1: pad, x2: pad, y2: pad + usableH, className: 'hpa-line-axis' }),
        h('polyline', { key: 'area', points: areaPoints, className: 'hpa-line-area', style: { fill: color } }),
        h('polyline', { key: 'line', points: linePoints, className: 'hpa-line-stroke', style: { stroke: color } }),
        points.map(function (p, idx) {
          return h('circle', { key: 'c' + idx, cx: p.x, cy: p.y, r: 3, className: 'hpa-line-dot', style: { fill: color } });
        })
      ]),
      h('div', { key: 'meta', className: 'hpa-line-meta' }, [
        h('span', { key: 'max' }, 'Máx: ' + max),
        h('span', { key: 'last' }, lastLabel)
      ])
    ]);
  }

  function App() {
    const [range, setRange] = useState(Number(cfg.defaultRange || 30));
    const [tab, setTab] = useState('visits');
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(function () {
      setLoading(true);
      setError('');
      apiFetch({ path: cfg.analyticsPath + '?range=' + encodeURIComponent(String(range)) })
        .then(function (res) { setData(res || null); })
        .catch(function (e) { setError((e && e.message) ? e.message : 'Error al cargar la analítica.'); })
        .finally(function () { setLoading(false); });
    }, [range]);

    const ranges = useMemo(function () {
      return Array.isArray(cfg.ranges) ? cfg.ranges : [1, 15, 30, 60, 90];
    }, []);

    const contact = data && data.contact_clicks ? data.contact_clicks : { totals: {}, daily: [], top_properties: [] };
    const sheet = data && data.technical_sheet_downloads ? data.technical_sheet_downloads : { totals: {}, daily: [] };
    const advisorItems = (contact.advisors || []).map(function (a) {
      return { label: a.advisor_name, visits: a.total_clicks || 0 };
    });

    return h('div', { className: 'hpa-app' }, [
      h('header', { key: 'head', className: 'hpa-header' }, [
        h('h1', { key: 't' }, 'Analítica de inmuebles'),
        h('div', { key: 'r', className: 'hpa-ranges' }, ranges.map(function (r) {
          return h('button', {
            key: r,
            type: 'button',
            className: 'hpa-range-btn' + (Number(r) === Number(range) ? ' is-active' : ''),
            onClick: function () { setRange(Number(r)); }
          }, (r === 1 ? '1 día' : (r + ' días')));
        }))
      ]),

      h('div', { key: 'tabs', className: 'hpa-tabs' }, [
        h('button', {
          key: 'v', type: 'button',
          className: 'hpa-tab-btn' + (tab === 'visits' ? ' is-active' : ''),
          onClick: function () { setTab('visits'); }
        }, 'Visitas'),
        h('button', {
          key: 'c', type: 'button',
          className: 'hpa-tab-btn' + (tab === 'contacts' ? ' is-active' : ''),
          onClick: function () { setTab('contacts'); }
        }, 'Clics de contacto')
      ]),

      loading ? h('p', { key: 'load', className: 'hpa-loading' }, 'Cargando...') : null,
      error ? h('p', { key: 'err', className: 'hpa-error' }, error) : null,

      data && !loading && tab === 'visits' ? h('div', { key: 'visits', className: 'hpa-grid' }, [
        h('section', { key: 'kpi1', className: 'hpa-card hpa-card--kpi' }, [
          h('span', null, 'Visitas totales'),
          h('strong', null, String((data.totals && data.totals.visits) || 0))
        ]),
        h('section', { key: 'kpi2', className: 'hpa-card hpa-card--kpi' }, [
          h('span', null, 'Visitantes únicos'),
          h('strong', null, String((data.totals && data.totals.unique_visitors) || 0))
        ]),
        h('section', { key: 'kpi3', className: 'hpa-card hpa-card--kpi' }, [
          h('span', null, 'Inmueble más visto'),
          h('strong', null, data.most_viewed_property ? String(data.most_viewed_property.title || '-') : 'Sin dato')
        ]),
        h('section', { key: 'kpi4', className: 'hpa-card hpa-card--kpi' }, [
          h('span', null, 'Gestión más vista'),
          h('strong', null, data.most_viewed_management ? String(data.most_viewed_management.label || '-') : 'Sin dato')
        ]),
        h('section', { key: 'kpi5', className: 'hpa-card hpa-card--kpi' }, [
          h('span', null, 'Barrio más visto'),
          h('strong', null, data.most_viewed_neighborhood ? String(data.most_viewed_neighborhood.label || '-') : 'Sin dato')
        ]),

        h('section', { key: 'd1', className: 'hpa-card hpa-card--span2' }, [
          h('h2', null, 'Visitas por día (todos los inmuebles)'),
          h(LineChart, { items: (data.daily || []).map(function (x) { return { label: x.date, visits: x.visits }; }), color: '#ff6752' })
        ]),
        h('section', { key: 'd2', className: 'hpa-card' }, [h('h2', null, 'Top inmuebles'), h(ChartBars, { items: data.top_properties || [] })]),
        h('section', { key: 'd3', className: 'hpa-card' }, [h('h2', null, 'Visitas por tipo de inmueble'), h(ChartBars, { items: data.by_type || [] })]),
        h('section', { key: 'd4', className: 'hpa-card' }, [h('h2', null, 'Visitas por gestión'), h(ChartBars, { items: data.by_management || [] })]),
        h('section', { key: 'd5', className: 'hpa-card' }, [h('h2', null, 'Visitas por barrio'), h(ChartBars, { items: data.by_neighborhood || [] })])
      ]) : null,

      data && !loading && tab === 'contacts' ? h('div', { key: 'contacts', className: 'hpa-grid' }, [
        h('section', { key: 'c1', className: 'hpa-card hpa-card--kpi' }, [h('span', null, 'Clics contacto (total)'), h('strong', null, String((contact.totals && contact.totals.all) || 0))]),
        h('section', { key: 'c2', className: 'hpa-card hpa-card--kpi' }, [h('span', null, 'WhatsApp'), h('strong', null, String((contact.totals && contact.totals.whatsapp) || 0))]),
        h('section', { key: 'c3', className: 'hpa-card hpa-card--kpi' }, [h('span', null, 'Teléfono'), h('strong', null, String((contact.totals && contact.totals.phone) || 0))]),
        h('section', { key: 'c4', className: 'hpa-card hpa-card--kpi' }, [h('span', null, 'Correo'), h('strong', null, String((contact.totals && contact.totals.email) || 0))]),
        h('section', { key: 'c4b', className: 'hpa-card hpa-card--kpi' }, [h('span', null, 'Descargas ficha técnica'), h('strong', null, String((sheet.totals && sheet.totals.total) || 0))]),

        h('section', { key: 'c5', className: 'hpa-card hpa-card--span2' }, [
          h('h2', null, 'Clics de contacto por día'),
          h(LineChart, { items: (contact.daily || []).map(function (x) { return { label: x.date, visits: x.total || 0 }; }), color: '#0f766e' })
        ]),
        h('section', { key: 'c6', className: 'hpa-card' }, [h('h2', null, 'Top inmuebles por clics'), h(ChartBars, { items: contact.top_properties || [] })]),
        h('section', { key: 'c7', className: 'hpa-card' }, [
          h('h2', null, 'Distribución por canal'),
          h(ChartBars, {
            items: [
              { label: 'WhatsApp', visits: (contact.totals && contact.totals.whatsapp) || 0 },
              { label: 'Teléfono', visits: (contact.totals && contact.totals.phone) || 0 },
              { label: 'Correo', visits: (contact.totals && contact.totals.email) || 0 }
            ]
          })
        ]),
        h('section', { key: 'c8', className: 'hpa-card hpa-card--span2' }, [
          h('h2', null, 'Asesores más contactados'),
          h(LineChart, { items: advisorItems, color: '#1d4ed8' })
        ]),
        h('section', { key: 'c9', className: 'hpa-card hpa-card--span2' }, [
          h('h2', null, 'Inmuebles por asesor (contactos)'),
          (contact.advisors || []).length ? h('div', { className: 'hpa-advisor-list' }, (contact.advisors || []).map(function (advisor, idx) {
            return h('div', { key: 'adv' + idx, className: 'hpa-advisor-item' }, [
              h('strong', { key: 'n' }, String(advisor.advisor_name || '-')),
              h('span', { key: 't', className: 'hpa-advisor-total' }, 'Total: ' + String(advisor.total_clicks || 0)),
              h('ul', { key: 'p', className: 'hpa-advisor-props' }, (advisor.properties || []).slice(0, 5).map(function (p, pidx) {
                return h('li', { key: 'p' + pidx }, String(p.title || ('#' + p.property_id)) + ' (' + String(p.clicks || 0) + ')');
              }))
            ]);
          })) : h('p', { className: 'hpa-empty' }, 'Sin datos para este rango.')
        ]),
        h('section', { key: 'c10', className: 'hpa-card hpa-card--span2' }, [
          h('h2', null, 'Descargas diarias de ficha técnica'),
          h(LineChart, {
            items: (sheet.daily || []).map(function (x) { return { label: x.date, visits: x.downloads || 0 }; }),
            color: '#9333ea'
          })
        ])
      ]) : null
    ]);
  }

  function mount(el, node) {
    var mounted = false;
    if (window.wp && window.wp.element && typeof window.wp.element.render === 'function') {
      window.wp.element.render(el, node);
      mounted = true;
    }
    if (!mounted && window.wp && window.wp.element && typeof window.wp.element.createRoot === 'function') {
      window.wp.element.createRoot(node).render(el);
      mounted = true;
    }
    if (!mounted && window.ReactDOM && typeof window.ReactDOM.render === 'function') {
      window.ReactDOM.render(el, node);
      mounted = true;
    }
    if (!mounted) {
      node.innerHTML = '<div class="notice notice-error"><p>No se pudo iniciar Analítica (renderer no disponible).</p></div>';
      if (window.console && typeof window.console.error === 'function') {
        window.console.error('Homlity Analytics: no React renderer available.');
      }
    }
  }

  mount(h(App), root);
}());
