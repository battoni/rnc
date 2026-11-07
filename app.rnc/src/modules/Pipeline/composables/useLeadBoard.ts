import { ref } from 'vue';
import type { BoardColumn, LeadCard, LeadMove } from '../interfaces';
import { GetLeadBoardService } from '../services';

/**
 * The board's state (rnc-1 R18): the columns as the API answered them, the search they answered, and
 * the granular update after a move — the card leaves its column and lands on top of the new one,
 * each count following, without asking for the board again.
 */
function useLeadBoard() {
  const appliedSearch = ref('');
  const columns = ref<BoardColumn[]>([]);
  const hasFailed = ref(false);
  const isLoading = ref(false);

  // HELPERS
  function load(search: string): Promise<unknown> {
    isLoading.value = true;
    hasFailed.value = false;

    return GetLeadBoardService(search)
      .then((response) => {
        columns.value = response.data.columns;
        appliedSearch.value = search;
      })
      .catch(() => (hasFailed.value = true))
      .finally(() => (isLoading.value = false));
  }

  function findCard(id: number): LeadCard | null {
    for (const column of columns.value) {
      const card = column.items.find((item) => item.id === id);

      if (card) return card;
    }

    return null;
  }

  function applyMove(move: LeadMove) {
    const source = columns.value.find((column) => column.items.some((item) => item.id === move.lead.id));
    const target = columns.value.find((column) => column.stage === move.lead.stage);

    if (source) {
      source.items = source.items.filter((item) => item.id !== move.lead.id);
      source.count = Math.max(0, source.count - 1);
    }

    if (!target) return;

    target.items = [move.lead, ...target.items];
    target.count += 1;
  }

  return { appliedSearch, applyMove, columns, findCard, hasFailed, isLoading, load };
}

export { useLeadBoard };
