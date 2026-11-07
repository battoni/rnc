import { defineStore } from 'pinia';
import type { Tip, TipContent } from '@TipModule';
import { GetActiveTipsService } from '@TipModule';

interface TipState {
  /** Anchor key → the tip rendering there. */
  contents: Record<string, TipContent>;
  /** Anchor whose tip the global edit dialog is currently open for. */
  editingAnchorKey: string | null;
  isLoaded: boolean;
}

/*
 * One request per session, shared by every `<ATip>` that mounts. Without this, a screen carrying
 * six tips would fire six identical requests on load, because each atom would see `isLoaded`
 * still false while the first one was in flight.
 */
let inFlight: Promise<void> | null = null;

export default defineStore('tip', {
  state: (): TipState => ({
    contents: {},
    editingAnchorKey: null,
    isLoaded: false,
  }),
  actions: {
    /**
     * Loads the tips once, lazily, when the first tip on a screen needs them.
     *
     * Deliberately not wired into app bootstrap: it needs a session, and a user who never opens
     * a screen with a tip should not pay for the request.
     */
    ensureLoaded(): Promise<void> {
      if (this.isLoaded) return Promise.resolve();

      if (inFlight) return inFlight;

      inFlight = GetActiveTipsService()
        .then(({ data }) => {
          this.contents = Object.fromEntries(data.map((tip) => [tip.anchor_key, tip]));
          this.isLoaded = true;
        })
        /*
         * Swallowed deliberately, and this is the one place it belongs. Every caller is an
         * `<ATip>` that can do nothing about the failure, and a screen carrying six tips would
         * otherwise raise six toasts about missing help nobody asked for — or, worse, six
         * unhandled rejections. `isLoaded` stays false, so the next tip to mount tries again.
         */
        .catch(() => undefined)
        .finally(() => (inFlight = null));

      return inFlight;
    },
    /**
     * Reflects a management-view change straight into what the rest of the app renders, so an
     * editor sees the new wording without a reload.
     */
    applyTip(tip: Tip) {
      const next = { ...this.contents };

      // A tip may have moved to another anchor, which leaves the old one empty.
      Object.keys(next).forEach((anchorKey) => {
        if (next[anchorKey]?.id === tip.id) delete next[anchorKey];
      });

      if (!tip.archived_at) {
        next[tip.anchor_key] = { anchor_key: tip.anchor_key, content: tip.content, id: tip.id };
      }

      this.contents = next;
    },
    closeEdit() {
      this.editingAnchorKey = null;
    },
    requestEdit(anchorKey: string) {
      this.editingAnchorKey = anchorKey;
    },
    reset() {
      this.contents = {};
      this.isLoaded = false;
      inFlight = null;
    },
  },
  getters: {
    tipFor:
      (state) =>
      (anchorKey: string): TipContent | undefined =>
        state.contents[anchorKey],
  },
});
