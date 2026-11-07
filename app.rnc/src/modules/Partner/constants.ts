import { PARTNER_SPECIALTY } from './enums';

/** The screens this module owns, linked by name (celer-05): the rail links here. */
const PARTNER_ROUTE_NAMES = { partners: 'partners', trail: 'referral-trail' } as const;

/** The keys of the network (plan AM8): Reinaldo and the operator manage and read; the team routes. */
const PARTNER_PERMISSIONS = { manage: 'partners.manage', trail: 'referrals.read' } as const;

/** Every specialty, in the order the form offers them (facts.md §3). */
const PARTNER_SPECIALTIES: PARTNER_SPECIALTY[] = Object.values(PARTNER_SPECIALTY);

/** A score from here up is a partner that covers something of this diagnostic (`PartnerMatcher::COVERED_BASE`). */
const COVERED_SCORE = 70;

/** The longest message the API keeps (`ReferLeadRequest::MESSAGE_MAX_LENGTH`). */
const REFERRAL_MESSAGE_MAX_LENGTH = 1000;

/** The field limits of the partner form (`StorePartnerRequest`). */
const PARTNER_FIELD_LIMITS = { city: 80, email: 160, name: 120, phone: 20 } as const;

/** The field limits of the portal-access form (`CreatePartnerAccessRequest`, rnc-2 R2). */
const PARTNER_ACCESS_LIMITS = { email: 160, username: 60 } as const;

/** The refusals the portal-access form names, by the API's stable `code` (`PartnerAccessException`). */
const PARTNER_ACCESS_ERROR_KEYS: Record<string, string> = {
  partner_access_exists: 'partners.access.exists',
  partner_access_missing: 'partners.access.missing',
  partner_inactive: 'partners.access.partnerInactive',
};

/** The refusals the routing form branches on, by the API's stable `code` (`ReferralException`). */
const REFERRAL_ERROR_KEYS: Record<string, string> = {
  already_referred: 'partners.routing.alreadyReferred',
  consent_required: 'partners.routing.consentRequired',
};

/** A page of the trail. */
const TRAIL_PER_PAGE = 20;

export {
  COVERED_SCORE,
  PARTNER_ACCESS_ERROR_KEYS,
  PARTNER_ACCESS_LIMITS,
  PARTNER_FIELD_LIMITS,
  PARTNER_PERMISSIONS,
  PARTNER_ROUTE_NAMES,
  PARTNER_SPECIALTIES,
  REFERRAL_ERROR_KEYS,
  REFERRAL_MESSAGE_MAX_LENGTH,
  TRAIL_PER_PAGE,
};
