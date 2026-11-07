/** The stable `code` the API sends with a refused phone-code sign-in. The screen acts on it. */
enum LOGIN_CODE_ERROR {
  DELIVERY_FAILED = 'delivery_failed',
  EXPIRED = 'code_expired',
  INVALID = 'invalid_code',
  LOCKED = 'code_locked',
}

enum ROLES {
  SUPERADMIN = 'administrator',
  ADMIN = 'admin',
  SUPPORT = 'support',
  USER = 'user',
}

export { LOGIN_CODE_ERROR, ROLES };
