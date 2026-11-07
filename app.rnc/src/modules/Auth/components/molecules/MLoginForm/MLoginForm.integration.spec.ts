import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { screen, waitFor, fireEvent } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import IftaLabel from 'primevue/iftalabel';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import { describe, beforeEach, expect, it } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import { mockUser } from '@/test/msw/handlers';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MLoginForm from './MLoginForm.vue';

const components = { Form, FormField, IftaLabel, InputText, Password, Button };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/login', name: 'auth.login', component: { template: '<div>Login</div>' } },
    ],
  });
}

describe('MLoginForm — integration (MSW)', () => {
  beforeEach(() => {
    const pinia = createPinia();
    setActivePinia(pinia);
  });

  it('shows a friendly rate-limit message when the login request returns 429', async () => {
    server.use(
      http.post('http://localhost/auth/login', () =>
        HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 })
      )
    );

    const router = makeRouter();
    renderWithPlugins(MLoginForm, {
      global: { components, plugins: [router] },
    });

    await fireEvent.update(screen.getByPlaceholderText(/enter your username/i), 'alice');
    await fireEvent.update(screen.getByPlaceholderText(/enter your password/i), 'wrong');
    await fireEvent.click(screen.getByRole('button', { name: /^login$/i }));

    await waitFor(
      () => {
        expect(document.body.textContent).toMatch(/Too many attempts\. Wait about 1 minute/i);
        expect(document.body.textContent).not.toMatch(/Something went wrong/i);
      },
      { timeout: 3000 }
    );
  });

  it('calls POST /auth/login and navigates to home on valid credentials', async () => {
    server.use(http.post('http://localhost/auth/login', () => HttpResponse.json({ data: mockUser })));

    const router = makeRouter();
    renderWithPlugins(MLoginForm, {
      global: { components, plugins: [router] },
    });

    await fireEvent.update(screen.getByPlaceholderText(/enter your username/i), 'alice');
    await fireEvent.update(screen.getByPlaceholderText(/enter your password/i), 'secret123');
    await fireEvent.click(screen.getByRole('button', { name: /^login$/i }));

    await waitFor(
      () => {
        expect(router.currentRoute.value.name).toBe('home');
      },
      { timeout: 3000 }
    );
  });

  it('points both labels at their inputs and announces them to a password manager', () => {
    renderWithPlugins(MLoginForm, {
      global: { components, plugins: [makeRouter()] },
    });

    // The password label used to point at nothing: Password was given no `inputId`, and a bare
    // `autocomplete` on it lands on the wrapper <div>, where no password manager looks.
    expect(screen.getByLabelText(/^username$/i).getAttribute('autocomplete')).toBe('username');
    expect(screen.getByLabelText(/^password$/i).getAttribute('autocomplete')).toBe('current-password');
  });

  /*
   * The form used to refuse an empty submit in silence: both fields turned red and nothing said
   * what was missing. The rule that allowed that is retired in RULES.md — this keeps it retired.
   */
  it('says what is missing when the form is submitted empty', async () => {
    const router = makeRouter();
    renderWithPlugins(MLoginForm, { global: { components, plugins: [router] } });

    await fireEvent.click(screen.getByRole('button', { name: /^login$/i }));

    await waitFor(() => {
      expect(document.body.textContent).toMatch(/username is required/i);
      expect(document.body.textContent).toMatch(/password is required/i);
    });
  });
});
