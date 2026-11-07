import { beforeEach, describe, expect, it } from 'vitest';
import { resetAppBusyState, useAppBusy } from './useAppBusy';

describe('useAppBusy', () => {
  beforeEach(() => resetAppBusyState());

  it('is idle before anything begins', () => {
    const { isBusy } = useAppBusy();

    expect(isBusy.value).toBe(false);
  });

  it('is busy while a request is in flight and idle once it ends', () => {
    const { beginRequest, endRequest, isBusy } = useAppBusy();

    beginRequest();

    expect(isBusy.value).toBe(true);

    endRequest();

    expect(isBusy.value).toBe(false);
  });

  it('stays busy until the last of several overlapping requests ends', () => {
    const { beginRequest, endRequest, isBusy } = useAppBusy();

    beginRequest();
    beginRequest();
    endRequest();

    expect(isBusy.value).toBe(true);

    endRequest();

    expect(isBusy.value).toBe(false);
  });

  it('never goes negative when an end arrives without a begin', () => {
    const { beginRequest, endRequest, isBusy } = useAppBusy();

    endRequest();
    beginRequest();

    expect(isBusy.value).toBe(true);
  });

  it('is busy while a navigation is pending', () => {
    const { isBusy, setNavigating } = useAppBusy();

    setNavigating(true);

    expect(isBusy.value).toBe(true);

    setNavigating(false);

    expect(isBusy.value).toBe(false);
  });

  it('absorbs a redirect raising the navigation flag twice — one afterEach still settles it', () => {
    const { isBusy, setNavigating } = useAppBusy();

    setNavigating(true);
    setNavigating(true);
    setNavigating(false);

    expect(isBusy.value).toBe(false);
  });

  it('stays busy through a navigation that also fires requests', () => {
    const { beginRequest, endRequest, isBusy, setNavigating } = useAppBusy();

    setNavigating(true);
    beginRequest();
    setNavigating(false);

    expect(isBusy.value).toBe(true);

    endRequest();

    expect(isBusy.value).toBe(false);
  });
});
