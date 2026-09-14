/** @jest-environment node */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const script = fs.readFileSync(path.join(__dirname, '../../assets/js/property-filter.js'), 'utf8');
let dom;
let document;
let city;
let search;
let trigger;

beforeEach(() => {
    dom = new JSDOM(`<div class="property-filter-widget"><form>
        <select name="ciudad[]" class="property-filter-multiselect" multiple data-placeholder="Ciudad" data-search-empty="No se encontraron ciudades">
            <option value="">Ciudad</option>
            <option value="bogota" selected>Bogotá</option>
            <option value="medellin">Medellín</option>
            <option value="cali">Cali</option>
        </select>
        <select name="tipo">
            <option value="">Tipo</option>
            <option value="casa">Casa</option>
            <option value="apartamento">Apartamento</option>
            <option value="oficina" disabled>Oficina</option>
        </select>
        <select name="alcobas">
            <option value="">Habitaciones</option>
            <option value="1">1+</option>
            <option value="2">2+</option>
        </select>
        <select name="barrios[]" class="property-filter-multiselect" multiple>
            <option value="poblado" data-city-slug="medellin">El Poblado</option>
            <option value="chapinero" data-city-slug="bogota">Chapinero</option>
        </select>
    </form></div>`, { runScripts: 'outside-only' });
    document = dom.window.document;
    dom.window.eval(script);
    document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
    city = document.querySelector('select');
    search = document.querySelector('.hpf-multi__search');
    trigger = document.querySelector('.hpf-multi__trigger');
    trigger.click();
});

afterEach(() => dom.window.close());

function type(query) {
    search.value = query;
    search.dispatchEvent(new dom.window.Event('input', { bubbles: true }));
}

function items() {
    return Array.from(city.parentNode.querySelectorAll('[role="option"]'));
}

test('filters city names ignoring accents and case without losing selections', () => {
    expect(document.activeElement).toBe(search);
    type('  MEDELLIN  ');
    expect(items().map(item => item.textContent)).toEqual(['Medellín']);
    items()[0].click();
    expect(Array.from(city.selectedOptions, option => option.value)).toEqual(['bogota', 'medellin']);
    expect(new dom.window.FormData(document.querySelector('form')).getAll('ciudad[]')).toEqual(['bogota', 'medellin']);
    expect(document.activeElement).toBe(search);
    type('bogota');
    expect(items()[0].getAttribute('aria-selected')).toBe('true');
    items()[0].click();
    expect(Array.from(city.selectedOptions, option => option.value)).toEqual(['medellin']);
    expect(document.querySelector('option[value="chapinero"]').hidden).toBe(true);
    expect(document.querySelector('option[value="poblado"]').hidden).toBe(false);
});

test('shows empty results and resets the query when reopening', () => {
    type('ciudad inexistente');
    expect(items()).toHaveLength(0);
    expect(document.querySelector('[role="status"]').textContent).toBe('No se encontraron ciudades');
    search.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(trigger.getAttribute('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(trigger);
    trigger.click();
    expect(search.value).toBe('');
    expect(items()).toHaveLength(3);
    expect(document.querySelector('[role="status"]').hidden).toBe(true);
});

test('supports keyboard access and prevents Enter from submitting while typing', () => {
    type('cali');
    const enter = new dom.window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
    search.dispatchEvent(enter);
    expect(enter.defaultPrevented).toBe(true);
    search.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    expect(document.activeElement).toBe(items()[0]);
    expect(document.querySelectorAll('.hpf-multi__search')).toHaveLength(4);
});

test('single selects filter, replace the selection and allow clearing it', () => {
    const select = document.querySelector('[name="tipo"]');
    const wrapper = select.parentNode;
    const button = wrapper.querySelector('.hpf-multi__trigger');
    const input = wrapper.querySelector('input');
    const change = jest.fn();
    select.addEventListener('change', change);
    button.click();
    input.value = 'apart';
    input.dispatchEvent(new dom.window.Event('input'));
    expect(wrapper.querySelectorAll('[role="option"]')).toHaveLength(1);
    wrapper.querySelector('[role="option"]').click();
    expect(select.value).toBe('apartamento');
    expect(button.textContent).toContain('Apartamento');
    expect(button.getAttribute('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(button);
    expect(change).toHaveBeenCalledTimes(1);
    button.click();
    wrapper.querySelectorAll('[role="option"]')[1].click();
    expect(Array.from(select.selectedOptions, option => option.value)).toEqual(['casa']);
    button.click();
    wrapper.querySelector('[role="option"]').click();
    expect(new dom.window.FormData(document.querySelector('form')).get('tipo')).toBe('');
    expect(wrapper.querySelector('[role="listbox"]').getAttribute('aria-multiselectable')).toBe('false');
    expect(wrapper.querySelectorAll('[role="option"]')[3].disabled).toBe(true);
});

test('neighborhood results refresh when the city changes even without a selected neighborhood', () => {
    const neighborhood = document.querySelector('[name="barrios[]"]').parentNode;
    expect(Array.from(neighborhood.querySelectorAll('[role="option"]'), item => item.textContent)).toEqual(['Chapinero']);
    type('medellin');
    items()[0].click();
    expect(neighborhood.querySelectorAll('[role="option"]')).toHaveLength(2);
    type('bogota');
    items()[0].click();
    expect(Array.from(neighborhood.querySelectorAll('[role="option"]'), item => item.textContent)).toEqual(['El Poblado']);
    const input = neighborhood.querySelector('input');
    input.value = 'chapinero';
    input.dispatchEvent(new dom.window.Event('input'));
    expect(neighborhood.querySelectorAll('[role="option"]')).toHaveLength(0);
    expect(neighborhood.querySelector('[role="status"]').textContent).toBe('No se encontraron opciones');
});
