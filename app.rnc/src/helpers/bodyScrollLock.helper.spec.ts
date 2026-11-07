import { afterEach, describe, expect, it } from 'vitest';
import toggleBodyScrollLock from './bodyScrollLock.helper';

describe('toggleBodyScrollLock', () => {
  afterEach(() => {
    document.body.classList.remove('overflow-hidden');
  });

  it('adds overflow-hidden when the body is unlocked', () => {
    expect(document.body.classList.contains('overflow-hidden')).toBe(false);

    toggleBodyScrollLock();

    expect(document.body.classList.contains('overflow-hidden')).toBe(true);
  });

  it('removes overflow-hidden when the body is already locked', () => {
    document.body.classList.add('overflow-hidden');

    toggleBodyScrollLock();

    expect(document.body.classList.contains('overflow-hidden')).toBe(false);
  });

  it('toggles back and forth on repeated calls', () => {
    toggleBodyScrollLock();
    toggleBodyScrollLock();

    expect(document.body.classList.contains('overflow-hidden')).toBe(false);
  });
});
