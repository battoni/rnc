/** The funnel steps the site reports to `POST /api/public/events` (rnc-1 R7, plan AM2). */
enum TRACKING_EVENT_TYPE {
  BOOKING = 'booking',
  CTA = 'cta',
  DIAGNOSTIC_CONTACT = 'diag.contact',
  DIAGNOSTIC_RESULT = 'diag.result',
  DIAGNOSTIC_START = 'diag.start',
  VISIT = 'visit',
}

/** Where a visit came from — the only thing the API learns about the referrer (AM2). */
enum TRAFFIC_SOURCE {
  DIRECT = 'direto',
  ORGANIC = 'organico',
  PAID = 'pago',
  PARTNER = 'parceiro',
}

/** How many clients a firm signing up for the beta serves — the API's `BetaClientsBand` (rnc-4 R29). */
enum BETA_CLIENTS_BAND {
  ABOVE_1000 = 'acima_1000',
  UP_TO_100 = 'ate_100',
  FROM_101_TO_300 = 'de_101_a_300',
  FROM_301_TO_1000 = 'de_301_a_1000',
}

export { BETA_CLIENTS_BAND, TRACKING_EVENT_TYPE, TRAFFIC_SOURCE };
