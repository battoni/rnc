<script setup lang="ts">
type ColorStep = { class: string; shade: string };
type ColorRamp = { main: string; name: string; steps: ColorStep[] };
type SemanticToken = { class: string; hex: string; name: string };

const CODE_GLYPHS = ['>_', '</>', '{ }', '[ ]', '=>', '//', '« »', '*'];

const PROGRESS_BARS = [
  { key: 'tokens', value: 92 },
  { key: 'identity', value: 68 },
  { key: 'atmosphere', value: 41 },
];

const TIMELINE_PHASES = [
  { key: 'foundation', num: '01' },
  { key: 'identity', num: '02' },
  { key: 'atmosphere', num: '03' },
];

const SEMANTIC_TOKENS: SemanticToken[] = [
  { name: 'success', class: 'bg-success-500', hex: '#22c55e' },
  { name: 'info', class: 'bg-info-500', hex: '#3b82f6' },
  { name: 'warn', class: 'bg-warn-500', hex: '#f59e0b' },
  { name: 'danger', class: 'bg-danger-500', hex: '#ef4444' },
  { name: 'help', class: 'bg-help-500', hex: '#a855f7' },
  { name: 'contrast', class: 'bg-contrast-500', hex: '#737373' },
];

const COLOR_RAMPS: ColorRamp[] = [
  {
    name: 'primary',
    main: '#c0e021',
    steps: [
      { shade: '50', class: 'bg-primary-50' },
      { shade: '100', class: 'bg-primary-100' },
      { shade: '200', class: 'bg-primary-200' },
      { shade: '300', class: 'bg-primary-300' },
      { shade: '400', class: 'bg-primary-400' },
      { shade: '500', class: 'bg-primary-500' },
      { shade: '600', class: 'bg-primary-600' },
      { shade: '700', class: 'bg-primary-700' },
      { shade: '800', class: 'bg-primary-800' },
      { shade: '900', class: 'bg-primary-900' },
      { shade: '950', class: 'bg-primary-950' },
    ],
  },
  {
    name: 'ink',
    main: '#141413',
    steps: [
      { shade: '50', class: 'bg-ink-50' },
      { shade: '100', class: 'bg-ink-100' },
      { shade: '200', class: 'bg-ink-200' },
      { shade: '300', class: 'bg-ink-300' },
      { shade: '400', class: 'bg-ink-400' },
      { shade: '500', class: 'bg-ink-500' },
      { shade: '600', class: 'bg-ink-600' },
      { shade: '700', class: 'bg-ink-700' },
      { shade: '800', class: 'bg-ink-800' },
      { shade: '900', class: 'bg-ink-900' },
      { shade: '950', class: 'bg-ink-950' },
    ],
  },
  {
    name: 'surface',
    main: '#888b8d',
    steps: [
      { shade: '50', class: 'bg-surface-50' },
      { shade: '100', class: 'bg-surface-100' },
      { shade: '200', class: 'bg-surface-200' },
      { shade: '300', class: 'bg-surface-300' },
      { shade: '400', class: 'bg-surface-400' },
      { shade: '500', class: 'bg-surface-500' },
      { shade: '600', class: 'bg-surface-600' },
      { shade: '700', class: 'bg-surface-700' },
      { shade: '800', class: 'bg-surface-800' },
      { shade: '900', class: 'bg-surface-900' },
      { shade: '950', class: 'bg-surface-950' },
    ],
  },
];
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="showcase.brand.title" />
    </template>

    <div class="flex flex-col gap-14 pb-10">
      <!-- HERO -->
      <section class="border-line bg-primary-500 relative overflow-hidden rounded-xl border">
        <div class="text-ink-900 absolute inset-0 opacity-[0.06]">
          <ABrandPattern
            :rotate="-8"
            :tile="150"
          />
        </div>

        <div class="relative flex flex-col gap-8 p-8 sm:p-12">
          <ABrandMark class="text-ink-900 h-16 w-auto" />

          <div class="flex flex-col gap-4">
            <span class="text-ink-900/70 font-mono text-sm tracking-[0.2em] uppercase">{{
              '>_ app.rnc × battoni.dev'
            }}</span>

            <h2 class="text-ink-950 max-w-2xl text-4xl leading-[1.05] font-bold tracking-tight sm:text-5xl">
              {{ $t('showcase.brand.hero.headline') }}
            </h2>

            <p class="text-ink-900/80 max-w-xl font-mono text-sm leading-relaxed">
              {{ $t('showcase.brand.hero.lede') }}
            </p>
          </div>

          <div class="text-ink-900/70 flex flex-wrap gap-3 font-mono text-base">
            <span
              v-for="glyph in CODE_GLYPHS"
              :key="glyph"
            >
              {{ glyph }}
            </span>
          </div>
        </div>
      </section>

      <!-- COLOR -->
      <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
          <p class="text-muted font-mono text-xs tracking-[0.2em] uppercase">
            {{ $t('showcase.brand.sections.color.eyebrow') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight">
            {{ $t('showcase.brand.sections.color.title') }}
          </h2>
        </div>

        <div class="flex flex-col gap-6">
          <div
            v-for="ramp in COLOR_RAMPS"
            class="flex flex-col gap-2"
            :key="ramp.name"
          >
            <div class="flex items-baseline justify-between">
              <span class="text-heading font-mono text-sm font-medium">{{ ramp.name }}</span>

              <span class="text-muted font-mono text-xs">
                {{ $t(`showcase.brand.ramps.${ramp.name}`) }} · {{ ramp.main }}
              </span>
            </div>

            <div class="flex flex-col gap-1.5">
              <div class="border-line grid grid-cols-11 overflow-hidden rounded-lg border">
                <div
                  v-for="step in ramp.steps"
                  :key="step.shade"
                  :class="['h-12', step.class]"
                />
              </div>

              <div class="grid grid-cols-11">
                <span
                  v-for="step in ramp.steps"
                  class="text-muted text-center font-mono text-xs"
                  :key="step.shade"
                >
                  {{ step.shade }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <div
            v-for="token in SEMANTIC_TOKENS"
            class="border-line bg-panel flex flex-col gap-2 rounded-lg border p-3"
            :key="token.name"
          >
            <div :class="['h-9 w-full rounded-md', token.class]" />

            <div class="flex flex-col gap-0.5">
              <div class="flex items-baseline justify-between gap-1">
                <span class="text-heading font-mono text-xs font-medium">{{ token.name }}</span>

                <span class="text-muted font-mono text-xs">{{ token.hex }}</span>
              </div>

              <span class="text-muted text-xs">{{ $t(`showcase.brand.tokens.${token.name}`) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- TYPOGRAPHY -->
      <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
          <p class="text-muted font-mono text-xs tracking-[0.2em] uppercase">
            {{ $t('showcase.brand.sections.type.eyebrow') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight">
            {{ $t('showcase.brand.sections.type.title') }}
          </h2>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="border-line bg-panel flex flex-col gap-4 rounded-xl border p-6">
            <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">
              {{ $t('showcase.brand.typography.displayCaption') }}
            </span>

            <p class="text-heading text-5xl font-bold tracking-tight">
              {{ $t('showcase.brand.typography.displayLarge') }}
            </p>

            <p class="text-heading text-2xl font-semibold tracking-tight">
              {{ $t('showcase.brand.typography.displayMedium') }}
            </p>

            <p class="text-body text-base">{{ $t('showcase.brand.typography.displayBody') }}</p>
          </div>

          <div class="border-line bg-panel flex flex-col gap-4 rounded-xl border p-6">
            <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">
              {{ $t('showcase.brand.typography.textCaption') }}
            </span>

            <p class="text-heading font-mono text-2xl font-medium">{ battoni.dev }</p>

            <p class="text-body font-mono text-sm leading-relaxed">
              {{ $t('showcase.brand.typography.textBody') }}
            </p>

            <p class="text-muted font-mono text-xs tracking-[0.18em] uppercase">0123456789 · &lt;/&gt; · =&gt;</p>
          </div>
        </div>
      </section>

      <!-- THE MARK -->
      <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
          <p class="text-muted font-mono text-xs tracking-[0.2em] uppercase">
            {{ $t('showcase.brand.sections.mark.eyebrow') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight">
            {{ $t('showcase.brand.sections.mark.title') }}
          </h2>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
          <div class="bg-primary-500 flex aspect-[4/3] items-center justify-center rounded-xl">
            <ABrandMark class="text-ink-900 h-20 w-auto" />
          </div>

          <div class="bg-ink-900 flex aspect-[4/3] items-center justify-center rounded-xl">
            <ABrandMark class="text-primary-500 h-20 w-auto" />
          </div>

          <div class="border-line bg-panel flex aspect-[4/3] items-center justify-center rounded-xl border">
            <ABrandMark class="text-ink-900 dark:text-surface-50 h-20 w-auto" />
          </div>
        </div>

        <p class="text-muted max-w-2xl font-mono text-sm leading-relaxed">
          {{ $t('showcase.brand.sections.mark.body') }}
        </p>
      </section>

      <!-- VISUAL LANGUAGE -->
      <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
          <p class="text-muted font-mono text-xs tracking-[0.2em] uppercase">
            {{ $t('showcase.brand.sections.language.eyebrow') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight">
            {{ $t('showcase.brand.sections.language.title') }}
          </h2>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
          <div class="grid grid-cols-4 gap-3">
            <div
              v-for="glyph in CODE_GLYPHS"
              class="border-line bg-panel flex aspect-square items-center justify-center rounded-lg border"
              :key="glyph"
            >
              <span class="text-heading font-mono text-xl">{{ glyph }}</span>
            </div>
          </div>

          <div class="border-line bg-ink-900 relative overflow-hidden rounded-xl border">
            <div class="text-primary-500 absolute inset-0 opacity-10">
              <ABrandPattern
                :rotate="-8"
                :tile="120"
              />
            </div>

            <div class="relative flex h-full flex-col justify-end gap-1 p-5">
              <span class="text-primary-500 font-mono text-xs tracking-[0.2em] uppercase">
                {{ $t('showcase.brand.language.patternLabel') }}
              </span>

              <span class="text-surface-50 font-mono text-sm">{{ $t('showcase.brand.language.patternNote') }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- COMPONENTS -->
      <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
          <p class="text-muted font-mono text-xs tracking-[0.2em] uppercase">
            {{ $t('showcase.brand.sections.components.eyebrow') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight">
            {{ $t('showcase.brand.sections.components.title') }}
          </h2>
        </div>

        <div class="border-line bg-panel flex flex-col gap-6 rounded-xl border p-6">
          <div class="flex flex-wrap items-center gap-3">
            <Button
              severity="primary"
              :label="$t('showcase.brand.context.buttons.primary')"
            />

            <Button
              severity="secondary"
              :label="$t('showcase.brand.context.buttons.secondary')"
            />

            <Button
              severity="success"
              :label="$t('showcase.brand.context.buttons.success')"
            />

            <Button
              severity="danger"
              :label="$t('showcase.brand.context.buttons.danger')"
            />

            <Button
              icon="pi pi-bolt"
              severity="primary"
              :label="$t('showcase.brand.context.buttons.withIcon')"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Tag :value="$t('showcase.brand.context.tags.primary')" />

            <Tag
              severity="success"
              :value="$t('showcase.brand.context.tags.done')"
            />

            <Tag
              severity="info"
              :value="$t('showcase.brand.context.tags.scheduled')"
            />

            <Tag
              severity="warn"
              :value="$t('showcase.brand.context.tags.pending')"
            />

            <Tag
              severity="danger"
              :value="$t('showcase.brand.context.tags.canceled')"
            />
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label class="text-muted block text-sm">{{ $t('showcase.brand.context.username') }}</label>

              <InputText :placeholder="$t('showcase.brand.context.usernamePlaceholder')" />
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-muted block text-sm">{{ $t('showcase.brand.context.role') }}</label>

              <InputText :placeholder="$t('showcase.brand.context.rolePlaceholder')" />
            </div>
          </div>
        </div>
      </section>

      <!-- SIGNATURE — living elements (these come alive under theme-2) -->
      <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
          <p class="text-muted font-mono text-xs tracking-[0.2em] uppercase">
            {{ $t('showcase.brand.sections.signature.eyebrow') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight">
            {{ $t('showcase.brand.sections.signature.title') }}
          </h2>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="border-line bg-panel flex flex-col gap-4 rounded-xl border p-6">
            <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">
              {{ $t('showcase.brand.signature.progress') }}
            </span>

            <div
              v-for="bar in PROGRESS_BARS"
              class="flex flex-col gap-1.5"
              :key="bar.key"
            >
              <div class="flex items-baseline justify-between">
                <span class="text-muted font-mono text-xs uppercase">
                  {{ $t(`showcase.brand.signature.bars.${bar.key}`) }}
                </span>

                <span class="text-heading font-mono text-sm">{{ bar.value }}%</span>
              </div>

              <div class="bg-panel-muted h-2.5 overflow-hidden rounded-full">
                <div
                  class="brand-track bg-primary-500 h-full rounded-full"
                  :style="{ width: `${bar.value}%` }"
                />
              </div>
            </div>
          </div>

          <div class="border-line bg-panel flex flex-col gap-4 rounded-xl border p-6">
            <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">
              {{ $t('showcase.brand.signature.timeline') }}
            </span>

            <div class="relative flex flex-col gap-5">
              <span class="bg-line absolute top-2 bottom-2 left-[5px] w-px" />

              <div
                v-for="phase in TIMELINE_PHASES"
                class="relative pl-6"
                :key="phase.num"
              >
                <span class="brand-node bg-primary-500 absolute top-1 left-0 size-3 rounded-sm" />

                <h3 class="text-heading text-sm font-semibold">
                  <span class="text-primary-500 font-mono text-xs">{{ phase.num }}</span>
                  {{ $t(`showcase.brand.signature.phases.${phase.key}.title`) }}
                </h3>

                <p class="text-muted mt-0.5 text-sm">{{ $t(`showcase.brand.signature.phases.${phase.key}.detail`) }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="border-line bg-panel flex flex-col gap-3 rounded-xl border p-6">
          <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">
            {{ $t('showcase.brand.signature.glossary') }}
          </span>

          <i18n-t
            class="text-body text-sm leading-relaxed"
            keypath="showcase.brand.signature.glossaryLede"
            scope="global"
            tag="p"
          >
            <template #term>
              <span class="brand-term">{{ $t('showcase.brand.signature.glossaryTerm') }}</span>
            </template>
          </i18n-t>

          <div class="brand-gloss">
            <b>{{ $t('showcase.brand.signature.glossaryTerm') }}</b> —
            {{ $t('showcase.brand.signature.glossaryDefinition') }}
          </div>
        </div>
      </section>
    </div>
  </TheLayout>
</template>
