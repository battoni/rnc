import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vitest';
import MFunnelTable from './MFunnelTable.vue';

/*
 * A split of the funnel as a table (rnc-1 R24; rnc-2 F5 adds "Por canal", A12): named by its caption,
 * one header per column, each row named by its first cell and keyed for the spec.
 */
function renderTable() {
  return render(MFunnelTable, {
    props: {
      caption: 'Por canal',
      headers: ['Canal', 'Início', 'Contatos', 'Resultado', 'Agendou', 'Clientes', 'Início → agendou'],
      rows: [
        { cells: ['35', '21', '17', '8', '2', '22,9%'], key: 'formulario', label: 'Formulário' },
        { cells: ['5', '4', '3', '2', '1', '40,0%'], key: 'chat', label: 'Chat' },
      ],
      testId: 'channel-row',
    },
  });
}

describe('MFunnelTable', () => {
  it('names the table by its caption and gives every column a header', () => {
    renderTable();

    const table = screen.getByRole('table', { name: 'Por canal' });

    expect(
      within(table)
        .getAllByRole('columnheader')
        .map((header) => header.textContent?.trim())
    ).toEqual(['Canal', 'Início', 'Contatos', 'Resultado', 'Agendou', 'Clientes', 'Início → agendou']);
  });

  it('puts the chat walk in its own row, under its own name (A12)', () => {
    renderTable();

    const rows = screen.getAllByTestId('channel-row');

    expect(rows.map((row) => row.getAttribute('data-key'))).toEqual(['formulario', 'chat']);
    expect(within(rows[1]!).getByRole('rowheader').textContent?.trim()).toBe('Chat');
    expect(
      within(rows[1]!)
        .getAllByRole('cell')
        .map((cell) => cell.textContent?.trim())
    ).toEqual(['5', '4', '3', '2', '1', '40,0%']);
  });
});
