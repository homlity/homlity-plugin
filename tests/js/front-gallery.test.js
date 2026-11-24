/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const script = fs.readFileSync(path.join(__dirname, '../../assets/js/front-gallery.js'), 'utf8');

function setup() {
    const dom = new JSDOM(`
        <div data-homlity-gallery="light">
            <a class="property-gallery__item--light" href="https://example.test/one.jpg"></a>
            <a class="property-gallery__item--light" href="https://example.test/two.jpg"></a>
        </div>
    `, { runScripts: 'outside-only' });
    const instances = [];
    dom.window.GLightbox = jest.fn((options) => {
        const instance = { openAt: jest.fn() };
        instances.push({ options, instance });
        return instance;
    });
    dom.window.eval(script);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
    return { dom, instances, node: dom.window.document.querySelector('[data-homlity-gallery]') };
}

test('reutiliza una única instancia de GLightbox por galería', () => {
    const { dom, instances, node } = setup();
    const links = node.querySelectorAll('a');

    expect(dom.window.GLightbox).toHaveBeenCalledTimes(1);
    links[1].click();

    expect(dom.window.GLightbox).toHaveBeenCalledTimes(1);
    expect(instances[0].instance.openAt).toHaveBeenCalledWith(1);
    dom.window.close();
});
