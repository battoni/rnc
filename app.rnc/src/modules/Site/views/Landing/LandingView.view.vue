<template>
  <ThePublicLayout>
    <section class="flex flex-col gap-4 py-10">
      <span class="text-muted text-sm font-semibold tracking-wide uppercase">
        {{ $t('site.landing.kicker') }}
      </span>

      <h1 class="text-heading text-3xl font-bold tracking-tight md:text-5xl">
        {{ $t('site.landing.title') }}
      </h1>

      <p class="text-body text-lg">
        {{ $t('site.landing.lede') }}
      </p>
    </section>
  </ThePublicLayout>
</template>
