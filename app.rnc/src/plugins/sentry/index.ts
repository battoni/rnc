import type { App } from 'vue';
import * as Sentry from '@sentry/vue';
import { SENTRY_DNS, SENTRY_PROPAGATION_TARGETS } from '@Constants';
import router from '../../libraries/router/index';

export default {
  install(app: App) {
    if (!SENTRY_DNS || !SENTRY_PROPAGATION_TARGETS) return;

    Sentry.init({
      app,
      dsn: SENTRY_DNS,
      integrations: [Sentry.browserTracingIntegration({ router }), Sentry.replayIntegration()],
      tracesSampleRate: 1.0,
      tracePropagationTargets: SENTRY_PROPAGATION_TARGETS,
      replaysSessionSampleRate: 0.1,
      replaysOnErrorSampleRate: 1.0,
    });
  },
};
