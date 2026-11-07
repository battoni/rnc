import type { TipAnchor } from '@Interfaces';

/**
 * Every place in the app where an `<ATip>` can render, declared once.
 *
 * This catalogue lives in the FRONTEND on purpose: the anchors ARE the `<ATip tip-key="...">` call
 * sites, and they physically exist here. Keeping the list in the API would mean two repositories
 * holding the same list and drifting apart the first time a screen is renamed.
 *
 * It is what the Tips view shows under "used in", and what the create dropdown offers — so an
 * editor never writes a tip that has nowhere to appear. A key renamed here without being renamed at
 * the call site is caught by `tipAnchor.constant.spec.ts`, which walks the `.vue` files; without
 * that test a renamed anchor would silently blank a tip instead of failing.
 *
 * `routeName` + `routeQuery` build the "see in app" link: where to send someone so they can SEE the
 * tip in place. Every anchor has one — a row in the Tips view with no way to reach the screen
 * leaves the editor guessing what the words they are rewriting sit next to.
 *
 * THE BOOTSTRAP SHIPS ITS OWN SCREENS AND NOTHING ELSE. A project adds its anchors here, beside the
 * `<ATip>` it just placed. The two edits belong together, and the spec fails if only one happens.
 */
const TIP_ANCHORS: TipAnchor[] = [
  {
    key: 'users.list',
    labelKey: 'tips.anchors.usersList',
    routeName: 'users',
    sectionKey: 'tips.sections.administration',
  },
  {
    key: 'roles.permissions',
    labelKey: 'tips.anchors.rolesPermissions',
    routeName: 'roles-and-permissions',
    sectionKey: 'tips.sections.administration',
  },
  {
    key: 'personalization.appearance',
    labelKey: 'tips.anchors.personalizationAppearance',
    routeName: 'personalization',
    sectionKey: 'tips.sections.workspace',
  },
  {
    key: 'features.catalogue',
    labelKey: 'tips.anchors.featuresCatalogue',
    routeName: 'features',
    sectionKey: 'tips.sections.workspace',
  },
];

export { TIP_ANCHORS };
