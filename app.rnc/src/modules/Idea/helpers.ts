import type { IdeaPayload, NewsPayload } from './interfaces';

/**
 * The multipart body an idea write sends.
 *
 * `removeAttachment` only travels when it is TRUE. FormData stringifies everything, so a `false`
 * would arrive as the string "false" — which Laravel's boolean validation reads as false, but
 * which the `nullable` rule would still let through as a set field. Sending only the true case
 * keeps "delete the file" a positive instruction and absence its own answer.
 */
function toIdeaFormData(payload: IdeaPayload): FormData {
  const FORM_DATA = new FormData();

  FORM_DATA.append('title', payload.title);
  FORM_DATA.append('description', payload.description);
  FORM_DATA.append('type', payload.type);

  if (payload.area) FORM_DATA.append('area', payload.area);
  if (payload.attachment) FORM_DATA.append('attachment', payload.attachment);
  if (payload.removeAttachment) FORM_DATA.append('removeAttachment', '1');

  return FORM_DATA;
}

/**
 * The multipart body a Novidades write sends. Multipart always, because the entry may carry a
 * cover and one request shape is easier to trust than two.
 *
 * Booleans travel only when TRUE. FormData stringifies everything, so a `false` arrives as the
 * string "false" — which Laravel's boolean rule reads correctly but which leaves "unset" and
 * "explicitly false" indistinguishable for the fields where that matters. `publish` is the
 * exception: it is sent either way, because pulling a published entry back to a draft is a
 * deliberate `false` and not an absence.
 *
 * Lists travel one entry per value under `name[]`, which is the shape PHP parses back into an
 * array. There is no way to write an EMPTY array in multipart at all — the field is simply
 * absent, and absent is what the API reads as "this request is not about areas", so an entry
 * would keep tags the writer had just removed. `clearAreas` is the same trick as `removeCover`
 * one field over: a boolean that travels only when true and says the emptiness out loud.
 */
function toNewsFormData(payload: NewsPayload): FormData {
  const FORM_DATA = new FormData();

  FORM_DATA.append('title', payload.title);
  FORM_DATA.append('body', payload.body);
  FORM_DATA.append('publish', payload.publish ? '1' : '0');

  if (payload.subtitle) FORM_DATA.append('subtitle', payload.subtitle);
  if (payload.link) FORM_DATA.append('link', payload.link);
  if (payload.ideaId) FORM_DATA.append('ideaId', String(payload.ideaId));

  payload.areas.forEach((area) => FORM_DATA.append('areas[]', area));

  if (payload.areas.length === 0) FORM_DATA.append('clearAreas', '1');

  if (payload.cover) FORM_DATA.append('cover', payload.cover);
  if (payload.removeCover) FORM_DATA.append('removeCover', '1');

  return FORM_DATA;
}

/**
 * Wrap a line the moderator typed as an HTML paragraph, escaping it first.
 *
 * The text comes from a plain textarea and the body is HTML. Interpolated raw, an announcement
 * like "Corrigido: a < b agora ordena certo" is parsed from the `<` onward as a tag, the server's
 * sanitiser drops it, and the published entry silently loses half its sentence. `&` and `>` mangle
 * the same way. This is not about XSS — the server sanitises on write — it is about not destroying
 * what somebody wrote.
 */
function toHtmlParagraph(text: string): string {
  const ESCAPED = text.trim().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  return `<p>${ESCAPED}</p>`;
}

export { toHtmlParagraph, toIdeaFormData, toNewsFormData };
