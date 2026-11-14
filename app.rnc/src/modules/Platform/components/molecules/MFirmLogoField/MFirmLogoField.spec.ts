import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { ALVORADA_FIXTURE } from '../../../test/fixtures';
import MFirmLogoField from './MFirmLogoField.vue';

/*
 * rnc-4 A43 (R27): a firm's logo from the console. The field refuses what the API would — anything
 * not PNG, JPEG or WebP (an SVG first of all) and anything over 512 KB — BEFORE sending, previews the
 * picked image, sends it on "Enviar logo" and hands the firm the API answers back; the API's own
 * refusals (sides over 1024 px, a disguised file) are named by code. "Remover" takes the logo away.
 */
const LOGO_URL = 'http://localhost:8000/storage/firms/3/logo-abc.png';

// The multipart body as sent: jsdom's File does not survive undici's `formData()` parser, and reaches it
// renamed `blob` — the field's name and the type are what the test can read.
let uploads: string[] = [];
let removals = 0;

function renderField(firm = ALVORADA_FIXTURE) {
  return renderWithPlugins(MFirmLogoField, {
    props: { firm: structuredClone(firm) },
    global: { components: { Button } },
  });
}

function pick(file: File) {
  return fireEvent
    .update(screen.getByTestId('firm-logo-input'), '')
    .then(() => fireEvent.change(screen.getByTestId('firm-logo-input'), { target: { files: [file] } }));
}

function fileOf(name: string, type: string, bytes = 1024) {
  return new File([new Uint8Array(bytes)], name, { type });
}

beforeEach(() => {
  uploads = [];
  removals = 0;
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:preview'), revokeObjectURL: vi.fn() }));
  server.use(
    http.post('http://localhost/platform/firms/3/logo', async ({ request }) => {
      uploads.push(await request.text());

      return HttpResponse.json({ data: { ...ALVORADA_FIXTURE, logo_url: LOGO_URL } });
    }),
    http.delete('http://localhost/platform/firms/3/logo', () => {
      removals += 1;

      return HttpResponse.json({ data: { ...ALVORADA_FIXTURE, logo_url: null } });
    })
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('MFirmLogoField', () => {
  it('says the firm has no logo yet, and asks only for PNG, JPEG or WebP', () => {
    renderField();

    expect(screen.getByTestId('firm-logo-empty')).toBeTruthy();
    expect(screen.getByTestId('firm-logo-input').getAttribute('accept')).toBe('image/png,image/jpeg,image/webp');
  });

  it('refuses an SVG before sending anything', async () => {
    renderField();

    await pick(fileOf('logo.svg', 'image/svg+xml'));

    expect(screen.getByTestId('firm-logo-error').textContent).toContain('PNG');
    expect(screen.queryByTestId('firm-logo-upload')).toBeNull();
    expect(screen.queryByTestId('firm-logo-preview')).toBeNull();
    expect(uploads).toHaveLength(0);
  });

  it('refuses an image over 512 KB before sending anything', async () => {
    renderField();

    await pick(fileOf('logo.png', 'image/png', 513 * 1024));

    expect(screen.getByTestId('firm-logo-error').textContent).toContain('512');
    expect(uploads).toHaveLength(0);
  });

  it('previews a PNG, sends it as `logo` and hands back the firm the API answers', async () => {
    const { emitted } = renderField();

    await pick(fileOf('logo.png', 'image/png'));

    expect(screen.getByTestId('firm-logo-preview').getAttribute('src')).toBe('blob:preview');

    await fireEvent.click(screen.getByTestId('firm-logo-upload'));

    await waitFor(() => expect(emitted('onChange')).toHaveLength(1));
    expect(uploads[0]).toContain('name="logo"');
    expect(uploads[0]).toContain('Content-Type: image/png');
    expect((emitted('onChange')[0] as [{ logo_url: string }])[0].logo_url).toBe(LOGO_URL);
  });

  it("names the API's refusal by its code", async () => {
    server.use(
      http.post('http://localhost/platform/firms/3/logo', () =>
        HttpResponse.json({ errors: { logo: ['platform.logoTooWide'] }, message: 'x' }, { status: 422 })
      )
    );

    const { emitted } = renderField();

    await pick(fileOf('logo.webp', 'image/webp'));
    await fireEvent.click(screen.getByTestId('firm-logo-upload'));

    await waitFor(() => expect(screen.getByTestId('firm-logo-error').textContent).toContain('1024'));
    expect(emitted('onChange')).toBeUndefined();
  });

  it('shows the current logo on this origin and removes it', async () => {
    const { emitted } = renderField({ ...ALVORADA_FIXTURE, logo_url: LOGO_URL });

    expect(screen.getByTestId('firm-logo-current').getAttribute('src')).toBe('/storage/firms/3/logo-abc.png');

    await fireEvent.click(screen.getByTestId('firm-logo-remove'));

    await waitFor(() => expect(emitted('onChange')).toHaveLength(1));
    expect(removals).toBe(1);
  });
});
