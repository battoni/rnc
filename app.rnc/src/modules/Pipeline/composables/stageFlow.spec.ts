import { describe, expect, it } from 'vitest';
import { LEAD_STAGE } from '../enums';
import { contactLinks } from './contactLinks';
import { canMarkLost, nextStage, previousStage } from './stageFlow';

/* The ◀ ▶ ✕ of a card (the demo's `nextStage`/`prevStage`) and the contact links of the lead detail (R19). */
describe('stageFlow', () => {
  it('walks the pipeline forward and back', () => {
    expect(nextStage(LEAD_STAGE.VISITANTE)).toBe(LEAD_STAGE.LEAD_PARCIAL);
    expect(nextStage(LEAD_STAGE.PROPOSTA)).toBe(LEAD_STAGE.CLIENTE);
    expect(nextStage(LEAD_STAGE.CLIENTE)).toBeNull();
    expect(previousStage(LEAD_STAGE.LEAD)).toBe(LEAD_STAGE.LEAD_PARCIAL);
    expect(previousStage(LEAD_STAGE.VISITANTE)).toBeNull();
  });

  it('keeps Perdido outside the flow: no ▶, ◀ goes back to Lead', () => {
    expect(nextStage(LEAD_STAGE.PERDIDO)).toBeNull();
    expect(previousStage(LEAD_STAGE.PERDIDO)).toBe(LEAD_STAGE.LEAD);
  });

  it('offers ✕ only while the lead is in play', () => {
    expect(canMarkLost(LEAD_STAGE.LEAD)).toBe(true);
    expect(canMarkLost(LEAD_STAGE.CLIENTE)).toBe(false);
    expect(canMarkLost(LEAD_STAGE.PERDIDO)).toBe(false);
  });
});

describe('contactLinks', () => {
  it('dials and messages the stored phone with the country code, and writes to the e-mail', () => {
    expect(contactLinks('5511900000001', 'ana@exemplo.com.br')).toEqual({
      email: 'mailto:ana@exemplo.com.br',
      phone: 'tel:+5511900000001',
      whatsapp: 'https://wa.me/5511900000001',
    });
  });

  it('adds the country code to a national number and offers nothing without a contact', () => {
    expect(contactLinks('(11) 90000-0002', null).whatsapp).toBe('https://wa.me/5511900000002');
    expect(contactLinks(null, null)).toEqual({ email: null, phone: null, whatsapp: null });
  });
});
