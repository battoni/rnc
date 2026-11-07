import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import type { RegisterUserPayload } from '../interfaces';
import { server } from '@/test/msw/server';
import registerUserService from './registerUser.service';
import requestEmailOtpService from './requestEmailOtp.service';
import verifyEmailService from './verifyEmail.service';

/*
 * The CONTRACT of the sign-up services, checked on the wire.
 *
 * `MSignUpUser.spec.ts` mocks `RegisterUserService`, which proves the form calls the function and
 * nothing about what leaves the browser — and what leaves is exactly where a rename breaks. The
 * payload mirrors `RegisterRequest` key for key: camelCase where the FormRequest reads camelCase,
 * and `password_confirmation` in snake_case because Laravel's `confirmed` rule reads that name.
 */
const API = 'http://localhost';

interface CapturedRequest {
  body: unknown;
  method: string;
  path: string;
}

function captureNext(path: string, response: unknown): CapturedRequest {
  const CAPTURED: CapturedRequest = { body: undefined, method: '', path: '' };

  server.use(
    http.all(`${API}/${path}`, async ({ request }) => {
      CAPTURED.body = await request.json();
      CAPTURED.method = request.method;
      CAPTURED.path = new URL(request.url).pathname;

      return HttpResponse.json({ data: response });
    })
  );

  return CAPTURED;
}

describe('sign-up services — contract (MSW)', () => {
  it('RegisterUserService posts the form to auth/register with the keys RegisterRequest validates', async () => {
    const PAYLOAD: RegisterUserPayload = {
      birthDate: '1990-05-17',
      cpf: '52998224725',
      email: 'ana@example.com',
      firstName: 'Ana',
      lastName: 'Souza',
      password: 'secret-123',
      password_confirmation: 'secret-123',
      phone: '11999998888',
      termsAccepted: true,
    };
    const CAPTURED = captureNext('auth/register', { email: 'ana@example.com', name: 'Ana' });

    const { data } = await registerUserService(PAYLOAD);

    expect(CAPTURED.method).toBe('POST');
    expect(CAPTURED.path).toBe('/auth/register');
    expect(CAPTURED.body).toEqual({
      birthDate: '1990-05-17',
      cpf: '52998224725',
      email: 'ana@example.com',
      firstName: 'Ana',
      lastName: 'Souza',
      password: 'secret-123',
      password_confirmation: 'secret-123',
      phone: '11999998888',
      termsAccepted: true,
    });
    expect(data).toEqual({ email: 'ana@example.com', name: 'Ana' });
  });

  it('RequestEmailOtpService posts the address to auth/email/code', async () => {
    const CAPTURED = captureNext('auth/email/code', { sent: true });

    const { data } = await requestEmailOtpService({ email: 'ana@example.com' });

    expect(CAPTURED.method).toBe('POST');
    expect(CAPTURED.path).toBe('/auth/email/code');
    expect(CAPTURED.body).toEqual({ email: 'ana@example.com' });
    expect(data).toEqual({ sent: true });
  });

  it('VerifyEmailService posts the address and the six digits to auth/email/verify', async () => {
    const CAPTURED = captureNext('auth/email/verify', { id: 7, name: 'Ana' });

    const { data } = await verifyEmailService({ code: '123456', email: 'ana@example.com' });

    expect(CAPTURED.method).toBe('POST');
    expect(CAPTURED.path).toBe('/auth/email/verify');
    expect(CAPTURED.body).toEqual({ code: '123456', email: 'ana@example.com' });
    expect(data).toEqual({ id: 7, name: 'Ana' });
  });
});
