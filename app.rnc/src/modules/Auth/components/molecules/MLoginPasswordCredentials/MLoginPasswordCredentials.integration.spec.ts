import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { screen, waitFor, fireEvent } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputMask from 'primevue/inputmask';
import Message from 'primevue/message';
import Password from 'primevue/password';
import SelectButton from 'primevue/selectbutton';
import { describe, beforeEach, expect, it } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import { mockUser } from '@/test/msw/handlers';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MLoginPasswordCredentials from './MLoginPasswordCredentials.vue';

const components = { Form, FormField, InputMask, Password, Button, Message, SelectButton, Divider };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/login', name: 'auth.login', component: { template: '<div>Login</div>' } },
      { path: '/entrar-email', name: 'auth.entrarEmail', component: { template: '<div>Email</div>' } },
    ],
  });
}

describe('MLoginPasswordCredentials — integration (MSW)', () => {
  beforeEach(() => {
    const pinia = createPinia();
    setActivePinia(pinia);
  });

  it('calls POST /auth/login and navigates to home on valid credentials', async () => {
    server.use(http.post('http://localhost/auth/login', () => HttpResponse.json({ data: mockUser })));

    const router = makeRouter();
    renderWithPlugins(MLoginPasswordCredentials, {
      global: { components, plugins: [router] },
    });

    await fireEvent.update(screen.getByLabelText(/mobile number or email/i), '11999999999');
    await fireEvent.update(screen.getByPlaceholderText(/enter your password/i), 'secret123');
    await fireEvent.click(screen.getByRole('button', { name: /^continue$/i }));

    await waitFor(
      () => {
        expect(router.currentRoute.value.name).toBe('home');
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
    renderWithPlugins(MLoginPasswordCredentials, {
      global: { components, plugins: [router] },
    });

    await fireEvent.update(screen.getByLabelText(/mobile number or email/i), '11999999999');
    await fireEvent.update(screen.getByPlaceholderText(/enter your password/i), 'wrong');
    await fireEvent.click(screen.getByRole('button', { name: /^continue$/i }));

    await waitFor(
      () => {
        expect(document.body.textContent).toMatch(/Too many attempts\. Wait about 1 minute/i);
        expect(document.body.textContent).not.toMatch(/Something went wrong/i);
      },
      { timeout: 3000 }
    );
  });
});
