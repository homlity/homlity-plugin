import { render, createRoot } from '@wordpress/element';
import App from './App';
import './styles.css';

const detectCssUrl = () => {
  if (window.homlityConsignmentConfig?.cssUrl) {
    return window.homlityConsignmentConfig.cssUrl;
  }

  const currentScript = document.currentScript;
  if (!currentScript?.src) {
    return '';
  }

  return currentScript.src.replace(/[^/]+\.js(?:\?.*)?$/, 'index.css');
};

const normalizeLegacyConfig = (legacyConfig = {}) => ({
  primaryColor: legacyConfig?.theme?.color?.primary || '',
  textColor: legacyConfig?.theme?.color?.text || '',
});

const globalConfig = {
  ...(window.homlityConsignmentConfig || {}),
  cssUrl: detectCssUrl(),
};

const roots = document.querySelectorAll('[data-homlity-consignment-root], homlity-consignacion');

roots.forEach((host) => {
  const shadowRoot = host.shadowRoot || host.attachShadow({ mode: 'open' });
  const mountPoint = document.createElement('div');
  const styleReset = document.createElement('style');

  styleReset.textContent = `
    :host {
      display: block;
      color: inherit;
    }
    *, *::before, *::after {
      box-sizing: border-box;
    }
  `;

  shadowRoot.innerHTML = '';
  shadowRoot.appendChild(styleReset);

  if (globalConfig.cssUrl) {
    const styleLink = document.createElement('link');
    styleLink.rel = 'stylesheet';
    styleLink.href = globalConfig.cssUrl;
    shadowRoot.appendChild(styleLink);
  }

  shadowRoot.appendChild(mountPoint);

  let instanceConfig = {};
  if (host.matches('[data-homlity-consignment-root]')) {
    const rawConfig = host.getAttribute('data-config');
    if (rawConfig) {
      try {
        instanceConfig = JSON.parse(rawConfig);
      } catch (error) {
        // Keep rendering with global defaults if one instance config is malformed.
        instanceConfig = {};
      }
    }
  } else {
    instanceConfig = normalizeLegacyConfig(host.configuracion || {});
  }

  const app = <App hostElement={host} rootConfig={{ ...globalConfig, ...instanceConfig }} />;

  if (createRoot) {
    createRoot(mountPoint).render(app);
  } else {
    render(app, mountPoint);
  }
});
