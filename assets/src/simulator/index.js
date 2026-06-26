import { defineCustomElement, defineComponent, h } from 'vue';
import SimuladorVenta from './SimuladorVenta.vue';
import SimuladorArriendo from './SimuladorArriendo.vue';

const shadowBaseStyles = `
  :host {
    display: block;
    width: 100%;
    color: #111827;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 14px;
    line-height: 1.5;
    text-align: left;
    box-sizing: border-box;
  }

  :host,
  :host *,
  :host *::before,
  :host *::after {
    box-sizing: border-box;
  }
`;

const SimuladorRoot = defineComponent({
  name: 'CodweltSimulador',
  props: {
    modo: { type: String, default: 'venta' },
    configuracion: { type: [String, Object], default: '{}' },
    logo: { type: String, default: '' },
  },
  setup(props) {
    return () => {
      let config = {};
      try {
        if (typeof props.configuracion === 'string') {
          config = props.configuracion ? JSON.parse(props.configuracion) : {};
        } else {
          config = props.configuracion || {};
        }
      } catch {
        config = {};
      }

      const modeConfig = props.modo === 'venta' ? (config.venta || {}) : (config.arriendo || {});
      const systemLogo = config.system?.logo || props.logo || '';

      if (props.modo === 'venta') {
        return h(SimuladorVenta, { configuracion: modeConfig, logo: systemLogo });
      }
      return h(SimuladorArriendo, { configuracion: modeConfig, logo: systemLogo });
    };
  },
});

const SimuladorElement = defineCustomElement(SimuladorRoot, {
  shadowRoot: true,
  styles: [shadowBaseStyles],
});

if (!customElements.get('codwelt-simulador')) {
  customElements.define('codwelt-simulador', SimuladorElement);
}
