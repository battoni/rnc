<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
      <Skeleton
        height="2rem"
        width="12rem"
      />

      <Skeleton
        height="2.5rem"
        width="10rem"
      />
    </div>

    <div class="flex gap-4">
      <Skeleton
        height="2.5rem"
        width="8rem"
      />

      <Skeleton
        height="2.5rem"
        width="8rem"
      />

      <Skeleton
        height="2.5rem"
        width="8rem"
      />
    </div>

    <div class="mt-4 mb-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <Skeleton
          height="1.25rem"
          width="6rem"
        />

        <Skeleton
          height="1.5rem"
          width="3rem"
        />
      </div>

      <div class="flex gap-2">
        <Skeleton
          height="2.25rem"
          width="5rem"
        />

        <Skeleton
          height="2.25rem"
          width="5rem"
        />

        <Skeleton
          height="2.25rem"
          width="5rem"
        />
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <Skeleton
        v-for="skeletonIndex in 6"
        class="rounded-md"
        height="12rem"
        :key="skeletonIndex"
        width="100%"
      />
    </div>
  </div>
</template>
