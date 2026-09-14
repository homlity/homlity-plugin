/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const script = fs.readFileSync(path.join(__dirname, '../../assets/js/property-map-tabs.js'), 'utf8');
let dom, root, instance;
function setup(settings = {}) {
  dom = new JSDOM(`<section data-property-map data-map-id="hml-map-1" data-lat="4.65" data-lng="-74.05">
    <button class="property-map__tab" data-map-tab="map"></button>
    <button class="property-map__tab" data-map-tab="street"></button>
    <div class="property-map__panel" data-map-panel="map"></div>
    <div class="property-map__panel" data-map-panel="street"><div class="property-map__street-canvas" hidden></div><iframe data-map-src="" hidden></iframe></div>
    <p data-map-street-fallback hidden><span data-map-street-message></span></p>
  </section>`, { runScripts: 'outside-only', pretendToBeVisual: true });
  root = dom.window.document.querySelector('section');
  root.dataset.settings = JSON.stringify({street_error_message: 'Error de carga', street_unavailable_message: 'Sin cobertura', ...settings});
  dom.window.eval(script);
  dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
  instance = root.__homlityPropertyMap;
}
afterEach(() => dom.window.close());
test('uses the iframe even for a widget saved with the old API mode', () => {
  setup({street_mode: 'google_js', google_maps_api_key: 'old-key'});
  const iframe = root.querySelector('iframe');
  const url = 'https://www.google.com/maps/embed?pb=!4v1638990616651!6m8!1m7!1toZz0mw!2m2!1d4.65!2d-74.05!3f90!4f0!5f0.7820865974627469';
  iframe.dataset.mapSrc = url;
  root.querySelector('[data-map-tab="street"]').click();
  expect(iframe.src).toBe(url);
  expect(iframe.hidden).toBe(false);
  expect(root.querySelector('[data-map-street-fallback]').hidden).toBe(true);
  expect(dom.window.document.querySelectorAll('script')).toHaveLength(0);
  root.querySelector('[data-map-tab="map"]').click();
  root.querySelector('[data-map-tab="street"]').click();
  expect(iframe.src).toBe(url);
});
test('does not load the iframe before the Street View tab is opened', () => {
  setup();
  expect(root.querySelector('iframe').hasAttribute('src')).toBe(false);
  expect(dom.window.document.querySelectorAll('script')).toHaveLength(0);
});
