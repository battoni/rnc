import type { RouteRecordRaw } from 'vue-router';
import { FIRM_SETTINGS_PERMISSIONS, FIRM_SETTINGS_ROUTE_NAMES } from './constants';

/*
 * The firm's settings for its manager (rnc-4 R30–R33), under "Gestão": signed in, by permission — the
 * team's list asks `users.read` like its API, the rest `firm.settings`. A core surface: no `meta.module`.
 */
const contactView = () => import('./views/Contact/FirmContactView.view.vue');
const teamView = () => import('./views/Team/FirmTeamView.view.vue');
const specialistsView = () => import('./views/Specialists/FirmSpecialistsView.view.vue');
const consentView = () => import('./views/Consent/FirmConsentView.view.vue');

const SCREENS = [
  { component: contactView, en: 'firm', name: FIRM_SETTINGS_ROUTE_NAMES.contact, ptBR: 'escritorio' },
  { component: teamView, en: 'team', name: FIRM_SETTINGS_ROUTE_NAMES.team, ptBR: 'equipe' },
  { component: specialistsView, en: 'specialists', name: FIRM_SETTINGS_ROUTE_NAMES.specialists, ptBR: 'especialistas' },
  { component: consentView, en: 'consent', name: FIRM_SETTINGS_ROUTE_NAMES.consent, ptBR: 'consentimento' },
];

const FirmSettingsModuleRoutes: RouteRecordRaw[] = SCREENS.flatMap((screen) => {
  const permission =
    screen.name === FIRM_SETTINGS_ROUTE_NAMES.team
      ? FIRM_SETTINGS_PERMISSIONS.read
      : FIRM_SETTINGS_PERMISSIONS.settings;

  return [
    { component: screen.component, meta: { permission }, name: `${screen.name}-en`, path: `/management/${screen.en}` },
    {
      component: screen.component,
      meta: { permission },
      name: `${screen.name}-pt-BR`,
      path: `/gestao/${screen.ptBR}`,
    },
  ];
});

export { FirmSettingsModuleRoutes };
