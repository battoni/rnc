<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="personalization.title" />
    </template>

    <div class="flex flex-col gap-6">
      <header class="flex flex-col gap-2">
        <h2 class="text-heading text-lg font-semibold">
          {{ $t('personalization.subtitle') }}
        </h2>

        <p class="text-muted max-w-prose">
          {{ $t('personalization.description') }}
        </p>
      </header>

      <div class="max-w-xl">
        <MPersonalizationVisualCard />
      </div>
    </div>
  </TheLayout>
</template>
