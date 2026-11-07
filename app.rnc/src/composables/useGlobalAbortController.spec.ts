import { beforeEach, describe, expect, it } from 'vitest';
import { useGlobalAbortController } from './useGlobalAbortController';

describe('useGlobalAbortController', () => {
  let api: ReturnType<typeof useGlobalAbortController>;

  beforeEach(() => {
    api = useGlobalAbortController();
    // reset to a fresh, unaborted controller for each test
    api.createNewController();
  });

  it('exposes a non-aborted signal initially', () => {
    expect(api.getSignal().aborted).toBe(false);
  });

  it('aborts the current signal when cancelAllRequests is called', () => {
    const signal = api.getSignal();

    api.cancelAllRequests();

    expect(signal.aborted).toBe(true);
  });

  it('createNewController replaces the aborted signal with a fresh one', () => {
    const firstSignal = api.getSignal();
    api.cancelAllRequests();
    expect(firstSignal.aborted).toBe(true);

    api.createNewController();
    const secondSignal = api.getSignal();

    expect(secondSignal).not.toBe(firstSignal);
    expect(secondSignal.aborted).toBe(false);
  });

  it('shares one controller across composable instances (module-level singleton)', () => {
    const other = useGlobalAbortController();

    api.cancelAllRequests();

    // both instances observe the same aborted controller
    expect(other.getSignal().aborted).toBe(true);
  });
});
