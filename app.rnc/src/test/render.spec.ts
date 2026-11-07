import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import { renderWithPlugins } from './render';

/*
 * The integration harness has to show what a view actually renders. Two gaps it used to have, both
 * silent: a view's `#primaryAction` (the one button a screen is for) vanished inside the
 * `ThePageHeader` stub with no error, and every `v-tooltip` text was dropped, so no integration spec
 * could assert one.
 */
const VIEW = defineComponent({
  template: `
    <TheLayout>
      <template #pageHeader>
        <ThePageHeader title="users.title">
          <template #leading><span>leading-slot</span></template>
          <template #titleSuffix><span>suffix-slot</span></template>
          <template #primaryAction><button type="button">primary-slot</button></template>
          <template #actions><button type="button">actions-slot</button></template>
        </ThePageHeader>
      </template>

      <button v-tooltip="'Say it'" type="button">with-tooltip</button>
    </TheLayout>
  `,
});

describe('renderWithPlugins', () => {
  it('renders every slot a view gives ThePageHeader', () => {
    const { getByText } = renderWithPlugins(VIEW);

    expect(getByText('leading-slot')).toBeInTheDocument();
    expect(getByText('suffix-slot')).toBeInTheDocument();
    expect(getByText('primary-slot')).toBeInTheDocument();
    expect(getByText('actions-slot')).toBeInTheDocument();
  });

  it('mirrors a tooltip onto the element, like mountWithPlugins', () => {
    const { getByText } = renderWithPlugins(VIEW);

    expect(getByText('with-tooltip')).toHaveAttribute('data-tooltip', 'Say it');
  });
});
