/**
 * `PublicFirmResource` — the firm's brand and public contact, as the API sends it (`GET public/firm`).
 * Under a sub-brand (rnc-3 R2) the look is the brand's — `brand` its slug, the name, colours, tagline
 * and proof — and the contact stays the firm's. The main brand answers `brand` null; `short_name`,
 * `tagline` and `proof` are the firm's own, null where the site keeps its own copy (rnc-4 R8).
 * `is_platform` marks the root's firm, `platform.name` is the product's name (R9) and `logo_url` the
 * firm's uploaded logo (null under a sub-brand). Moved from the Site module (rnc-4 D10): the back
 * office reads it too.
 */
interface PublicFirm {
  address: string | null;
  brand?: string | null;
  brand_accent: string;
  brand_primary: string;
  business_hours: PublicFirmHours[];
  /** The footer's credentials line (rnc-4 F5a, W31), the firm's own; null or absent shows none. */
  credentials?: string | null;
  is_platform?: boolean;
  logo_text: string;
  logo_url?: string | null;
  mailbox: string | null;
  /** The firm's opt-in modules (rnc-4 R17, `FIRM_MODULE`): the site hides what is off — the chat. */
  modules?: string[];
  name: string;
  phone: string | null;
  phone_href: string | null;
  platform?: { name: string };
  proof?: PublicBrandProof[] | null;
  short_name?: string | null;
  slug: string;
  tagline?: string | null;
}

/** One of a brand's four proof points (rnc-3 R1). */
interface PublicBrandProof {
  label: string;
  value: string;
}

/** ISO weekdays (1 = Monday) and the opening hours on them. */
interface PublicFirmHours {
  from: string;
  to: string;
  weekdays: number[];
}

export type { PublicBrandProof, PublicFirm, PublicFirmHours };
