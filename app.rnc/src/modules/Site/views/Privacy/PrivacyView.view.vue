<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicPrivacy } from '../../interfaces';
import { usePublicFirm, useSeo } from '../../composables';
import { GetPublicPrivacyService } from '../../services';

/*
 * The firm's privacy notice (rnc-4 R38, A47, F9b): `GET public/privacy` says WHO — the firm as the
 * controller, the platform as the operator, the firm's mailbox and phone for a data request — and
 * WHICH, as stable keys (what is collected, how long each kind is kept, who else may receive it);
 * the words are this page's, under `site.privacy` (W104). At the root the platform is the controller
 * itself, so there is no separate operator line. Linked from the footer and from the diagnostic's
 * consent.
 */
const { firm, load } = usePublicFirm();
const { t } = useI18n();

const hasFailed = ref(false);
const isLoading = ref(true);
const privacy = ref<PublicPrivacy | null>(null);

const controllerName = computed(() => privacy.value?.controller.name ?? '');
const hasRequestContact = computed(() => Boolean(privacy.value?.requests.email || privacy.value?.requests.phone));
const isPlatform = computed(() => privacy.value?.controller.is_platform ?? false);

const seo = computed(() => ({
  description: t('site.privacy.seoDescription'),
  siteName: firm.value?.name ?? null,
  title: t('site.privacy.title'),
}));

useSeo(seo);

onMounted(onComponentMount);

// EVENTS
function fetchPrivacy() {
  isLoading.value = true;
  hasFailed.value = false;

  GetPublicPrivacyService()
    .then(({ data }) => (privacy.value = data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function onComponentMount() {
  load();

  fetchPrivacy();
}
</script>

<template>
  <ThePublicLayout>
    <article
      class="flex flex-col"
      data-testid="privacy-page"
    >
      <header class="bg-panel-muted border-line border-b">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-5 py-12 lg:py-16">
          <p class="text-brand-word text-xs font-semibold tracking-widest uppercase">
            {{ $t('site.privacy.kicker') }}
          </p>

          <h1 class="text-heading text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
            {{ $t('site.privacy.title') }}
          </h1>

          <p
            v-if="privacy"
            class="text-body text-base leading-relaxed sm:text-lg"
          >
            {{ $t('site.privacy.lede', { name: controllerName }) }}
          </p>
        </div>
      </header>

      <div class="mx-auto flex w-full max-w-6xl flex-col gap-10 px-5 py-12 lg:py-16">
        <div
          v-if="isLoading && !privacy"
          class="flex flex-col gap-4"
        >
          <Skeleton height="6rem" />

          <Skeleton height="12rem" />
        </div>

        <div
          v-else-if="hasFailed"
          class="bg-panel shadow-lift flex flex-col items-start gap-4 rounded-lg p-6"
          role="alert"
        >
          <p class="text-body">{{ $t('site.privacy.loadFailed') }}</p>

          <Button
            data-testid="privacy-retry"
            icon="pi pi-refresh"
            severity="secondary"
            :label="$t('site.privacy.retry')"
            @click="fetchPrivacy"
          />
        </div>

        <div
          v-else-if="privacy"
          class="text-body flex flex-col gap-10 leading-relaxed"
          data-testid="privacy-notice"
        >
          <section
            class="flex flex-col gap-3"
            data-testid="privacy-controller"
          >
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.controller.title') }}</h2>

            <p>
              {{
                isPlatform
                  ? $t('site.privacy.controller.platformText', { name: controllerName })
                  : $t('site.privacy.controller.text', { name: controllerName })
              }}
            </p>

            <p
              v-if="privacy.controller.address"
              class="text-muted text-sm"
            >
              {{ $t('site.privacy.controller.address', { address: privacy.controller.address }) }}
            </p>
          </section>

          <section
            v-if="!isPlatform"
            class="flex flex-col gap-3"
            data-testid="privacy-operator"
          >
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.operator.title') }}</h2>

            <p>
              {{ $t('site.privacy.operator.text', { controller: controllerName, name: privacy.operator.name }) }}
            </p>
          </section>

          <section class="flex flex-col gap-3">
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.collected.title') }}</h2>

            <ul
              class="flex list-disc flex-col gap-1.5 pl-5"
              data-testid="privacy-collected"
            >
              <li
                v-for="item in privacy.collected"
                :key="item"
              >
                {{ $t(`site.privacy.collected.items.${item}`) }}
              </li>
            </ul>

            <p>{{ $t('site.privacy.collected.purpose') }}</p>
          </section>

          <section class="flex flex-col gap-3">
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.retention.title') }}</h2>

            <ul
              class="flex list-disc flex-col gap-1.5 pl-5"
              data-testid="privacy-retention"
            >
              <li
                v-for="line in privacy.retention"
                :key="line.data"
              >
                <strong class="text-heading font-semibold">{{ $t(`site.privacy.retention.data.${line.data}`) }}</strong
                >:
                {{ $t(`site.privacy.retention.rules.${line.rule}`) }}
              </li>
            </ul>
          </section>

          <section class="flex flex-col gap-3">
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.sharing.title') }}</h2>

            <ul
              v-if="privacy.sharing.length > 0"
              class="flex list-disc flex-col gap-1.5 pl-5"
              data-testid="privacy-sharing"
            >
              <li
                v-for="item in privacy.sharing"
                :key="item"
              >
                {{ $t(`site.privacy.sharing.items.${item}`) }}
              </li>
            </ul>

            <p>{{ $t('site.privacy.sharing.operatorOnly') }}</p>
          </section>

          <section class="flex flex-col gap-3">
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.rights.title') }}</h2>

            <p>{{ $t('site.privacy.rights.text') }}</p>
          </section>

          <section
            class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5"
            data-testid="privacy-requests"
          >
            <h2 class="text-heading text-xl font-semibold">{{ $t('site.privacy.requests.title') }}</h2>

            <template v-if="hasRequestContact">
              <p>{{ $t('site.privacy.requests.text', { name: controllerName }) }}</p>

              <a
                v-if="privacy.requests.email"
                class="touch-target text-brand-word self-start break-all underline underline-offset-4"
                :href="`mailto:${privacy.requests.email}`"
              >
                {{ privacy.requests.email }}
              </a>

              <span
                v-if="privacy.requests.phone"
                class="tabular-nums"
              >
                {{ $t('site.privacy.requests.phone', { phone: privacy.requests.phone }) }}
              </span>
            </template>

            <p v-else>{{ $t('site.privacy.requests.none') }}</p>
          </section>
        </div>
      </div>
    </article>
  </ThePublicLayout>
</template>
