import { ref } from 'vue';
import type { BoardBrandOption, BoardColumn, LeadCard, LeadMove } from '../interfaces';
import { GetLeadBoardService } from '../services';

/**
 * The board's state (rnc-1 R18): the columns as the API answered them, the search they answered, and
 * the granular update after a move — the card leaves its column and lands on top of the new one,
 * each count following, without asking for the board again. The brand filter (rnc-3 R4) rides with
 * the search, and the board's own answer says which sub-brands there are to filter by.
 */
function useLeadBoard() {
  const appliedBrand = ref<string | null>(null);
  const appliedSearch = ref('');
  const brands = ref<BoardBrandOption[]>([]);
  const columns = ref<BoardColumn[]>([]);
  const hasFailed = ref(false);
  const isLoading = ref(false);

  // HELPERS
  function load(search: string, brand: string | null = appliedBrand.value): Promise<unknown> {
    isLoading.value = true;
    hasFailed.value = false;

    return GetLeadBoardService(search, brand)
      .then((response) => {
        brands.value = response.data.brands ?? [];
        columns.value = response.data.columns;
        appliedBrand.value = brand;
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

  return { appliedBrand, appliedSearch, applyMove, brands, columns, findCard, hasFailed, isLoading, load };
}

export { useLeadBoard };
