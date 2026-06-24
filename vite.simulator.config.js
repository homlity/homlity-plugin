import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [
    vue({
      customElement: true,
    }),
  ],
  build: {
    outDir: resolve(__dirname, 'assets/js'),
    emptyOutDir: false,
    lib: {
      entry: resolve(__dirname, 'assets/src/simulator/index.js'),
      name: 'CodweltSimulador',
      fileName: 'simulator',
      formats: ['iife'],
    },
    rollupOptions: {
      output: {
        entryFileNames: 'simulator.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'simulator.css';
          }
          return assetInfo.name;
        },
        inlineDynamicImports: true,
      },
    },
    target: 'es2020',
    minify: false,
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production'),
    '__VUE_OPTIONS_API__': true,
    '__VUE_PROD_DEVTOOLS__': false,
    '__VUE_PROD_HYDRATION_MISMATCH_DETAILS__': false,
  },
});
