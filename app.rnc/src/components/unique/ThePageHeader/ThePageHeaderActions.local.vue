<script setup lang="ts">
import { computed } from 'vue';
import { NOTIFICATIONS_PERMISSION } from '@NotificationModule';
import { useUserStore } from '@UserModule';

/*
 * Project-owned header actions — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * What every back-office screen shows at the right end of its title row, after the screen's own
 * actions. rnc-1 F4: the bell (R17), for whoever holds `notifications.read`.
 */
const userStore = useUserStore();

const canReadNotifications = computed(() => userStore.hasPermission(NOTIFICATIONS_PERMISSION));
</script>

<template>
  <MNotificationBell v-if="canReadNotifications" />
</template>
