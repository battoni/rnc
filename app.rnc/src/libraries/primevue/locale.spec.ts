import { describe, expect, it } from 'vitest';
import buildPrimeVueLocale, { applyPrimeVueLocale } from './locale';

describe('buildPrimeVueLocale', () => {
  // The bug this exists for: a pt-BR app whose DatePicker read "August 2026 / Su Mo Tu".
  it('names the months and days in Portuguese for pt-BR', () => {
    const LOCALE = buildPrimeVueLocale('pt-BR');

    expect(LOCALE.monthNames[7]).toBe('Agosto');
    expect(LOCALE.dayNames[0]).toBe('Domingo');
    expect(LOCALE.dayNamesShort[1]).toBe('Seg.');
  });

  it('still speaks English for en', () => {
    const LOCALE = buildPrimeVueLocale('en');

    expect(LOCALE.monthNames[7]).toBe('August');
    expect(LOCALE.dayNames[0]).toBe('Sunday');
  });

  // PrimeVue indexes both arrays from Sunday; an off-by-one here mislabels every column.
  it('starts the arrays on Sunday and runs to Saturday', () => {
    expect(buildPrimeVueLocale('en').dayNames).toEqual([
      'Sunday',
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
    ]);
  });

  it('gives twelve months, January first', () => {
    const LOCALE = buildPrimeVueLocale('pt-BR');

    expect(LOCALE.monthNames).toHaveLength(12);
    expect(LOCALE.monthNames[0]).toBe('Janeiro');
    expect(LOCALE.monthNames[11]).toBe('Dezembro');
  });

  // Intl's `narrow` gives pt-BR "D S T Q Q S S", where four of the seven collide.
  it('gives seven distinct, period-free column headings', () => {
    const PT_BR = buildPrimeVueLocale('pt-BR').dayNamesMin;

    expect(new Set(PT_BR).size).toBe(7);
    expect(PT_BR).toEqual(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']);
    expect(buildPrimeVueLocale('en').dayNamesMin).toEqual(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
  });

  it('translates the button bar', () => {
    expect(buildPrimeVueLocale('pt-BR').today).toBe('Hoje');
    expect(buildPrimeVueLocale('pt-BR').clear).toBe('Limpar');
  });

  it('reads a regional tag as its language', () => {
    expect(buildPrimeVueLocale('pt-PT').today).toBe('Hoje');
  });

  it('falls back to English words for a language it does not carry', () => {
    const LOCALE = buildPrimeVueLocale('ja-JP');

    expect(LOCALE.today).toBe('Today');
    expect(LOCALE.monthNames).toHaveLength(12);
  });

  // A week's first day is a calendar decision, not a translation: this builder does not make it.
  it('leaves the first day of the week to PrimeVue', () => {
    expect(buildPrimeVueLocale('pt-BR')).not.toHaveProperty('firstDayOfWeek');
  });

  // VIS-14: the paginator read "First Page / Next Page" and the dialog's X read "Close" in pt-BR.
  it('labels the paginator, dialog and calendar controls in Portuguese', () => {
    const { aria, nextMonth, prevMonth } = buildPrimeVueLocale('pt-BR');

    expect(aria.firstPageLabel).toBe('Primeira página');
    expect(aria.prevPageLabel).toBe('Página anterior');
    expect(aria.nextPageLabel).toBe('Próxima página');
    expect(aria.lastPageLabel).toBe('Última página');
    expect(aria.pageLabel).toBe('Página {page}');
    expect(aria.close).toBe('Fechar');
    expect(prevMonth).toBe('Mês anterior');
    expect(nextMonth).toBe('Próximo mês');
  });

  // A language switch replaces what this builder owns and keeps every other PrimeVue default.
  it('applies a language over a live config without dropping the keys it does not carry', () => {
    const CONFIG_LOCALE = {
      accept: 'Yes',
      aria: { close: 'Close', firstPageLabel: 'First Page', star: '1 star' },
      monthNames: ['January'],
    };

    applyPrimeVueLocale(CONFIG_LOCALE, 'pt-BR');

    expect(CONFIG_LOCALE.monthNames[7]).toBe('Agosto');
    expect(CONFIG_LOCALE.aria.close).toBe('Fechar');
    expect(CONFIG_LOCALE.aria.firstPageLabel).toBe('Primeira página');
    expect(CONFIG_LOCALE.aria.star).toBe('1 star');
    expect(CONFIG_LOCALE.accept).toBe('Yes');
  });
});
