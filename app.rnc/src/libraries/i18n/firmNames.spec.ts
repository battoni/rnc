import { afterEach, describe, expect, it } from 'vitest';
import i18n, { setFirmNames } from './index';

/*
 * rnc-4 R14, R15: a string that needs the firm writes `{firmShort}` (its short name) or `{firmName}` (its
 * full name) in the locale file, and the host firm fills it at runtime — no call site passes it. The
 * placeholders become linked messages to `firm.short` / `firm.name`, which `setFirmNames` rewrites,
 * so every rendered string follows the firm the moment it answers.
 */
const { t } = i18n.global;

describe('the firm placeholders', () => {
  afterEach(() => setFirmNames({ name: 'Franco Contabilidade', short: 'Franco' }));

  it('fill {firmShort} and {firmName} with the host firm, in both languages', () => {
    setFirmNames({ name: 'Alvorada Contabilidade', short: 'Alvorada' });

    expect(t('site.landing.proofLabel', {}, { locale: 'pt-BR' })).toBe('Por que a Alvorada');
    expect(t('auth.brand.tagline', {}, { locale: 'pt-BR' })).toContain('O hub da Alvorada Contabilidade');
    expect(t('site.landing.proofLabel', {}, { locale: 'en' })).toBe('Why Alvorada');
  });

  it('keep the punctuation and the other parameters around the name', () => {
    setFirmNames({ name: 'Alvorada Contabilidade', short: 'Alvorada' });

    expect(t('diagnostic.contact.hint', {}, { locale: 'pt-BR' })).toContain('ficam com a Alvorada e não');
    expect(t('partners.routing.defaultMessage', { company: 'Acme' }, { locale: 'pt-BR' })).toBe(
      'A Alvorada indica a Acme. Podem fazer o primeiro contato em até 24 horas úteis?'
    );
    expect(t('partners.routing.defaultMessage', { company: 'Acme' }, { locale: 'en' })).toBe(
      'Alvorada recommends Acme. Could you make the first contact within 24 business hours?'
    );
  });

  it('follow the firm when it changes', () => {
    setFirmNames({ name: 'Alvorada Contabilidade', short: 'Alvorada' });
    setFirmNames({ name: 'Beira Rio Contábil', short: 'Beira Rio' });

    expect(t('site.landing.proofLabel', {}, { locale: 'pt-BR' })).toBe('Por que a Beira Rio');
  });

  // The portal and the repricing letter name the firm the API answered, as a `{firm}` parameter of
  // their own: the host firm's placeholders never take it over.
  it("leave a caller's own {firm} parameter alone", () => {
    setFirmNames({ name: 'Alvorada Contabilidade', short: 'Alvorada' });

    expect(t('portal.referral.viaFirm', { firm: 'Beira Rio Contábil' }, { locale: 'pt-BR' })).toBe(
      'Pela Beira Rio Contábil'
    );
  });
});
