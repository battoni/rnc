/*
 * What a production build refuses to ship without (rnc-4 verify P2-S4). `env.constant.ts` falls back to
 * `localhost` for `VITE_ROOT_DOMAIN` so a developer needs no setup; in a production bundle that fallback would read
 * every `<slug>.<root>` host as the platform's and point every firm link at localhost — with no error anywhere.
 * Called by `vite.config.ts`; the dev server and the e2e (test-mode) build keep the fallback.
 */
function assertBuildEnv(command: 'build' | 'serve', mode: string, env: Record<string, string | undefined>) {
  const isProductionBuild = command === 'build' && mode === 'production';

  if (!isProductionBuild) return;

  if (!env.VITE_ROOT_DOMAIN?.trim()) {
    throw new Error(
      'VITE_ROOT_DOMAIN is empty: a production build needs the platform root domain (docs/deploy/runbook.md, Env inventory — SPA).'
    );
  }
}

export { assertBuildEnv };
