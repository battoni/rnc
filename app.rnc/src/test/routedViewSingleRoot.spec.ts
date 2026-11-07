import { describe, expect, it } from 'vitest';

/*
 * A routed view renders as the child of App.vue's `<Transition mode="out-in">`. A component with
 * more than one root node — two elements, or an element with a comment beside it — is
 * fragment-rooted, and the Transition cannot drive it: the leave never finishes, so the NEXT view
 * never mounts and the app goes blank until a reload. `TheCenteredLayout` carries the note about a
 * root-level comment for the same reason; this is the test that catches it everywhere.
 *
 * Found by the not-found e2e: leaving that screen blanked the app for every destination, signed in
 * or out. `TermsView` and `ForgotPassword` had it too.
 */
const VIEWS = import.meta.glob(['../views/**/*.vue', '../modules/*/views/**/*.vue'], {
  eager: true,
  import: 'default',
  query: '?raw',
});

// HELPERS
function templateOf(source: string): string {
  const START = source.indexOf('<template>');
  const END = source.lastIndexOf('</template>');

  return START < 0 || END < 0 ? '' : source.slice(START + '<template>'.length, END);
}

function rootNodeCount(template: string): number {
  let depth = 0;
  let roots = 0;
  let index = 0;

  while (index < template.length) {
    if (template.startsWith('<!--', index)) {
      const end = template.indexOf('-->', index);
      if (depth === 0) roots += 1;
      index = end < 0 ? template.length : end + 3;
      continue;
    }

    const tag = /^<(\/?)([A-Za-z][\w.-]*)([^>]*?)(\/?)>/.exec(template.slice(index));

    if (!tag) {
      index += 1;
      continue;
    }

    const [, closing, name = '', , selfClosing] = tag;
    const IS_VOID = ['br', 'hr', 'img', 'input'].includes(name.toLowerCase());

    if (closing) depth -= 1;
    else {
      if (depth === 0) roots += 1;
      if (!selfClosing && !IS_VOID) depth += 1;
    }

    index += tag[0].length;
  }

  return roots;
}

describe('a routed view has exactly one root node', () => {
  it('lists none with a sibling element or comment at the root', () => {
    const OFFENDERS = Object.entries(VIEWS)
      .filter(([file]) => file.endsWith('View.vue') || file.endsWith('.view.vue'))
      .map(([file, source]) => ({
        file: file.replace('../', 'src/'),
        roots: rootNodeCount(templateOf(source as string)),
      }))
      .filter(({ roots }) => roots > 1);

    expect(OFFENDERS).toEqual([]);
  });
});
