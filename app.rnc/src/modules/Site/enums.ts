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

export { TRACKING_EVENT_TYPE, TRAFFIC_SOURCE };
