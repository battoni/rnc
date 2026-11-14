import type { WorkspaceKey } from './types';

interface Workspace {
  icon: string;
  key: WorkspaceKey;
  permission: string;
  /** The role it also asks, where the key alone is not its audience's (the operator's, Chrome QA 5.2). */
  role?: string;
  /** The base route name; the localized records are `<routeName>-en` / `<routeName>-pt-BR`. */
  routeName: string;
}

export type { Workspace };
