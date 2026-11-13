import type { WorkspaceKey } from './types';

interface Workspace {
  icon: string;
  key: WorkspaceKey;
  permission: string;
  /** The base route name; the localized records are `<routeName>-en` / `<routeName>-pt-BR`. */
  routeName: string;
}

export type { Workspace };
