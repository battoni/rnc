import { computed } from 'vue';
import type { Workspace } from '../interfaces';
import { useUserStore } from '@UserModule';
import { WORKSPACES } from '../constants';

/** The workspaces this person may open, in rail order. Pure, so the role table can be tested. */
function visibleWorkspaces(hasPermission: (key: string) => boolean): Workspace[] {
  return WORKSPACES.filter((workspace) => hasPermission(workspace.permission));
}

/** The signed-in person's workspaces, live: a permission change redraws the cards. */
function useWorkspaces() {
  const userStore = useUserStore();

  const workspaces = computed(() => visibleWorkspaces(userStore.hasPermission));

  return { workspaces };
}

export { useWorkspaces, visibleWorkspaces };
