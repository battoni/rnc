<script setup lang="ts"></script>

<template>
  <AuthFlowLayout :title="$t('auth.flow.login.passwordTitle')">
    <MLoginUsernamePassword />

    <template #bottomActions>
      <RouterLink :to="{ name: 'auth.support' }">
        <p class="text-muted text-center text-base">{{ $t('auth.flow.needHelp') }}</p>
      </RouterLink>
    </template>
  </AuthFlowLayout>
</template>
