import { describe, expect, it } from 'vitest';
import { FiscalModuleRoutes } from './routes';

/*
 * The fiscal screens (rnc-2 F8, D14): the team's two under Equipe behind `fiscal.read`, Reinaldo's
 * XML import behind `fiscal.import` — each in both languages, so `attachAuthGuard` sends anyone
 * without the key to Home.
 */
describe('FiscalModuleRoutes', () => {
  it('declares readiness, supplier credit and the import in both languages, each behind its key', () => {
    expect(
      FiscalModuleRoutes.map((route) => [route.path, route.name, (route.meta as { permission?: string }).permission])
    ).toEqual([
      ['/team/invoices', 'nfe-readiness-en', 'fiscal.read'],
      ['/equipe/nfe', 'nfe-readiness-pt-BR', 'fiscal.read'],
      ['/team/suppliers', 'supplier-credit-en', 'fiscal.read'],
      ['/equipe/fornecedores', 'supplier-credit-pt-BR', 'fiscal.read'],
      ['/management/invoices', 'nfe-imports-en', 'fiscal.import'],
      ['/gestao/notas', 'nfe-imports-pt-BR', 'fiscal.import'],
    ]);
  });
});
