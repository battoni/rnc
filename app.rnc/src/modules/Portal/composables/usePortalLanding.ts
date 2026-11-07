import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useUserStore } from '@UserModule';
import { PORTAL_PERMISSIONS, STAFF_HOME_PERMISSION } from '../constants';

/**
 * Whether the signed-in person belongs in the portal rather than the back office (rnc-2 R3): holds
 * `portal.access` and not the staff's `home.read`. The operator holds both and stays in the back
 * office; a partner holds only the first. Home (`/painel`) reads it to send a partner on to
 * `/parceiro` — and since every refused back-office route falls back to Home, every one of them
 * bounces a partner to its portal.
 */
export default function usePortalLanding(): { isPortalOnly: ComputedRef<boolean> } {
  const userStore = useUserStore();

  const isPortalOnly = computed(
    () => userStore.hasPermission(PORTAL_PERMISSIONS.access) && !userStore.hasPermission(STAFF_HOME_PERMISSION)
  );

  return { isPortalOnly };
}
