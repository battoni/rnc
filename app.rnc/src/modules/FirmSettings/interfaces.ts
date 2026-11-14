import type { PublicFirmHours } from '@Interfaces';

/** `FirmSettingsResource` (rnc-4 R30, C16): the firm's contact data, hours and credentials line. */
interface FirmSettings {
  address: string | null;
  business_hours: PublicFirmHours[] | null;
  credentials: string | null;
  mailbox: string | null;
  name: string;
  phone: string | null;
  slug: string;
}

/** `UpdateFirmSettingsRequest` — no look and no module field: those are the operator's (D25). */
interface FirmSettingsPayload {
  address: string | null;
  business_hours: PublicFirmHours[];
  credentials: string | null;
  mailbox: string | null;
  phone: string | null;
}

/** `SpecialistHourResource` — one window of an ISO weekday, `HH:MM` local times. */
interface SpecialistHour {
  closes_at: string;
  opens_at: string;
  weekday: number;
}

/** `SpecialistResource` — a bookable (flagged) specialist with its week in reading order. */
interface Specialist {
  area: string | null;
  hours: SpecialistHour[];
  id: number;
  name: string;
  user_id: number;
}

/** `UpdateSpecialistHoursRequest` — the whole week; an empty list closes the agenda. */
interface SpecialistHoursPayload {
  hours: SpecialistHour[];
}

/** `ConsentVersionResource` — the stored words, unfilled; the author is null for a seeded version. */
interface ConsentVersion {
  author: { id: number; name: string } | null;
  created_at: string;
  text: string;
  version: number;
}

/** `StoreConsentVersionRequest`. */
interface ConsentVersionPayload {
  text: string;
}

/** A role the team form offers — the team's two, read from `auth/roles`. */
interface TeamRoleOption {
  id: number;
  name: string;
  slug: string;
}

/** `StoreUserRequest` as the manager sends it: the firm is never sent (the API stamps its own, AM2). */
interface TeamMemberCreatePayload {
  is_specialist: boolean;
  last_name: string;
  name: string;
  password: string;
  role_id: number;
  status: 'active';
  username: string;
}

/** `UpdateUserRequest` for an edit — the password goes alone through the reset. */
interface TeamMemberUpdatePayload {
  is_specialist?: boolean;
  last_name?: string;
  name?: string;
  password?: string;
  role_id?: number;
  username?: string;
}

/** A refused save as the API provider throws it: a 422's `errors`, or a translated sentence. */
interface SaveFailure {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
  translatedMessage?: string;
}

export type {
  ConsentVersion,
  ConsentVersionPayload,
  FirmSettings,
  FirmSettingsPayload,
  SaveFailure,
  Specialist,
  SpecialistHour,
  SpecialistHoursPayload,
  TeamMemberCreatePayload,
  TeamMemberUpdatePayload,
  TeamRoleOption,
};
