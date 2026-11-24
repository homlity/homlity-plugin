/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const script = fs.readFileSync(path.join(__dirname, '../../assets/js/front-map.js'), 'utf8');

test('muestra el detalle de la propiedad en el popup del marcador', () => {
    const dom = new JSDOM(`<div class="homlity-front-leaflet-map"
        data-lat="4.7" data-lng="-74.1" data-title="Fallback"
        data-marker-popup='${JSON.stringify({
            title: 'Apartamento en Chapinero',
            url: 'https://example.test/propiedad',
            thumbnail: 'https://example.test/foto.jpg',
            operations: ['Arriendo'],
            prices: [{ label: 'Arriendo', amount: '$ 2.000.000' }],
            bedrooms: '3', bathrooms: '2', parking: '1', location: ['Chapinero', 'Bogotá']
        })}'
        data-related-markers='[]'></div>`, { runScripts: 'outside-only' });
    const popups = [];
    const map = {
        setView: jest.fn().mockReturnThis(),
        remove: jest.fn(),
        fitBounds: jest.fn()
    };
    const leaflet = {
        map: jest.fn(() => map),
        tileLayer: jest.fn(() => ({ addTo: jest.fn() })),
        marker: jest.fn(() => ({ addTo: jest.fn().mockReturnThis(), bindPopup: jest.fn((html) => { popups.push(html); return this; }) })),
        divIcon: jest.fn(() => ({}))
    };
    dom.window.L = leaflet;
    dom.window.eval(script);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

    expect(popups[0]).toContain('Apartamento en Chapinero');
    expect(popups[0]).toContain('Arriendo');
    expect(popups[0]).toContain('$ 2.000.000');
    expect(popups[0]).toContain('3');
    expect(popups[0]).toContain('2');
    expect(popups[0]).toContain('1');
    expect(popups[0]).toContain('Chapinero');
    dom.window.close();
});
