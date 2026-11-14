import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { ALVORADA_FIXTURE, IPE_FIXTURE } from '../../../test/fixtures';
import MFirmModulesForm from './MFirmModulesForm.vue';

/*
 * rnc-4 A29 (R18, R25): a firm's modules — core always on (shown, not switched), the opt-in ones as
 * switches, the operator-only channel apart, and the dependency named before anything is sent: radar,
 * contracts and fiscal need the portfolio. The save sends the WHOLE list, in the catalogue's order.
 */
const sent: Record<string, unknown>[] = [];

function renderForm(firm = IPE_FIXTURE) {
  return renderWithPlugins(MFirmModulesForm, {
    props: { firm: structuredClone(firm) },
    global: { components: { Button, ToggleSwitch } },
  });
}

function switchOf(key: string): HTMLInputElement {
  return screen.getByTestId(`module-switch-${key}`).querySelector('input') as HTMLInputElement;
}

beforeEach(() => {
  sent.length = 0;
  server.use(
    http.put('http://localhost/platform/firms/:id/modules', async ({ request }) => {
      const body = (await request.json()) as { modules: string[] };

      sent.push(body);

      return HttpResponse.json({ data: { ...IPE_FIXTURE, modules: body.modules } });
    })
  );
});

describe('MFirmModulesForm', () => {
  it("shows core as always on, the firm's opt-in modules switched on, and channel apart", () => {
    renderForm(ALVORADA_FIXTURE);

    expect(screen.getByTestId('modules-core').textContent).toContain('Diagnostic');
    expect(switchOf('portfolio').checked).toBe(true);
    expect(switchOf('briefing').checked).toBe(true);
    expect(switchOf('chat').checked).toBe(false);
    expect(screen.getByTestId('modules-operator-only').contains(screen.getByTestId('module-switch-channel'))).toBe(
      true
    );
  });

  it('names the dependency and sends nothing while radar is on without the portfolio', async () => {
    renderForm();

    await fireEvent.click(switchOf('radar'));

    expect(screen.getByTestId('module-dependency-radar').textContent).toContain('Portfolio');

    await fireEvent.click(screen.getByTestId('modules-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('modules-error').textContent).toContain('Portfolio');

    await fireEvent.click(switchOf('portfolio'));

    expect(screen.queryByTestId('module-dependency-radar')).toBeNull();
  });

  it('saves the whole list in the catalogue order and hands the firm back', async () => {
    const { emitted } = renderForm();

    await fireEvent.click(switchOf('radar'));
    await fireEvent.click(switchOf('portfolio'));
    await fireEvent.click(switchOf('channel'));
    await fireEvent.click(screen.getByTestId('modules-save'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({ modules: ['portfolio', 'radar', 'briefing', 'channel'] });
    await waitFor(() => expect(emitted().onSuccess).toHaveLength(1));
  });

  it("shows the API's dependency refusal on the form", async () => {
    server.use(
      http.put('http://localhost/platform/firms/:id/modules', () =>
        HttpResponse.json(
          { errors: { modules: ['modules.missingDependency'] }, message: 'modules.missingDependency' },
          { status: 422 }
        )
      )
    );
    renderForm();

    await fireEvent.click(switchOf('chat'));
    await fireEvent.click(screen.getByTestId('modules-save'));

    await waitFor(() => expect(screen.getByTestId('modules-error').textContent).toContain('needs'));
  });
});
