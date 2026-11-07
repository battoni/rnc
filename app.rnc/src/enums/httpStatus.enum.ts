/**
 * The status codes this app BRANCHES on — not a catalogue of HTTP.
 *
 * A bare `422` in a handler reads as a number until you remember what the API does with it; and the
 * same number appears in eight files, so a reader has to rebuild the meaning every time. Only codes
 * the front actually treats differently belong here.
 */
enum HTTP_STATUS {
  UNAUTHORIZED = 401,
  FORBIDDEN = 403,
  NOT_FOUND = 404,
  CONFLICT = 409,
  UNPROCESSABLE_ENTITY = 422,
  TOO_MANY_REQUESTS = 429,
}

export { HTTP_STATUS };
