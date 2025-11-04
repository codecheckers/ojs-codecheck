import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import i18nExtractKeys from './i18nExtractKeys.vite.js';

export default defineConfig({
  plugins: [
    i18nExtractKeys(),
    vue()
  ],
  define: {
    'process.env': {},
    '__VUE_OPTIONS_API__': true,
    '__VUE_PROD_DEVTOOLS__': false,
    '__VUE_PROD_HYDRATION_MISMATCH_DETAILS__': false
  },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    lib: {
      entry: path.resolve(__dirname, 'resources/js/main.js'),
      name: 'CodecheckPlugin',
      formats: ['iife'],
      fileName: () => 'build.iife.js',
    },
    rollupOptions: {
      external: ['pkp', 'vue'],
      output: {
        globals: {
          pkp: 'pkp',
          vue: 'pkp.modules.vue',
        },
        assetFileNames: 'build.css',
      },
    },
  },
});