import { fireEvent, screen, waitFor } from '@testing-library/vue';
import Button from 'primevue/button';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderWithPlugins } from '@/test/render';
import MOneTimePassword from './MOneTimePassword.vue';

/*
 * rnc-4 R25: the password the API answers once, with a copy button. Verify P2-S6: a browser that refuses the
 * clipboard (no permission, an insecure origin) must say so and ask for a manual copy — the password is shown
 * only this once, and a button that silently did nothing would leave the operator believing it was copied.
 */
function renderPassword(writeText: () => Promise<void>) {
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });

  return renderWithPlugins(MOneTimePassword, { global: { components: { Button } }, props: { password: 'Xy7-secret' } });
}

afterEach(() => {
  Reflect.deleteProperty(navigator, 'clipboard');
});

describe('MOneTimePassword', () => {
  it('copies the password and says so', async () => {
    const writeText = vi.fn(() => Promise.resolve());

    renderPassword(writeText);
    await fireEvent.click(screen.getByTestId('password-copy'));

    expect(writeText).toHaveBeenCalledWith('Xy7-secret');
    await waitFor(() => expect(screen.getByTestId('password-copy').textContent).toContain('Copied'));
    expect(screen.queryByTestId('password-copy-failed')).toBeNull();
  });

  it('asks for a manual copy when the browser refuses the clipboard', async () => {
    renderPassword(() => Promise.reject(new DOMException('denied', 'NotAllowedError')));

    await fireEvent.click(screen.getByTestId('password-copy'));

    const message = await screen.findByTestId('password-copy-failed');

    expect(message.getAttribute('role')).toBe('alert');
    expect(message.textContent).toContain('copy it by hand');
    expect(screen.getByTestId('password-copy').textContent).not.toContain('Copied');
  });
});
