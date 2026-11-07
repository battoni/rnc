<script setup lang="ts"></script>

<template>
  <AuthFlowLayout :title="$t('auth.forgotPassword.title')">
    <template #topActions>
      <RouterLink :to="{ name: 'auth.entrar' }">
        <Button
          link
          class="celer-button-link-secondary"
          icon="pi pi-arrow-left"
          :label="$t('auth.flow.back')"
        />
      </RouterLink>
    </template>

    <MSupportForgotPassword />
  </AuthFlowLayout>
</template>
