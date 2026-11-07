<script setup lang="ts">
import { FIRM_PUBLIC_CONTACT } from '@Constants';

const CURRENT_YEAR = new Date().getFullYear();
</script>

<template>
  <div class="bg-canvas text-body flex min-h-dvh w-full flex-col">
    <header class="border-line border-b">
      <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-4">
        <RouterLink
          class="touch-target text-xl"
          :to="{ name: 'site-home' }"
        >
          <ALogo />
        </RouterLink>
      </div>
    </header>

    <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col px-5 py-10">
      <slot />
    </main>

    <footer class="bg-panel border-line border-t">
      <div class="mx-auto grid w-full max-w-6xl gap-6 px-5 py-8 text-sm md:grid-cols-3">
        <div class="flex flex-col gap-2">
          <ALogo class="text-lg" />

          <p class="text-muted">
            {{ $t('site.footer.tagline') }}
          </p>
        </div>

        <address class="text-muted flex flex-col gap-2 not-italic">
          <span class="text-heading font-semibold">
            {{ $t('site.footer.contact') }}
          </span>

          <span>{{ FIRM_PUBLIC_CONTACT.address }}</span>

          <a
            class="touch-target hover:text-heading underline-offset-4 hover:underline"
            :href="FIRM_PUBLIC_CONTACT.phoneHref"
          >
            {{ FIRM_PUBLIC_CONTACT.phone }}
          </a>

          <a
            class="touch-target hover:text-heading underline-offset-4 hover:underline"
            :href="`mailto:${FIRM_PUBLIC_CONTACT.mailbox}`"
          >
            {{ FIRM_PUBLIC_CONTACT.mailbox }}
          </a>
        </address>

        <div class="text-muted flex flex-col gap-2">
          <span class="text-heading font-semibold">
            {{ $t('site.footer.hours') }}
          </span>

          <span>{{ $t('site.footer.hoursWeekdays') }}</span>

          <span>{{ $t('site.footer.hoursFriday') }}</span>

          <span class="mt-auto">
            {{ $t('site.footer.copyright', { year: CURRENT_YEAR, name: FIRM_PUBLIC_CONTACT.name }) }}
          </span>
        </div>
      </div>
    </footer>
  </div>
</template>
