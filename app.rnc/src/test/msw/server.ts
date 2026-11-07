import { setupServer } from 'msw/node';
import { handlers } from './handlers';

// One server instance shared across all integration specs via setup.ts.
export const server = setupServer(...handlers);
