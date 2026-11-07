import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { EXPRESS_FIXTURE } from '../../../test/fixtures';
import MAddEditBrandForm from './MAddEditBrandForm.vue';

/*
 * A6 / R5 (rnc-3 F2): a sub-brand's edit — name, short name, tagline, the two colours with a live
 * note on how white reads on the primary, and the four proof points. The API's refusal of a primary
 * white cannot be read on (`brand.lowContrast`) is shown by its code, beside the field.
 */
const sent: Record<string, unknown>[] = [];

function renderForm() {
  return renderWithPlugins(MAddEditBrandForm, {
    props: { brand: structuredClone(EXPRESS_FIXTURE) },
    global: { components: { Button, InputText } },
  });
}

beforeEach(() => {
  sent.length = 0;
  server.use(
    http.patch('http://localhost/brands/1', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      sent.push(body);

      return HttpResponse.json({ data: { ...EXPRESS_FIXTURE, ...body } });
    })
  );
});

describe('MAddEditBrandForm', () => {
  it("opens with the brand's own words, colours and four proof points", () => {
    renderForm();

    expect((screen.getByTestId('brand-name') as HTMLInputElement).value).toBe('Franco Express Contabilidade');
    expect((screen.getByTestId('brand-short-name') as HTMLInputElement).value).toBe('Franco Express');
    expect((screen.getByTestId('brand-primary') as HTMLInputElement).value).toBe('#3b2a86');
    expect((screen.getByTestId('brand-accent') as HTMLInputElement).value).toBe('#ff8a3d');
    expect(screen.getAllByTestId('brand-proof-value')).toHaveLength(4);
    expect((screen.getAllByTestId('brand-proof-value')[1] as HTMLInputElement).value).toBe('Pequenas');
  });

  it('says, as the primary changes, whether white text reads on it', async () => {
    renderForm();

    const note = () => screen.getByTestId('brand-contrast-note');

    expect(note().dataset.readable).toBe('true');
    expect(note().textContent).toContain('11.3:1');

    await fireEvent.update(screen.getByTestId('brand-primary'), '#ffff00');

    expect(note().dataset.readable).toBe('false');
    expect(note().textContent).toContain('1.1:1');
    expect(note().textContent).toContain('4.5:1');
  });

  it('saves the edit: trimmed words, lower-case colours, the four points', async () => {
    const { emitted } = renderForm();

    await fireEvent.update(screen.getByTestId('brand-tagline'), '  Rápida e sem papel  ');
    await fireEvent.update(screen.getByTestId('brand-primary'), '#2D1F6E');
    await fireEvent.update(screen.getAllByTestId('brand-proof-value')[2]!, 'Digital');
    await fireEvent.click(screen.getByTestId('brand-save'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({
      brand_accent: '#ff8a3d',
      brand_primary: '#2d1f6e',
      name: 'Franco Express Contabilidade',
      proof: [
        { label: 'da Franco em São Paulo', value: '35 anos' },
        { label: 'empresas e Simples Nacional', value: 'Pequenas' },
        { label: 'rotina sem papel', value: 'Digital' },
        { label: 'Rede Nacional de Contabilidade', value: 'RNC' },
      ],
      short_name: 'Franco Express',
      tagline: 'Rápida e sem papel',
    });
    await waitFor(() => expect(emitted().onSuccess).toHaveLength(1));
  });

  it('refuses a colour that is not #rrggbb, or an empty proof point, before asking the API', async () => {
    renderForm();

    await fireEvent.update(screen.getByTestId('brand-accent'), 'laranja');
    await fireEvent.update(screen.getAllByTestId('brand-proof-label')[0]!, ' ');
    await fireEvent.click(screen.getByTestId('brand-save'));

    expect(screen.getByTestId('brand-accent-error').textContent).toContain('#rrggbb');
    expect(screen.getByTestId('brand-proof-error').textContent).toBeTruthy();
    expect(sent).toHaveLength(0);
  });

  it("shows the API's contrast refusal by its code, beside the primary", async () => {
    server.use(
      http.patch('http://localhost/brands/1', () =>
        HttpResponse.json(
          { errors: { brand_primary: ['brand.lowContrast'] }, message: 'brand.lowContrast' },
          { status: 422 }
        )
      )
    );
    const { emitted } = renderForm();

    await fireEvent.click(screen.getByTestId('brand-save'));

    await waitFor(() =>
      expect(screen.getByTestId('brand-primary-error').textContent).toContain('White text does not read on this colour')
    );
    expect(emitted().onSuccess).toBeUndefined();
  });
  it("clears the API's contrast refusal once the primary changes, and keeps the other fields' errors", async () => {
    server.use(
      http.patch('http://localhost/brands/1', () =>
        HttpResponse.json(
          { errors: { brand_primary: ['brand.lowContrast'], tagline: ['brand.lowContrast'] }, message: 'x' },
          { status: 422 }
        )
      )
    );
    renderForm();

    await fireEvent.update(screen.getByTestId('brand-primary'), '#ffff00');
    await fireEvent.click(screen.getByTestId('brand-save'));
    await waitFor(() => expect(screen.getByTestId('brand-primary-error').textContent).toBeTruthy());

    await fireEvent.update(screen.getByTestId('brand-primary'), '#3b2a86');

    await waitFor(() => expect(screen.queryByTestId('brand-primary-error')).toBeNull());
    expect(screen.getByTestId('brand-contrast-note').dataset.readable).toBe('true');
    expect(screen.getAllByText('White text does not read on this colour', { exact: false })).toHaveLength(1);
  });
});
