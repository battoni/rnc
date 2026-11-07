<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import type { NewsReactionCounts } from '../../../interfaces';
import { NEWS_REACTION_GLYPHS } from '../../../constants';
import { NEWS_REACTION } from '../../../enums';

/**
 * Applause on an entry: clap, heart, rocket, as many times as you like.
 *
 * Not a toggle, and that is the whole design. An idea's vote is a judgement and must be one per
 * person or the ranking measures enthusiasm instead of ideas. This is the opposite gesture — it
 * costs nothing, means nothing but delight, and clapping twice is the point.
 *
 * The count on screen moves on the click, before the server answers. Applause that waits 200ms for
 * permission does not feel like applause.
 *
 * These buttons carry `data-allow-rapid-click`, the app-wide double-click guard's escape hatch.
 * That guard exists because one intent must not become two records; here a second click IS a
 * second intent, and swallowing it would break the only gesture this component has.
 *
 * THE STATE IS RELIEF, NOT AN OUTLINE (CRITICAL). An unspent kind is a raised surface
 * (`shadow-lift`) and a spent one is pressed into the panel (`shadow-sink`), which is how the rest
 * of the product says raised and pressed — see `styles/theme/RULES.md`. They were bordered boxes
 * with a `border-primary-500` when spent, which is the one relief vocabulary the theme explicitly
 * retires: a border draws a box around a surface, and this is a surface. The spent count is also
 * `text-heading` and semibold against the unspent `text-muted`, so the state survives a scheme where
 * shadows read faint. Not a primary shade: the ramp does not flip, and primary-700 on the light
 * panel measured 1.9:1 — a count is text, and text is a role token.
 *
 * Reduced motion throws no confetti at all, in script as well as in CSS. The stylesheet hides the
 * burst, so its `animationend` never fires, and a particle that waits for it to leave would stay in
 * the DOM — seven more per click until the card unmounts.
 */
const props = defineProps<{
  disabled?: boolean;
  myReactions?: NewsReactionCounts;
  reactions?: NewsReactionCounts;
}>();

/*
 * `settle` is handed back so the parent can say WHICH request finished, rather than the component
 * guessing from a prop changing. See `pendingClaims`.
 */
const emit = defineEmits<{ onReact: [type: NEWS_REACTION, settle: () => void] }>();

let particleId = 0;

const bursts = ref<{ id: number; type: NEWS_REACTION; angle: number; distance: number; spin: number; delay: number }[]>(
  []
);
let claimId = 0;

/*
 * The loans: one claim per click whose request has not come back yet.
 *
 * Each claim is repaid by ITS OWN request settling, through the `settle` callback handed out with
 * the emit — not by `props.reactions` changing identity, which is what this watched before. That
 * was wrong twice over. Clearing the whole map on the first response made three fast claps fall
 * from 3 to 1 before climbing back; and once it was a queue, ANY write to the entry repaid a loan,
 * so opening an article you had just clapped made the count drop and jump as the mark-read
 * response landed between the clap and its answer.
 *
 * A request that FAILS repays its claim too — the reader must not be left looking at applause the
 * server never recorded.
 */
const pendingClaims = ref<{ id: number; type: NEWS_REACTION }[]>([]);

const types = computed(() => Object.values(NEWS_REACTION));

const mine = computed(() =>
  types.value.reduce<NewsReactionCounts>(
    (accumulator, type) => ({
      ...accumulator,
      [type]: (props.myReactions?.[type] ?? 0) + pendingFor(type),
    }),
    {}
  )
);

const totals = computed(() =>
  types.value.reduce<NewsReactionCounts>(
    (accumulator, type) => ({
      ...accumulator,
      [type]: (props.reactions?.[type] ?? 0) + pendingFor(type),
    }),
    {}
  )
);

onBeforeUnmount(onComponentUnmount);

// HELPERS

function glyph(type: NEWS_REACTION): string {
  return NEWS_REACTION_GLYPHS[type] ?? '';
}

/* Guarded: an engine without `matchMedia` gets the confetti, as it gets every other animation. */
function prefersReducedMotion(): boolean {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function pendingFor(type: NEWS_REACTION): number {
  return pendingClaims.value.filter((claim) => claim.type === type).length;
}

/**
 * One burst of confetti, thrown from the button that was clicked.
 *
 * Every particle gets its own angle, distance, spin and delay, so hammering the button never
 * produces the same shape twice — the thing that makes a repeated animation feel mechanical is
 * that it repeats.
 */
function spawnBurst(type: NEWS_REACTION) {
  if (prefersReducedMotion()) return;

  const PARTICLE_COUNT = 7;

  const created = Array.from({ length: PARTICLE_COUNT }, () => ({
    id: (particleId += 1),
    type,
    // A fan upward, never straight down: applause rises.
    angle: -90 + (Math.random() * 120 - 60),
    distance: 42 + Math.random() * 46,
    spin: Math.random() * 220 - 110,
    delay: Math.random() * 90,
  }));

  bursts.value = [...bursts.value, ...created];
}

// EVENTS

function onReactClick(type: NEWS_REACTION) {
  if (props.disabled) return;

  // Optimistic: the count and the confetti are the feedback, and they must land on the click.
  const CLAIM_ID = (claimId += 1);

  pendingClaims.value = [...pendingClaims.value, { id: CLAIM_ID, type }];

  spawnBurst(type);

  emit('onReact', type, () => onClaimSettle(CLAIM_ID));
}

/* Particles are removed when their own animation ends — no timer to leak, none to get out of sync. */
function onParticleEnd(id: number) {
  bursts.value = bursts.value.filter((particle) => particle.id !== id);
}

/*
 * One request settled, one claim repaid — by id, so it does not matter which order they come back
 * in, and an unrelated write to the entry repays nothing.
 *
 * Without repayment every click is counted twice: once locally the moment it is clicked, and again
 * when the saved entry comes back with the same click already in it. Three claps showed eight.
 */
function onClaimSettle(id: number) {
  pendingClaims.value = pendingClaims.value.filter((claim) => claim.id !== id);
}

function onComponentUnmount() {
  bursts.value = [];
  pendingClaims.value = [];
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <!-- `aria-label`: the glyph is decorative and the count is a bare number, so without a name a
         screen reader announced the whole control as "4". `gap-3` on the row and `touch-target`
         here: a hand-written button grows its hit area 6px on a touch screen, and 12px apart keeps
         the grown areas from overlapping (celer-06). -->
    <button
      v-for="type in types"
      data-allow-rapid-click
      :key="type"
      type="button"
      :aria-label="$t(`news.reactions.${type}`, { count: totals[type] ?? 0 })"
      :class="[
        'motion-instant active:shadow-sink touch-target relative flex cursor-pointer items-center gap-1.5 rounded-sm px-2.5 py-1.5 transition-[background-color,box-shadow] disabled:cursor-not-allowed disabled:opacity-60',
        (mine[type] ?? 0) > 0 ? 'bg-panel-muted shadow-sink' : 'bg-panel shadow-lift hover:bg-panel-muted',
      ]"
      :data-testid="`news-react-${type}`"
      :disabled
      @click="onReactClick(type)"
    >
      <span
        aria-hidden="true"
        class="text-base leading-none"
      >
        {{ glyph(type) }}
      </span>

      <span :class="['text-sm tabular-nums', (mine[type] ?? 0) > 0 ? 'text-heading font-semibold' : 'text-muted']">
        {{ totals[type] }}
      </span>

      <!-- The confetti. It launches from the button's top edge rather than its middle, so the
           count underneath stays readable while the burst is in the air. `pointer-events-none` so
           a burst never eats the next click of a fast double-clap, and `overflow-visible` on the
           button so particles leave its box. -->
      <span
        v-for="particle in bursts.filter((item) => item.type === type)"
        aria-hidden="true"
        class="news-burst pointer-events-none absolute top-0 left-1/2 text-base leading-none select-none"
        :key="particle.id"
        :style="{
          '--burst-x': `${Math.cos((particle.angle * Math.PI) / 180) * particle.distance}px`,
          '--burst-y': `${Math.sin((particle.angle * Math.PI) / 180) * particle.distance}px`,
          '--burst-spin': `${particle.spin}deg`,
          animationDelay: `${particle.delay}ms`,
        }"
        @animationend="onParticleEnd(particle.id)"
      >
        {{ glyph(particle.type) }}
      </span>
    </button>
  </div>
</template>

<style scoped>
button {
  overflow: visible;
}

.news-burst {
  animation: news-burst 720ms cubic-bezier(0.2, 0.7, 0.3, 1) forwards;
}

@keyframes news-burst {
  0% {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.4) rotate(0deg);
  }

  18% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1.15) rotate(calc(var(--burst-spin) * 0.2));
  }

  100% {
    opacity: 0;
    transform: translate(calc(-50% + var(--burst-x)), calc(-50% + var(--burst-y))) scale(0.7) rotate(var(--burst-spin));
  }
}

/*
 * The count still moves and the button still presses in, so the gesture is fully legible without
 * anything flying across the screen. `spawnBurst` refuses to create particles here too; this rule is
 * the second half, for a preference that changes while a burst is already in the air.
 */
@media (prefers-reduced-motion: reduce) {
  .news-burst {
    display: none;
  }
}
</style>
