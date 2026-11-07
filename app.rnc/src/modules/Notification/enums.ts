/** What a bell entry is about — the API's notification `kind`s (rnc-1 R17; rnc-2 R9 the partner's moves). */
enum NOTIFICATION_KIND {
  MEETING_BOOKED = 'meeting_booked',
  NEW_LEAD = 'new_lead',
  REFERRAL_STATUS = 'referral_status',
}

export { NOTIFICATION_KIND };
