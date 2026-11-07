/** What a bell entry is about — the API's notification `kind`s (rnc-1 R17; rnc-3 F5 the assistant's three). */
enum NOTIFICATION_KIND {
  ASSISTANT_ANSWER = 'assistant_answer',
  ASSISTANT_NOTE_EXPIRING = 'assistant_note_expiring',
  ASSISTANT_QUESTION = 'assistant_question',
  MEETING_BOOKED = 'meeting_booked',
  NEW_LEAD = 'new_lead',
}

export { NOTIFICATION_KIND };
