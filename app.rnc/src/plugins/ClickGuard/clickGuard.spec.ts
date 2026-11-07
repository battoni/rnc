import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { resetAppBusyState, useAppBusy } from '@Composables';
import { GUARD_WINDOW_MS, LOCK_ATTRIBUTE, MAX_HOLD_MS, OPT_OUT_ATTRIBUTE } from './clickGuard';
import { startClickGuard, stopClickGuard } from './plugin';

// HELPERS

function mountButton(container: HTMLElement = document.body) {
  const button = document.createElement('button');

  container.appendChild(button);

  return button;
}

describe('click guard', () => {
  beforeEach(() => {
    // Only the timers the guard itself arms — `queueMicrotask` must stay real, it is how the
    // guard reads the busy signal after the click handler has run.
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    resetAppBusyState();
    document.body.innerHTML = '';
    startClickGuard();
  });

  afterEach(() => {
    stopClickGuard();
    vi.useRealTimers();
  });

  it('fires the handler once for two rapid clicks on the same button', () => {
    const onClick = vi.fn();
    const button = mountButton();

    button.addEventListener('click', onClick);

    button.click();
    button.click();

    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('fires again once the guard window has passed', () => {
    const onClick = vi.fn();
    const button = mountButton();

    button.addEventListener('click', onClick);

    button.click();
    vi.advanceTimersByTime(GUARD_WINDOW_MS + 1);
    button.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('marks a locked button and clears the mark when the window passes', () => {
    const button = mountButton();

    button.click();

    expect(button.getAttribute(LOCK_ATTRIBUTE)).toBe('locked');

    vi.advanceTimersByTime(GUARD_WINDOW_MS + 1);

    expect(button.hasAttribute(LOCK_ATTRIBUTE)).toBe(false);
  });

  it('guards each button on its own — two different buttons both fire', () => {
    const onClick = vi.fn();
    const first = mountButton();
    const second = mountButton();

    first.addEventListener('click', onClick);
    second.addEventListener('click', onClick);

    first.click();
    second.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('keeps a swallowed click away from ancestors too', () => {
    const onAncestorClick = vi.fn();
    const wrapper = document.createElement('div');

    document.body.appendChild(wrapper);
    wrapper.addEventListener('click', onAncestorClick);

    const button = mountButton(wrapper);

    button.click();
    button.click();

    expect(onAncestorClick).toHaveBeenCalledTimes(1);
  });

  it('lets a button opted out with the escape hatch be clicked as fast as it likes', () => {
    const onClick = vi.fn();
    const button = mountButton();

    button.setAttribute(OPT_OUT_ATTRIBUTE, '');
    button.addEventListener('click', onClick);

    button.click();
    button.click();
    button.click();

    expect(onClick).toHaveBeenCalledTimes(3);
  });

  it('honours the escape hatch on an ancestor', () => {
    const onClick = vi.fn();
    const wrapper = document.createElement('div');

    wrapper.setAttribute(OPT_OUT_ATTRIBUTE, '');
    document.body.appendChild(wrapper);

    const button = mountButton(wrapper);

    button.addEventListener('click', onClick);

    button.click();
    button.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('leaves a PrimeVue stepper section alone', () => {
    const onClick = vi.fn();
    const button = mountButton();

    button.setAttribute('data-pc-section', 'incrementbutton');
    button.addEventListener('click', onClick);

    button.click();
    button.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('ignores clicks that land on something that is not a button', () => {
    const onClick = vi.fn();
    const link = document.createElement('a');

    document.body.appendChild(link);
    link.addEventListener('click', onClick);

    link.click();
    link.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('holds the lock past the window while the click keeps the app busy', async () => {
    const { beginRequest, endRequest } = useAppBusy();
    const onClick = vi.fn(() => beginRequest());
    const button = mountButton();

    button.addEventListener('click', onClick);

    button.click();
    await nextTick();

    vi.advanceTimersByTime(GUARD_WINDOW_MS + 1);
    button.click();

    expect(onClick).toHaveBeenCalledTimes(1);

    endRequest();
    await nextTick();

    button.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('never strands a button: the hold expires even if the work never settles', async () => {
    const { beginRequest } = useAppBusy();
    const onClick = vi.fn(() => beginRequest());
    const button = mountButton();

    button.addEventListener('click', onClick);

    button.click();
    await nextTick();

    vi.advanceTimersByTime(MAX_HOLD_MS + 1);
    button.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('does not extend the lock when the app was already busy before the click', async () => {
    const { beginRequest } = useAppBusy();

    beginRequest();

    const onClick = vi.fn();
    const button = mountButton();

    button.addEventListener('click', onClick);

    button.click();
    await nextTick();

    vi.advanceTimersByTime(GUARD_WINDOW_MS + 1);
    button.click();

    expect(onClick).toHaveBeenCalledTimes(2);
  });
});
