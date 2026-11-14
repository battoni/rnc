<script setup lang="ts">
import { vReveal } from '@Directives';
import { PRODUCT_ANCHORS } from '../../../constants';

/*
 * How the beta works (rnc-4 R28, D23) beside the form that joins it (R29): few firms, about three
 * months, the price line D23 fixes — no figure — and the firm's say in what comes next. The form owns
 * its call and its success state (`MBetaSignupForm`).
 */
const BETA_POINTS = [
  { icon: 'pi pi-users', key: 'few' },
  { icon: 'pi pi-calendar', key: 'duration' },
  { icon: 'pi pi-tag', key: 'price' },
  { icon: 'pi pi-comments', key: 'voice' },
] as const;
</script>

<template>
  <section
    :id="PRODUCT_ANCHORS.beta"
    class="mx-auto w-full max-w-6xl scroll-mt-24 px-5 py-20 lg:py-24"
    data-testid="product-beta"
  >
    <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-14">
      <div class="flex flex-col gap-6 lg:sticky lg:top-24">
        <header
          v-reveal
          class="flex flex-col gap-3"
        >
          <p
            class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
          >
            {{ $t('site.product.beta.kicker') }}
          </p>

          <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
            {{ $t('site.product.beta.title') }}
          </h2>
        </header>

        <ul
          v-reveal.stagger
          class="flex flex-col gap-4"
        >
          <li
            v-for="point in BETA_POINTS"
            class="flex items-start gap-4"
            :key="point.key"
          >
            <span
              class="bg-primary-100 text-primary-950 flex size-10 shrink-0 items-center justify-center rounded-full"
            >
              <i
                aria-hidden="true"
                :class="point.icon"
              />
            </span>

            <span class="flex flex-col gap-1">
              <strong class="text-heading font-semibold">
                {{ $t(`site.product.beta.points.${point.key}.title`) }}
              </strong>

              <span class="text-body text-sm leading-relaxed">
                {{ $t(`site.product.beta.points.${point.key}.text`) }}
              </span>
            </span>
          </li>
        </ul>
      </div>

      <div
        v-reveal
        class="bg-panel shadow-lift-lg ring-line flex flex-col gap-6 rounded-xl p-6 ring-1 sm:p-8"
      >
        <header class="flex flex-col gap-2">
          <h3 class="text-heading text-2xl font-semibold tracking-tight">
            {{ $t('site.product.form.title') }}
          </h3>

          <p class="text-muted text-sm leading-relaxed">
            {{ $t('site.product.form.lede') }}
          </p>
        </header>

        <MBetaSignupForm />
      </div>
    </div>
  </section>
</template>
