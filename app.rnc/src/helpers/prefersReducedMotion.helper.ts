/*
 * Whether the person asked the system for less motion.
 *
 * Read at the moment it matters, never cached at module load: the setting can change while the
 * tab is open, and a cached `true` would keep every reveal off for the rest of the session.
 * `matchMedia` is missing in a bare jsdom, and a test harness that stubs it answers `false`;
 * either way "no answer" means "no preference", so motion plays.
 */
export default function prefersReducedMotion(): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
