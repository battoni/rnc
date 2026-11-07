<script setup lang="ts">
import { nextTick, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import type { NavChildRouteItem, NavExternalItem, NavLinkItem, NavRouteItem } from '../../../interfaces';
import { revealNavGroup } from '../../../navGroupScroll';

const emit = defineEmits<{
  logout: [];
  'toggle-group': [groupKey: string];
}>();

const props = defineProps<{
  /** The documentation portal, when the environment names one — see `TheNavbar`. */
  docsLink?: NavExternalItem | null;
  expandedGroupKeys: string[];
  itemsWithRoutes: NavRouteItem[];
  personalizationWithRoute: NavLinkItem;
  settingsWithRoutes: NavLinkItem[];
}>();

const route = useRoute();
const { t } = useI18n();

const navScrollArea = useTemplateRef<HTMLElement>('navScrollArea');

// HELPERS
/** An entry built from a person's own record carries its name; everything else is an i18n key. */
function toLabel(item: NavChildRouteItem | NavRouteItem): string {
  return item.isLabelLiteral ? item.label : t(item.label);
}

/** A parent reads as active while one of its children's routes is on screen — a quieter
 *  `bg-panel` on the row, without the icon treatment the exact-active child gets. */
function isGroupActive(item: NavRouteItem): boolean {
  return !!item.children?.some((child) => child.route === route.path);
}

function isGroupExpanded(groupKey: string): boolean {
  return props.expandedGroupKeys.includes(groupKey);
}

/** A collapsed group is clipped, not unmounted — `inert` is what takes its links out of tab
 *  order too, the same trick the rail itself uses. `false` would ship as inert="false", which
 *  HTML reads as inert, so absent is the only "off". */
function toGroupInert(groupKey: string): true | undefined {
  return isGroupExpanded(groupKey) ? undefined : true;
}

/** An open group whose children sit below the fold of this scroll area is a group nobody can
 *  read. Only the list moves — the page never scrolls — so the group is measured against the
 *  scroll container it lives in. Collapsing a group asks for nothing. */
function revealGroup(groupKey: string) {
  if (!isGroupExpanded(groupKey)) return;

  const groupElement = navScrollArea.value?.querySelector<HTMLElement>(`[data-nav-group="${groupKey}"]`) ?? null;

  revealNavGroup(navScrollArea.value, groupElement);
}

// EVENTS
function onGroupToggle(groupKey: string) {
  // Read before the emit: one group is open at a time, so another one is about to collapse and
  // its height is still in the layout — measuring now would overshoot by exactly that height.
  // The transition's own end event does it on the settled layout instead.
  const isAnotherGroupClosing = props.expandedGroupKeys.some((expandedKey) => expandedKey !== groupKey);

  emit('toggle-group', groupKey);

  if (isAnotherGroupClosing) return;

  nextTick(() => revealGroup(groupKey));
}

/** The children finish growing when the track's own transition ends — the first moment the
 *  group can be measured for real. `.self` keeps a child link's colour transition out of it. */
function onGroupTransitionEnd(groupKey: string) {
  revealGroup(groupKey);
}

function onLogoutClick() {
  emit('logout');
}
</script>

<template>
  <div class="hidden w-54 shrink-0 self-start py-8 lg:sticky lg:top-8 lg:ml-8 lg:block">
    <Card
      class="the-sidenav flex h-[calc(100dvh-4rem)] w-full flex-col"
      pt:body="flex flex-col flex-1 h-full min-h-0 p-0"
      pt:content="flex flex-col flex-1 min-h-0"
    >
      <template #content>
        <div class="m-auto flex w-full max-w-[100px] shrink-0 items-center justify-center px-4 py-4">
          <ALogo variant="min" />
        </div>

        <Divider
          class="mx-auto mt-0 mb-1 w-4/5 shrink-0"
          type="dashed"
        />

        <!-- min-h-0 breaks the flex chain's min-content floor so this block, not the rail,
             scrolls once the items outgrow the viewport. Logo stays pinned above, settings
             below (Card #footer) — expanding a group only ever grows this scroll area. -->
        <div
          class="scrollbar-brand flex min-h-0 w-full flex-1 flex-col items-start justify-start gap-1 overflow-y-auto p-2"
          ref="navScrollArea"
        >
          <template
            v-for="item in itemsWithRoutes"
            :key="item.key"
          >
            <!-- Plain link — no children, exactly the item it always was. -->
            <RouterLink
              v-if="!item.children && item.route"
              v-slot="{ href, isExactActive, navigate }"
              custom
              :to="item.route"
            >
              <a
                :class="[
                  'hover:bg-panel motion-instant flex w-full items-center gap-2 rounded-sm p-1 px-2 transition-colors',
                  { 'bg-panel': isExactActive },
                ]"
                :href
                @click="navigate"
              >
                <span
                  :class="['rounded-sm p-2 text-xl', item.icon, { 'bg-primary-100 text-primary-950': isExactActive }]"
                />

                <span>{{ toLabel(item) }}</span>

                <ANavDot
                  v-if="item.hasDot"
                  :label="$t('navigation.unread')"
                />
              </a>
            </RouterLink>

            <!-- Group — a header row plus a collapsible child list. With a route the text
                 navigates and only the chevron toggles; without one the whole row toggles. -->
            <div
              v-else
              class="w-full"
              :data-nav-group="item.key"
            >
              <div
                :class="[
                  'motion-instant flex w-full items-center rounded-sm transition-colors',
                  { 'bg-panel': isGroupActive(item) },
                ]"
              >
                <RouterLink
                  v-if="item.route"
                  v-slot="{ href, isExactActive, navigate }"
                  custom
                  :to="item.route"
                >
                  <a
                    :class="[
                      'hover:bg-panel motion-instant flex min-w-0 flex-1 items-center gap-2 rounded-sm p-1 px-2 transition-colors',
                      { 'bg-panel': isExactActive },
                    ]"
                    :href
                    @click="navigate"
                  >
                    <span
                      :class="[
                        'rounded-sm p-2 text-xl',
                        item.icon,
                        { 'bg-primary-100 text-primary-950': isExactActive },
                      ]"
                    />

                    <span class="min-w-0 flex-1 truncate">{{ toLabel(item) }}</span>
                  </a>
                </RouterLink>

                <Button
                  v-else
                  plain
                  text
                  unstyled
                  class="hover:bg-panel motion-instant flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-sm p-1 px-2 text-left transition-colors"
                  type="button"
                  :aria-expanded="isGroupExpanded(item.key)"
                  @click="onGroupToggle(item.key)"
                >
                  <span :class="['rounded-sm p-2 text-xl', item.icon]" />

                  <span class="min-w-0 flex-1 truncate">{{ toLabel(item) }}</span>

                  <span
                    :class="[
                      'pi pi-chevron-down motion-calm motion-ease-settle text-subtle shrink-0 text-sm transition-transform',
                      { '-rotate-90': !isGroupExpanded(item.key) },
                    ]"
                  />
                </Button>

                <Button
                  v-if="item.route"
                  plain
                  text
                  unstyled
                  class="hover:bg-panel motion-instant flex w-8 shrink-0 cursor-pointer items-center justify-center self-stretch rounded-sm transition-colors"
                  type="button"
                  :aria-expanded="isGroupExpanded(item.key)"
                  :aria-label="$t('navigation.toggleGroup', { section: toLabel(item) })"
                  @click="onGroupToggle(item.key)"
                >
                  <span
                    :class="[
                      'pi pi-chevron-down motion-calm motion-ease-settle text-subtle text-sm transition-transform',
                      { '-rotate-90': !isGroupExpanded(item.key) },
                    ]"
                  />
                </Button>
              </div>

              <!-- Same 0fr→1fr track trick the rail itself collapses with — height animates
                   out of `auto` with nothing hard-coded, so the motion never jumps. -->
              <div
                :class="[
                  'motion-calm motion-ease-settle grid transition-[grid-template-rows]',
                  isGroupExpanded(item.key) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                ]"
                @transitionend.self="onGroupTransitionEnd(item.key)"
              >
                <div
                  class="min-h-0 overflow-hidden"
                  :inert="toGroupInert(item.key)"
                >
                  <ul class="flex w-full flex-col gap-1 pt-1">
                    <li
                      v-for="child in item.children"
                      class="w-full"
                      :key="child.key"
                    >
                      <RouterLink
                        v-slot="{ href, isExactActive, navigate }"
                        custom
                        :to="child.route"
                      >
                        <a
                          :class="[
                            'hover:bg-panel motion-instant text-muted flex min-h-10 w-full items-center gap-2 rounded-sm p-1 pr-2 pl-[18px] text-sm transition-colors pointer-coarse:min-h-11',
                            { 'bg-panel': isExactActive },
                          ]"
                          :href
                          @click="navigate"
                        >
                          <span
                            :class="[
                              'rounded-sm p-1.5 text-base',
                              child.icon,
                              { 'bg-primary-100 text-primary-950': isExactActive },
                            ]"
                          />

                          <span class="min-w-0 truncate">{{ toLabel(child) }}</span>
                        </a>
                      </RouterLink>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>

      <template #footer>
        <div class="sticky bottom-0 w-full shrink-0">
          <Divider
            class="mx-auto mt-0 mb-1 w-4/5"
            type="dashed"
          />

          <div class="flex flex-col gap-1 p-2">
            <RouterLink
              v-for="setting in settingsWithRoutes"
              v-slot="{ href, isExactActive, navigate }"
              custom
              :key="setting.key"
              :to="setting.route"
            >
              <a
                :class="[
                  'hover:bg-panel motion-instant flex w-full items-center gap-2 rounded-sm p-1 px-2 transition-colors',
                  { 'bg-panel': isExactActive },
                ]"
                :href
                @click="navigate"
              >
                <span
                  :class="[
                    'rounded-sm p-2 text-xl',
                    setting.icon,
                    { 'bg-primary-100 text-primary-950': isExactActive },
                  ]"
                />

                <span>{{ $t(setting.label) }}</span>

                <ANavDot
                  v-if="setting.hasDot"
                  :label="$t('navigation.unread')"
                />
              </a>
            </RouterLink>

            <RouterLink
              v-slot="{ href, isExactActive, navigate }"
              custom
              :to="personalizationWithRoute.route"
            >
              <a
                :class="[
                  'hover:bg-panel motion-instant flex w-full items-center gap-2 rounded-sm p-1 px-2 transition-colors',
                  { 'bg-panel': isExactActive },
                ]"
                :href
                @click="navigate"
              >
                <span
                  :class="[
                    'rounded-sm p-2 text-xl',
                    personalizationWithRoute.icon,
                    { 'bg-primary-100 text-primary-950': isExactActive },
                  ]"
                />

                <span>{{ $t(personalizationWithRoute.label) }}</span>
              </a>
            </RouterLink>

            <a
              v-if="docsLink"
              class="hover:bg-panel motion-instant flex w-full items-center gap-2 rounded-sm p-1 px-2 transition-colors"
              data-testid="nav-docs"
              rel="noopener noreferrer"
              target="_blank"
              :href="docsLink.href"
            >
              <span :class="['rounded-sm p-2 text-xl', docsLink.icon]" />

              <span>{{ $t(docsLink.label) }}</span>
            </a>

            <Button
              plain
              text
              unstyled
              class="hover:bg-primary-100 hover:text-primary-950 motion-instant flex w-full cursor-pointer items-center gap-2 rounded-sm p-1 px-2 transition-colors"
              type="button"
              @click="onLogoutClick"
            >
              <span class="pi pi-sign-out rounded-sm p-2 text-xl" />

              <span>{{ $t('navigation.logout') }}</span>
            </Button>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>
