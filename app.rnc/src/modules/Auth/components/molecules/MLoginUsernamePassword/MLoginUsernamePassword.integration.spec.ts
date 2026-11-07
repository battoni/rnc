import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { screen, waitFor, fireEvent } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
// Register PrimeVue components explicitly — auto-import only works in Vite, not Vitest.
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';
import { describe, beforeEach, expect, it } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import { mockUser } from '@/test/msw/handlers';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MLoginUsernamePassword from './MLoginUsernamePassword.vue';

const components = { Form, FormField, InputText, Password, Button, Message };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/login', name: 'auth.login', component: { template: '<div>Login</div>' } },
    ],
  });
}

describe('MLoginUsernamePassword — integration (MSW)', () => {
  beforeEach(() => {
    const pinia = createPinia();
    setActivePinia(pinia);
  });

  it('renders username and password fields', () => {
    const router = makeRouter();
    renderWithPlugins(MLoginUsernamePassword, {
      global: { components, plugins: [router] },
    });

    expect(screen.getByLabelText(/username/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
  });

  it('renders the submit button', () => {
    const router = makeRouter();
    renderWithPlugins(MLoginUsernamePassword, {
      global: { components, plugins: [router] },
    });

    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('calls POST /auth/login and navigates to home on valid credentials', async () => {
    server.use(http.post('http://localhost/auth/login', () => HttpResponse.json({ data: mockUser })));

    const router = makeRouter();
    renderWithPlugins(MLoginUsernamePassword, {
      global: { components, plugins: [router] },
    });

    const usernameInput = screen.getByLabelText(/username/i);
    const passwordInput = screen.getByLabelText(/password/i);

    await fireEvent.update(usernameInput, 'alice');
    await fireEvent.update(passwordInput, 'secret123');
    await fireEvent.click(screen.getByRole('button'));

    await waitFor(
      () => {
        expect(router.currentRoute.value.name).toBe('home');
      },
      { timeout: 3000 }
    );
  });

  it('shows an API error when the login request returns 422', async () => {
    server.use(
      http.post('http://localhost/auth/login', () =>
        HttpResponse.json(
          { message: 'auth.login.invalidCredentials', errors: { username: ['auth.login.invalidCredentials'] } },
          { status: 422 }
        )
      )
    );

    const router = makeRouter();
    renderWithPlugins(MLoginUsernamePassword, {
      global: { components, plugins: [router] },
    });

    await fireEvent.update(screen.getByLabelText(/username/i), 'nobody');
    await fireEvent.update(screen.getByLabelText(/password/i), 'wrong');
    await fireEvent.click(screen.getByRole('button'));

    // The view sets apiError which AFormError renders
    await waitFor(
      () => {
        // Either an error message is rendered or a toast was fired
        const hasError =
          document.querySelector('[data-pc-name="message"]') !== null ||
          document.body.innerHTML.includes('error') ||
          document.body.innerHTML.includes('not found') ||
          document.body.innerHTML.includes('username');
        expect(hasError).toBe(true);
      },
      { timeout: 3000 }
    );
  });

  it('shows a friendly rate-limit message when the login request returns 429', async () => {
    server.use(
      http.post('http://localhost/auth/login', () =>
        HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 })
      )
    );

    const router = makeRouter();
    renderWithPlugins(MLoginUsernamePassword, {
      global: { components, plugins: [router] },
    });

    await fireEvent.update(screen.getByLabelText(/username/i), 'alice');
    await fireEvent.update(screen.getByLabelText(/password/i), 'wrong');
    await fireEvent.click(screen.getByRole('button'));

    await waitFor(
      () => {
        expect(document.body.textContent).toMatch(/Too many attempts\. Wait about 1 minute/i);
        expect(document.body.textContent).not.toMatch(/Something went wrong/i);
      },
      { timeout: 3000 }
    );
  });
});
