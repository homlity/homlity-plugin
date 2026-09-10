/** @jest-environment node */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const pluginRoot = path.join(__dirname, '../..');
const bundle = fs.readFileSync(path.join(pluginRoot, 'assets/js/simulator.js'), 'utf8');

function renderShortcode(mode) {
  return execFileSync('php', ['-r', `
    require 'tests/bootstrap.php';
    $service = new Homlity\\PluginInmobiliario\\Services\\SimulatorService();
    echo $service->renderShortcode(['modo' => '${mode}']);
  `], { cwd: pluginRoot, encoding: 'utf8' });
}

describe('el shortcode funciona sin ejecutar scripts en línea', () => {
  it.each(['venta', 'arriendo'])('monta %s con los ajustes del shortcode', (mode) => {
    const dom = new JSDOM(renderShortcode(mode), { runScripts: 'outside-only' });
    try {
      dom.window.eval(bundle);
      const element = dom.window.document.querySelector('codwelt-simulador');
      expect(element.shadowRoot.querySelector(`.simulador-${mode}`)).not.toBeNull();
      expect(JSON.parse(element.getAttribute('configuracion'))).toHaveProperty(`${mode}.porcentajeIva`);
    } finally {
      dom.window.close();
    }
  });
});
