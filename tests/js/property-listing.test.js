/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const script = fs.readFileSync(path.join(__dirname, '../../assets/js/property-listing.js'), 'utf8');
let dom;
afterEach(() => dom.window.close());
function setup(view = 'grid') {
    dom = new JSDOM(`<div class="property-listing" data-view="${view}" data-empty-message="Sin resultados &lt;seguros&gt;" data-preset-tag="7" data-preset-tag-ids="[7]">
      <select class="property-listing__sort"><option value="date">Fecha</option><option value="price_asc">Precio</option></select>
      <div class="property-listing__grid"></div><div class="property-listing__map-container"><div class="property-listing__map" id="listing-map"></div></div>
    </div>`, { runScripts: 'outside-only', url: 'https://example.test/' });
    return dom.window;
}
test('AJAX empty results use configured text safely and retain legacy tag data', async () => {
    const win = setup();
    win.fetch = jest.fn().mockResolvedValue({ json: async () => ({ success: true, data: { html: '', total: 0, pages: 0, map_data: [] } }) });
    win.eval(script);
    win.document.dispatchEvent(new win.Event('DOMContentLoaded'));
    const sort = win.document.querySelector('select');
    sort.value = 'price_asc';
    sort.dispatchEvent(new win.Event('change'));
    await new Promise(resolve => setTimeout(resolve, 0));
    expect(win.document.querySelector('.property-listing__empty').textContent).toBe('Sin resultados <seguros>');
    expect(win.document.querySelector('seguros')).toBeNull();
    expect(win.fetch.mock.calls[0][1].body).toContain('preset_tag=7');
});
test('actual map size changes invalidate the Leaflet map', () => {
    const win = setup('map');
    let onResize;
    const observe = jest.fn();
    win.ResizeObserver = class { constructor(callback) { onResize = callback; } observe = observe; };
    const map = { setView: jest.fn().mockReturnThis(), invalidateSize: jest.fn() };
    win.L = { map: jest.fn(() => map), tileLayer: jest.fn(() => ({ addTo: jest.fn() })) };
    win.eval(script);
    win.document.dispatchEvent(new win.Event('DOMContentLoaded'));
    expect(observe).toHaveBeenCalledWith(win.document.querySelector('.property-listing__map'));
    onResize();
    expect(map.invalidateSize).toHaveBeenCalledTimes(1);
});
