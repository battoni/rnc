/** One of a brand's four proof points (rnc-3 R1). */
interface BrandProofPoint {
  label: string;
  value: string;
}

/** `BrandResource`: a sub-brand row of the console, with the leads started under it. */
interface Brand {
  active: boolean;
  brand_accent: string;
  brand_primary: string;
  id: number;
  lead_count: number;
  name: string;
  proof: BrandProofPoint[];
  short_name: string;
  slug: string;
  tagline: string | null;
}

/** `MainBrandResource`: the firm's own look, read-only here ("Marca principal"). */
interface MainBrand {
  brand_accent: string | null;
  brand_primary: string | null;
  lead_count: number;
  name: string | null;
}

/** `GET /api/brands`. */
interface BrandList {
  brands: Brand[];
  main: MainBrand;
}

/** `PATCH /api/brands/{id}` — what `UpdateBrandRequest` validates. */
interface BrandPayload {
  brand_accent: string;
  brand_primary: string;
  name: string;
  proof: BrandProofPoint[];
  short_name: string;
  tagline: string | null;
}

export type { Brand, BrandList, BrandPayload, BrandProofPoint, MainBrand };
