import { computed } from 'vue';
import type { Workspace } from '../interfaces';
import { useUserStore } from '@UserModule';
import { WORKSPACES } from '../constants';

/** The workspaces this person may open, in rail order. Pure, so the role table can be tested. */
function visibleWorkspaces(hasPermission: (key: string) => boolean, roleSlug?: string): Workspace[] {
  return WORKSPACES.filter(
    (workspace) => hasPermission(workspace.permission) && (!workspace.role || workspace.role === roleSlug)
  );
}

/** The signed-in person's workspaces, live: a permission change redraws the cards. */
function useWorkspaces() {
  const userStore = useUserStore();

  const workspaces = computed(() => visibleWorkspaces(userStore.hasPermission, userStore.user?.role_slug));

  return { workspaces };
}

export { useWorkspaces, visibleWorkspaces };
