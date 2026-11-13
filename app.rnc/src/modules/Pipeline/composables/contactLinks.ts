/** Brazil's country code: the API stores phones as digits with it (`PhoneNumber::normalize`). */
const COUNTRY_CODE = '55';

interface ContactLinks {
  email: string | null;
  phone: string | null;
  whatsapp: string | null;
}

/**
 * The lead detail's contact actions as real links (R19): `tel:` and `https://wa.me/55…` from the
 * phone, `mailto:` from the e-mail. Null where the lead left nothing to dial or write to.
 */
function contactLinks(phone: string | null, email: string | null): ContactLinks {
  const digits = (phone ?? '').replace(/\D+/g, '');
  const international = digits === '' ? null : digits.startsWith(COUNTRY_CODE) ? digits : `${COUNTRY_CODE}${digits}`;

  return {
    email: email ? `mailto:${email}` : null,
    phone: international ? `tel:+${international}` : null,
    whatsapp: international ? `https://wa.me/${international}` : null,
  };
}

export { contactLinks };
export type { ContactLinks };
