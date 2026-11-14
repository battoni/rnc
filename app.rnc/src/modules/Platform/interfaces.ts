/** One of a firm's four proof points (rnc-4 R8): the landing's strip. */
interface PlatformFirmProofPoint {
  label: string;
  value: string;
}

/** `PlatformFirmResource`: one firm as the console lists it (rnc-4 R25). */
interface PlatformFirm {
  brand_accent: string | null;
  brand_primary: string | null;
  created_at: string | null;
  id: number;
  is_platform: boolean;
  lead_count: number;
  logo_text: string | null;
  /** The firm's opt-in modules (`FIRM_MODULE`), in the catalogue's order; core is never listed. */
  modules: string[];
  name: string;
  proof: PlatformFirmProofPoint[] | null;
  short_name: string | null;
  /** The firm's own address, `http://<slug>.<root>` — the subdomain link. */
  site_url: string;
  slug: string;
  tagline: string | null;
}

/** The manager part of `ProvisionedFirmResource` and `ManagerLoginResource`. */
interface FirmManager {
  email: string | null;
  id: number;
  username: string;
}

/** `ProvisionedFirmResource`: a new firm, its manager and the password — answered once. */
interface ProvisionedFirm {
  firm: PlatformFirm;
  manager: FirmManager;
  password: string;
}

/** `ManagerLoginResource`: a manager login made (`created`) or reset — the password answered once. */
interface ManagerLogin {
  created: boolean;
  manager: FirmManager;
  password: string;
}

/** `POST /api/platform/firms` — `StorePlatformFirmRequest` validates. */
interface CreateFirmPayload {
  email: string | null;
  manager: string;
  name: string;
  slug: string;
}

/** `PATCH /api/platform/firms/{id}` — `UpdatePlatformFirmRequest` validates. */
interface UpdateFirmLookPayload {
  brand_accent: string;
  brand_primary: string;
  logo_text: string;
  name: string;
  proof: PlatformFirmProofPoint[] | null;
  short_name: string;
  tagline: string | null;
}

/** `PUT /api/platform/firms/{id}/modules` — the WHOLE opt-in list (`UpdatePlatformFirmModulesRequest`). */
interface FirmModulesPayload {
  modules: string[];
}

/** `POST /api/platform/firms/{id}/manager` — `StoreFirmManagerRequest` validates. */
interface FirmManagerPayload {
  email: string | null;
  username: string;
}

/** `GET`/`PUT /api/platform/consent-default`'s answer. */
interface ConsentDefault {
  text: string;
}

/** `PUT /api/platform/consent-default` — `UpdateConsentDefaultRequest` validates. */
interface ConsentDefaultPayload {
  text: string;
}

export type {
  ConsentDefault,
  ConsentDefaultPayload,
  CreateFirmPayload,
  FirmManager,
  FirmManagerPayload,
  FirmModulesPayload,
  ManagerLogin,
  PlatformFirm,
  PlatformFirmProofPoint,
  ProvisionedFirm,
  UpdateFirmLookPayload,
};
