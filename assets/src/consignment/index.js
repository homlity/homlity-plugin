import { render, createRoot } from '@wordpress/element';
import App from './App';
import './styles.css';

const root = document.getElementById('homlity-consignment-form-root');
if (root) {
  if (createRoot) {
    createRoot(root).render(<App />);
  } else {
    render(<App />, root);
  }
}
