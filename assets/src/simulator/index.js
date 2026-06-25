import { defineCustomElement, defineComponent, h } from 'vue';
import SimuladorVenta from './SimuladorVenta.vue';
import SimuladorArriendo from './SimuladorArriendo.vue';

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

const SimuladorElement = defineCustomElement(SimuladorRoot);

if (!customElements.get('codwelt-simulador')) {
  customElements.define('codwelt-simulador', SimuladorElement);
}
