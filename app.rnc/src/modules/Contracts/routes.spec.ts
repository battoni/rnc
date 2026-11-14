import { describe, expect, it } from 'vitest';
import { ContractsModuleRoutes } from './routes';

/*
 * "Alerta de contratos" (rnc-3 F7, R18): Reinaldo's screen behind `contracts.manage`, in both
 * languages, so `attachAuthGuard` sends anyone without the key (the team) to Home.
 */
describe('ContractsModuleRoutes', () => {
  it('declares the contract alert in both languages behind `contracts.manage`', () => {
    expect(
      ContractsModuleRoutes.map((route) => [route.path, route.name, (route.meta as { permission?: string }).permission])
    ).toEqual([
      ['/management/contracts', 'contract-alert-en', 'contracts.manage'],
      ['/gestao/contratos', 'contract-alert-pt-BR', 'contracts.manage'],
    ]);
  });
});
