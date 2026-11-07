const API_URL = import.meta.env.VITE_API_URL || '';
const APP_URL = import.meta.env.VITE_APP_URL || '';
const IS_PROD = import.meta.env.PROD;
const LOGIN_FLOW = import.meta.env.VITE_LOGIN_FLOW || 'phone';

const SUPPORT_NAMES = import.meta.env.VITE_SUPPORT_NAMES || ['Support'];
const DEFAULT_LANGUAGE = import.meta.env.VITE_DEFAULT_LANGUAGE || 'pt-BR';

/** The product's documentation portal. Unset means the navigation simply has no such entry. */
const DOCS_URL = import.meta.env.VITE_DOCS_URL || '';

const SENTRY_AUTH_TOKEN = import.meta.env.VITE_SENTRY_AUTH_TOKEN || null;
const SENTRY_DNS = import.meta.env.VITE_SENTRY || null;
const SENTRY_PROPAGATION_TARGETS = import.meta.env.VITE_SENTRY_PROPAGATION_TARGETS || null;

export {
  API_URL,
  APP_URL,
  DEFAULT_LANGUAGE,
  DOCS_URL,
  IS_PROD,
  LOGIN_FLOW,
  SENTRY_AUTH_TOKEN,
  SENTRY_DNS,
  SENTRY_PROPAGATION_TARGETS,
  SUPPORT_NAMES,
};
