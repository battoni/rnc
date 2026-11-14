import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import * as fs from 'fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, URL } from 'node:url';
import Components from 'unplugin-vue-components/vite';
import { defineConfig, loadEnv } from 'vite';
import vueDevTools from 'vite-plugin-vue-devtools';
import { assertBuildEnv } from './buildEnv';

function getPath(path: string) {
  return fileURLToPath(new URL(path, import.meta.url));
}

export default defineConfig(({ command, mode }) => {
  const isLocal = mode === 'development';
  process.env = { ...process.env, ...loadEnv(mode, process.cwd()) };
  assertBuildEnv(command, mode, process.env);

  const appUrl = process.env.VITE_APP_URL || 'localhost';
  const hostname = appUrl.replace(/^https?:\/\//, '').split(':')[0];

  // Single source of truth for the active brand theme. `@ActiveTheme` (imported
  // by src/styles/theme/colors.css) and the ACTIVE_THEME constant both resolve
  // from VITE_ACTIVE_THEME, so switching themes is a one-line .env change.
  const activeTheme = process.env.VITE_ACTIVE_THEME || 'hub';

  // Plain http when no certificate is named (rnc-1 AM1: `http://localhost:5173`, no Valet). A
  // project serving through Valet names its key and cert and gets https + wss as before.
  const sslKey = process.env.VITE_LOCAL_SSL_KEY;
  const sslCert = process.env.VITE_LOCAL_SSL_CERT;
  const hasLocalSsl = !!sslKey && !!sslCert;

  /*
   * rnc-4 D4: the SPA calls the API same-origin at `/api`, so each firm host
   * (`franco.localhost:5173`, `alvorada.localhost:5173`) keeps its own host-only session cookie.
   * `changeOrigin: false` keeps the browser's Host header, which the API's QA login-as reads (C2).
   * `allowedHosts` lets Vite answer every `*.localhost` host. Local, test mode and preview alike:
   * the e2e server runs in test mode, and an `/api` that only the dev server proxied would not exist
   * there (plan AM4). `/storage` is the API's public disk (a firm's logo, rnc-4 F4c): the SPA asks for
   * it on its own host (`toStorageAssetPath`), so it goes to the same target — the deploy's vhost does
   * the same per host (plan AM10). Needs `php artisan storage:link` in api.rnc.
   */
  const apiTarget = { target: process.env.VITE_API_PROXY_TARGET || 'http://localhost:8000', changeOrigin: false };
  const apiProxy = { '/api': apiTarget, '/storage': apiTarget };
  const allowedHosts = ['.localhost'];

  return defineConfig({
    preview: { allowedHosts, proxy: apiProxy },
    server: isLocal
      ? {
          allowedHosts,
          proxy: apiProxy,
          host: hostname,
          https: hasLocalSsl
            ? {
                key: fs.readFileSync(`${process.env.HOME}/${sslKey}`, 'utf8'),
                cert: fs.readFileSync(`${process.env.HOME}/${sslCert}`, 'utf8'),
              }
            : undefined,
          port: 5173,
          strictPort: true,
          hmr: {
            protocol: hasLocalSsl ? 'wss' : 'ws',
            host: hostname,
            port: 5173,
            clientPort: 5173,
          },
          watch: {
            usePolling: false,
          },
        }
      : { allowedHosts, proxy: apiProxy },
    plugins: [
      vue(),
      vueDevTools(),
      tailwindcss(),
      VueI18nPlugin({
        // Only the top-level CORE locales. Project overrides in src/locales/local/* are
        // deep-merged explicitly in src/libraries/i18n/index.ts (the core/local pattern).
        include: resolve(dirname(fileURLToPath(import.meta.url)), './src/locales/*.json'),
      }),
      Components({
        dirs: ['src/modules/*', 'src/components/*', 'src/layouts/*'],
        dts: true,
        resolvers: [PrimeVueResolver()],
        types: [
          {
            from: 'vue-router',
            names: ['RouterLink', 'RouterView'],
          },
        ],
      }),
    ],
    optimizeDeps: {
      include: [
        '@primevue/forms/form',
        '@primevue/forms/formfield',
        '@vueuse/core',
        'primevue/badge',
        'primevue/button',
        'primevue/card',
        'primevue/checkbox',
        'primevue/column',
        'primevue/confirmpopup',
        'primevue/datatable',
        'primevue/dialog',
        'primevue/divider',
        'primevue/drawer',
        'primevue/editor',
        'primevue/floatlabel',
        'primevue/iconfield',
        'primevue/iftalabel',
        'primevue/inputgroup',
        'primevue/inputgroupaddon',
        'primevue/inputicon',
        'primevue/inputmask',
        'primevue/inputnumber',
        'primevue/inputotp',
        'primevue/inputtext',
        'primevue/message',
        'primevue/multiselect',
        'primevue/paginator',
        'primevue/panel',
        'primevue/password',
        'primevue/popover',
        'primevue/radiobutton',
        'primevue/select',
        'primevue/selectbutton',
        'primevue/skeleton',
        'primevue/tab',
        'primevue/tablist',
        'primevue/tabpanel',
        'primevue/tabpanels',
        'primevue/tabs',
        'primevue/tag',
        'primevue/textarea',
        'primevue/toast',
        'primevue/toggleswitch',
        'primevue/tooltip',
      ],
    },
    resolve: {
      alias: {
        '@': getPath('./src'),
        '@Composables': getPath('./src/composables'),
        '@Composables/*': getPath('./src/composables/*'),
        '@Assets': getPath('./src/assets'),
        '@Assets/*': getPath('./src/assets/*'),
        '@Constants': getPath('./src/constants'),
        '@Constants/*': getPath('./src/constants/*'),
        '@Directives': getPath('./src/directives'),
        '@Directives/*': getPath('./src/directives/*'),
        '@Enums': getPath('./src/enums'),
        '@Enums/*': getPath('./src/enums/*'),
        '@Helpers': getPath('./src/helpers'),
        '@Helpers/*': getPath('./src/helpers/*'),
        '@Interfaces': getPath('./src/interfaces'),
        '@Interfaces/*': getPath('./src/interfaces/*'),
        '@Libraries': getPath('./src/libraries'),
        '@Libraries/*': getPath('./src/libraries/*'),
        '@Plugins': getPath('./src/plugins'),
        '@Plugins/*': getPath('./src/plugins/*'),
        '@Providers': getPath('./src/providers'),
        '@Providers/*': getPath('./src/providers/*'),
        '@Services': getPath('./src/services'),
        '@Services/*': getPath('./src/services/*'),
        '@Stores': getPath('./src/stores'),
        '@Stores/*': getPath('./src/stores/*'),
        '@Styles': getPath('./src/styles'),
        '@Styles/*': getPath('./src/styles/*'),
        '@ActiveTheme': getPath(`./src/styles/theme/themes/${activeTheme}.css`),
        '@Types': getPath('./src/types'),
        '@Types/*': getPath('./src/types/*'),

        '@AuthModule': getPath('./src/modules/Auth'),
        '@AuthModule/*': getPath('./src/modules/Auth/*'),
        '@DiagnosticModule': getPath('./src/modules/Diagnostic'),
        '@DiagnosticModule/*': getPath('./src/modules/Diagnostic/*'),
        '@FiscalModule': getPath('./src/modules/Fiscal'),
        '@FiscalModule/*': getPath('./src/modules/Fiscal/*'),
        '@BriefingModule': getPath('./src/modules/Briefing'),
        '@BriefingModule/*': getPath('./src/modules/Briefing/*'),
        '@ContractsModule': getPath('./src/modules/Contracts'),
        '@ContractsModule/*': getPath('./src/modules/Contracts/*'),
        '@RadarModule': getPath('./src/modules/Radar'),
        '@RadarModule/*': getPath('./src/modules/Radar/*'),
        '@HomeModule': getPath('./src/modules/Home'),
        '@HomeModule/*': getPath('./src/modules/Home/*'),
        '@NotificationModule': getPath('./src/modules/Notification'),
        '@NotificationModule/*': getPath('./src/modules/Notification/*'),
        '@MetricsModule': getPath('./src/modules/Metrics'),
        '@MetricsModule/*': getPath('./src/modules/Metrics/*'),
        '@AssistantModule': getPath('./src/modules/Assistant'),
        '@AssistantModule/*': getPath('./src/modules/Assistant/*'),
        '@BrandsModule': getPath('./src/modules/Brands'),
        '@BrandsModule/*': getPath('./src/modules/Brands/*'),
        '@FirmSettingsModule': getPath('./src/modules/FirmSettings'),
        '@FirmSettingsModule/*': getPath('./src/modules/FirmSettings/*'),
        '@PlatformModule': getPath('./src/modules/Platform'),
        '@PlatformModule/*': getPath('./src/modules/Platform/*'),
        '@ChannelModule': getPath('./src/modules/Channel'),
        '@ChannelModule/*': getPath('./src/modules/Channel/*'),
        '@PartnerModule': getPath('./src/modules/Partner'),
        '@PartnerModule/*': getPath('./src/modules/Partner/*'),
        '@PortalModule': getPath('./src/modules/Portal'),
        '@PortalModule/*': getPath('./src/modules/Portal/*'),
        '@RepricingModule': getPath('./src/modules/Repricing'),
        '@RepricingModule/*': getPath('./src/modules/Repricing/*'),
        '@PersonalizationModule': getPath('./src/modules/Personalization'),
        '@PersonalizationModule/*': getPath('./src/modules/Personalization/*'),
        '@PipelineModule': getPath('./src/modules/Pipeline'),
        '@PipelineModule/*': getPath('./src/modules/Pipeline/*'),
        '@SiteModule': getPath('./src/modules/Site'),
        '@SiteModule/*': getPath('./src/modules/Site/*'),
        '@UserModule': getPath('./src/modules/User'),
        '@UserModule/*': getPath('./src/modules/User/*'),
      },
    },
    build: {
      rollupOptions: {
        output: {
          manualChunks: (id) => {
            // Vendor chunks for large dependencies
            if (id.includes('node_modules')) {
              // prettier-ignore
              return id.includes('axios') ? 'vendor-axios'
              : id.includes('vue') || id.includes('vue-router') || id.includes('pinia') ? 'vendor-vue'
              : id.includes('primevue') || id.includes('@primeuix') ? 'vendor-primevue'
              : 'vendor';
            }

            // Keep ApiProvider wrapper and axios wrapper in the same chunk to avoid circular dependency
            // The axios npm package itself goes to vendor-axios above
            if (
              id.includes('/src/providers/ApiProvider/') ||
              (id.includes('/src/libraries/axios/') && !id.includes('node_modules'))
            ) {
              return 'api-core';
            }
          },
        },
      },
      chunkSizeWarningLimit: 600,
    },
  });
});
