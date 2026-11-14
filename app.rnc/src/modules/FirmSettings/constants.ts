/** The settings screens, linked by name (celer-05): the rail links here. */
const FIRM_SETTINGS_ROUTE_NAMES = {
  consent: 'firm-consent',
  contact: 'firm-contact',
  specialists: 'firm-specialists',
  team: 'firm-team',
} as const;

/**
 * The keys the screens and their API ask (rnc-4 F5a): `firm.settings` opens the contact, hours and
 * consent routes; the team's logins are `auth/users`, behind the `users.*` keys (W77).
 */
const FIRM_SETTINGS_PERMISSIONS = {
  archive: 'users.archive',
  create: 'users.create',
  read: 'users.read',
  resetPassword: 'users.reset_password',
  settings: 'firm.settings',
  update: 'users.update',
} as const;

/** The limits the API's requests set (`UpdateFirmSettingsRequest`, `UpdateSpecialistHoursRequest`). */
const FIRM_SETTINGS_LIMITS = {
  firmWindows: 7,
  phone: 30,
  specialistWindows: 21,
  text: 255,
} as const;

/** The consent's limit and the placeholder it must carry (W79, `StoreConsentVersionRequest`). */
const FIRM_CONSENT_MAX_LENGTH = 1000;
const FIRM_CONSENT_PLACEHOLDER = '{escritorio}';

/** The only roles a firm member gives (W76, api `Rules\TeamRole`), in the order the form offers them. */
const FIRM_TEAM_ROLE_SLUGS = ['equipe', 'gestao'] as const;

/** ISO weekdays (1 = Monday), as the API stores hours. */
const FIRM_WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const;

/** A login's one-time password: letters and digits, like the provisioner's (W40), so it copies cleanly. */
const FIRM_PASSWORD_LENGTH = 16;
const FIRM_PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

/** A new hours window starts on the usual working week. */
const FIRM_NEW_WINDOW = { from: '09:00', to: '18:00', weekdays: [1, 2, 3, 4, 5] };
const FIRM_NEW_SPECIALIST_HOUR = { closes_at: '18:00', opens_at: '09:00', weekday: 1 };

export {
  FIRM_CONSENT_MAX_LENGTH,
  FIRM_CONSENT_PLACEHOLDER,
  FIRM_NEW_SPECIALIST_HOUR,
  FIRM_NEW_WINDOW,
  FIRM_PASSWORD_ALPHABET,
  FIRM_PASSWORD_LENGTH,
  FIRM_SETTINGS_LIMITS,
  FIRM_SETTINGS_PERMISSIONS,
  FIRM_SETTINGS_ROUTE_NAMES,
  FIRM_TEAM_ROLE_SLUGS,
  FIRM_WEEKDAYS,
};
