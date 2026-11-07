<template>
  <div
    data-route-fade
    class="m-auto grid min-h-dvh w-full max-w-[360px] place-items-center"
  >
    <!-- No navigation here, so the whole page is the part that travels: the root carries
         `data-route-fade` and App.vue's route-fade fades all of it. The comment sits INSIDE
         the root on purpose — a root-level comment makes the component fragment-rooted. -->
    <slot />
  </div>
</template>
