import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetSingleTipState, useSingleTip } from './useSingleTip';

describe('useSingleTip', () => {
  beforeEach(() => {
    resetSingleTipState();
  });

  it('closes the previous tip when opening a new one', () => {
    const { openTip } = useSingleTip();
    const closeFirst = vi.fn();
    const closeSecond = vi.fn();

    openTip(closeFirst);
    openTip(closeSecond);

    expect(closeFirst).toHaveBeenCalledOnce();
    expect(closeSecond).not.toHaveBeenCalled();
  });

  it('clears active state only when closeTip matches the active tip', () => {
    const { closeTip, openTip } = useSingleTip();
    const closeFirst = vi.fn();
    const closeSecond = vi.fn();

    openTip(closeFirst);
    closeTip(closeSecond);

    openTip(closeSecond);

    expect(closeFirst).toHaveBeenCalledOnce();
    expect(closeSecond).not.toHaveBeenCalled();
  });

  it('clears active state when the active tip closes', () => {
    const { closeTip, openTip } = useSingleTip();
    const closeFirst = vi.fn();
    const closeSecond = vi.fn();

    openTip(closeFirst);
    closeTip(closeFirst);
    openTip(closeSecond);

    expect(closeFirst).not.toHaveBeenCalled();
    expect(closeSecond).not.toHaveBeenCalled();
  });

  it('closes the active tip when the state is reset', () => {
    const { openTip } = useSingleTip();
    const closeActive = vi.fn();

    openTip(closeActive);
    resetSingleTipState();

    expect(closeActive).toHaveBeenCalledOnce();
  });

  it('does nothing when reset with no active tip', () => {
    expect(() => resetSingleTipState()).not.toThrow();
  });
});
