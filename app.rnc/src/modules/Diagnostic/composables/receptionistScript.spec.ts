import { describe, expect, it } from 'vitest';
import type { ChatContext } from '../interfaces';
import { UI_LOCALES } from '@Constants';
import i18n from '@Libraries/i18n';
import { CHAT_TURNS } from '../constants';
import { CHAT_TURN, LOOKUP_STATUS } from '../enums';
import { COMPANY_FIXTURE, CONSENT_TEXT } from '../test/fixtures';
import { ReceptionistScript } from './receptionistScript';

/*
 * The chat receptionist's script (rnc-2 F5, R12–R13, A11): pure — a turn and what the visitor typed
 * in, the option it chose (or the free text it gave) out. Accents and case never matter; text that
 * matches nothing is "Não entendi…" and the turn stays; the script never answers anything itself.
 */
const NOT_UNDERSTOOD = 'diagnostic.chat.notUnderstood';

const CONTEXT: ChatContext = {
  company: null,
  consentText: '',
  labels: (key, params) => UI_LOCALES.map((locale) => i18n.global.t(key, params ?? {}, { locale: locale.value })),
  lookupStatus: LOOKUP_STATUS.IDLE,
  translate: (key, params) => i18n.global.t(key, params ?? {}),
};

function read(turn: CHAT_TURN, text: string, context: Partial<ChatContext> = {}) {
  return ReceptionistScript.matchAnswer(turn, text, { ...CONTEXT, ...context });
}

describe('ReceptionistScript — the turns (R12)', () => {
  it("asks the demo's 13 turns in order", () => {
    expect(CHAT_TURNS.map((entry) => entry.turn)).toEqual([
      CHAT_TURN.PATH,
      CHAT_TURN.CNPJ,
      CHAT_TURN.LOOKUP,
      CHAT_TURN.SECTOR,
      CHAT_TURN.REGIME,
      CHAT_TURN.COMPANY,
      CHAT_TURN.NAME,
      CHAT_TURN.CONTACT,
      CHAT_TURN.CONSENT,
      CHAT_TURN.B2B,
      CHAT_TURN.BAND,
      CHAT_TURN.INCENTIVOS,
      CHAT_TURN.EXPORTA,
    ]);
  });

  it('offers every option of the form as a quick reply', () => {
    const sector = ReceptionistScript.promptOf(CHAT_TURN.SECTOR, CONTEXT);
    const b2b = ReceptionistScript.promptOf(CHAT_TURN.B2B, CONTEXT);

    expect(sector.replies.map((reply) => reply.value)).toEqual([
      'comercio',
      'industria',
      'servicos',
      'saude',
      'educacao',
      'agro',
    ]);
    expect(b2b.replies).toHaveLength(11);
    expect(sector.isFreeText).toBe(false);
  });

  it("gives every turn a box, and takes the visitor's own words only where the turn asks for them", () => {
    const turns = CHAT_TURNS.map((entry) => entry.turn).filter((turn) => turn !== CHAT_TURN.LOOKUP);
    const freeText = turns.filter((turn) => ReceptionistScript.promptOf(turn, CONTEXT).isFreeText);

    expect(turns.every((turn) => ReceptionistScript.promptOf(turn, CONTEXT).input !== null)).toBe(true);
    expect(freeText).toEqual([CHAT_TURN.CNPJ, CHAT_TURN.COMPANY, CHAT_TURN.NAME, CHAT_TURN.CONTACT]);
    expect(ReceptionistScript.promptOf(CHAT_TURN.B2B, CONTEXT).input?.inputmode).toBe('numeric');
  });

  it('quotes the consent the API stores, word for word', () => {
    const prompt = ReceptionistScript.promptOf(CHAT_TURN.CONSENT, { ...CONTEXT, consentText: CONSENT_TEXT });

    expect(prompt.askParams).toEqual({ text: CONSENT_TEXT });
  });

  it('waits on the lookup while it runs, then asks to confirm the company it found', () => {
    const running = ReceptionistScript.promptOf(CHAT_TURN.LOOKUP, { ...CONTEXT, lookupStatus: LOOKUP_STATUS.RUNNING });
    const found = ReceptionistScript.promptOf(CHAT_TURN.LOOKUP, {
      ...CONTEXT,
      company: COMPANY_FIXTURE,
      lookupStatus: LOOKUP_STATUS.FOUND,
    });
    const unavailable = ReceptionistScript.promptOf(CHAT_TURN.LOOKUP, {
      ...CONTEXT,
      lookupStatus: LOOKUP_STATUS.UNAVAILABLE,
    });

    expect(running.busy).toBe(true);
    expect(running.replies).toEqual([]);
    expect(running.input).toBeNull();
    expect(found.askParams.name).toBe(COMPANY_FIXTURE.name);
    expect(found.replies.map((reply) => reply.value)).toEqual(['sim', 'cnpj', 'quiz']);
    expect(unavailable.replies.map((reply) => reply.value)).toEqual(['quiz', 'cnpj']);
  });

  it('offers the company the lookup found as the answer to "qual o nome da empresa"', () => {
    const prompt = ReceptionistScript.promptOf(CHAT_TURN.COMPANY, { ...CONTEXT, company: COMPANY_FIXTURE });

    expect(prompt.replies).toEqual([{ text: COMPANY_FIXTURE.name, value: COMPANY_FIXTURE.name }]);
  });
});

describe('ReceptionistScript — typed text at an option turn (R13, A11)', () => {
  it.each([
    ['saude', 'saude'],
    ['Saúde', 'saude'],
    ['SAÚDE', 'saude'],
    ['é uma clínica, da área de saúde', 'saude'],
    ['Serviços', 'servicos'],
    ['industria', 'industria'],
  ])('reads "%s" at the sector turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.SECTOR, typed)).toEqual({ errorKey: null, value });
  });

  it.each(['não sei de nada', 'quanto vou pagar de IBS?', 'saúde ou educação', '', '   '])(
    'answers "Não entendi…" to "%s" at the sector turn, and chooses nothing',
    (typed) => {
      expect(read(CHAT_TURN.SECTOR, typed)).toEqual({ errorKey: NOT_UNDERSTOOD, value: null });
    }
  );

  it.each([
    ['simples', 'simples'],
    ['Simples Nacional', 'simples'],
    ['lucro presumido', 'presumido'],
    ['Real', 'real'],
    ['não sei', 'nao_sei'],
  ])('reads "%s" at the regime turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.REGIME, typed).value).toBe(value);
  });

  it.each([
    ['tenho', 'cnpj'],
    ['Sim', 'cnpj'],
    ['não tenho', 'quiz'],
    ['Não tenho o CNPJ', 'quiz'],
    ['nao', 'quiz'],
  ])('reads "%s" at the path turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.PATH, typed).value).toBe(value);
  });

  it.each([
    ['sim', 'sim'],
    ['SIM', 'sim'],
    ['Não', 'nao'],
    ['nao', 'nao'],
  ])('reads "%s" at a yes/no turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.INCENTIVOS, typed).value).toBe(value);
    expect(read(CHAT_TURN.EXPORTA, typed).value).toBe(value);
  });

  it.each(['talvez', 'sim e não', 'não sei'])('does not guess a yes or a no from "%s"', (typed) => {
    expect(read(CHAT_TURN.EXPORTA, typed).errorKey).toBe(NOT_UNDERSTOOD);
  });

  it.each([
    ['80', '80'],
    ['80%', '80'],
    ['uns 80 por cento', '80'],
    ['0', '0'],
    ['100', '100'],
    ['35', '35'],
  ])('reads the number in "%s" at the B2B turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.B2B, typed).value).toBe(value);
  });

  it.each(['150', 'metade', '-10'])('refuses "%s" as a B2B share', (typed) => {
    expect(read(CHAT_TURN.B2B, typed).errorKey).toBe(NOT_UNDERSTOOD);
  });

  it.each([
    ['Acima de R$ 300 milhões/ano', 'b5'],
    ['acima de 300 milhoes', 'b5'],
    ['até 360 mil', 'b1'],
    ['over 300 million', 'b5'],
  ])('reads "%s" at the band turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.BAND, typed).value).toBe(value);
  });

  it.each([
    ['Autorizo', 'autorizo'],
    ['sim', 'autorizo'],
    ['prefiro não', 'nao_autorizo'],
    ['não', 'nao_autorizo'],
  ])('reads "%s" at the consent turn as %s', (typed, value) => {
    expect(read(CHAT_TURN.CONSENT, typed).value).toBe(value);
  });

  it('reads the lookup confirm by what the screen offers', () => {
    const found = { company: COMPANY_FIXTURE, lookupStatus: LOOKUP_STATUS.FOUND };
    const unavailable = { lookupStatus: LOOKUP_STATUS.UNAVAILABLE };

    expect(read(CHAT_TURN.LOOKUP, 'sim, é ela', found).value).toBe('sim');
    expect(read(CHAT_TURN.LOOKUP, 'não é essa', found).value).toBe('cnpj');
    expect(read(CHAT_TURN.LOOKUP, 'outro cnpj', found).value).toBe('cnpj');
    expect(read(CHAT_TURN.LOOKUP, 'questionário', found).value).toBe('quiz');
    expect(read(CHAT_TURN.LOOKUP, 'questionário', unavailable).value).toBe('quiz');
    expect(read(CHAT_TURN.LOOKUP, 'outro cnpj', unavailable).value).toBe('cnpj');
    expect(read(CHAT_TURN.LOOKUP, 'sim', { lookupStatus: LOOKUP_STATUS.RUNNING }).errorKey).toBe(NOT_UNDERSTOOD);
  });
});

describe('ReceptionistScript — free text (R13, A11)', () => {
  it('takes the company, the name and the CNPJ as typed, trimmed', () => {
    expect(read(CHAT_TURN.COMPANY, '  Clínica Horizonte ').value).toBe('Clínica Horizonte');
    expect(read(CHAT_TURN.NAME, 'Ana Souza').value).toBe('Ana Souza');
    expect(read(CHAT_TURN.CNPJ, '11.222.333/0001-81').value).toBe('11.222.333/0001-81');
  });

  it('takes nothing from an empty box', () => {
    expect(read(CHAT_TURN.COMPANY, '   ').errorKey).toBe(NOT_UNDERSTOOD);
  });

  it.each([
    ['ana@horizonte.com.br', { email: 'ana@horizonte.com.br', phone: null }],
    ['(11) 98888-7777', { email: null, phone: '(11) 98888-7777' }],
    ['11 3333 4444', { email: null, phone: '11 3333 4444' }],
  ])('reads "%s" as one way to reach the visitor', (typed, contact) => {
    expect(ReceptionistScript.readContact(typed)).toEqual(contact);
    expect(read(CHAT_TURN.CONTACT, typed).value).toBe(typed);
  });

  it.each(['ana@', 'ana@horizonte', '1234', '9888-7777', 'me liga'])(
    'gives the demo\'s contact error to "%s" and keeps the turn',
    (typed) => {
      expect(ReceptionistScript.readContact(typed)).toBeNull();
      expect(read(CHAT_TURN.CONTACT, typed)).toEqual({ errorKey: 'diagnostic.chat.contactInvalid', value: null });
    }
  );

  it("says the demo's words for a contact it cannot read", () => {
    expect(i18n.global.t('diagnostic.chat.contactInvalid', {}, { locale: 'pt-BR' })).toBe(
      'Não consegui ler esse contato. Pode mandar um e-mail completo ou um telefone com DDD?'
    );
    expect(i18n.global.t(NOT_UNDERSTOOD, {}, { locale: 'pt-BR' })).toBe('Não entendi. Escolha uma das opções abaixo.');
  });
});
