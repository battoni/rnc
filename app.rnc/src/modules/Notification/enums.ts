/** What a bell entry is about — the API's notification `kind`s (rnc-1 R17; rnc-2 R9 the partner's moves; rnc-3 F5 the assistant's three). */
enum NOTIFICATION_KIND {
  ASSISTANT_ANSWER = 'assistant_answer',
  ASSISTANT_NOTE_EXPIRING = 'assistant_note_expiring',
  ASSISTANT_QUESTION = 'assistant_question',
  /** A firm signed up for the beta on the root site (rnc-4 R29): the platform firm's team only. */
  BETA_SIGNUP = 'beta_signup',
  MEETING_BOOKED = 'meeting_booked',
  NEW_LEAD = 'new_lead',
  REFERRAL_STATUS = 'referral_status',
}

export { NOTIFICATION_KIND };
