import { parse } from '@vue/compiler-sfc';
import { describe, expect, it } from 'vitest';

/*
 * An element with both a static `class` and a bound `:class` is two attributes for one thing
 * (celer-04, "Unified class / :class binding"). ESLint does not see it, and it came back in
 * AFormError, MWalletCard and OListFeed after the rule was written, and in BrandView — a module
 * screen the first version of this scan did not read. Every template in the app is scanned:
 * components, layouts, modules, views and `App.vue`.
 */

// `NodeTypes` is a const enum in `@vue/compiler-core` and is not exported at runtime by compiler-sfc.
const NODE_TYPE = { ATTRIBUTE: 6, DIRECTIVE: 7, ELEMENT: 1, SIMPLE_EXPRESSION: 4 } as const;

/** The slice of compiler-core's AST this scan reads; compiler-sfc does not re-export the types. */
interface TemplateProp {
  arg?: { content?: string; type: number };
  name: string;
  type: number;
}

interface TemplateNode {
  children?: TemplateNode[];
  loc: { start: { line: number } };
  props?: TemplateProp[];
  tag?: string;
  type: number;
}

const SOURCES = {
  ...import.meta.glob('/src/components/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/layouts/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/modules/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/views/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/App.vue', { query: '?raw', import: 'default', eager: true }),
} as Record<string, string>;

function splitClassElements(nodes: TemplateNode[]): string[] {
  return nodes.flatMap((node) => {
    if (node.type !== NODE_TYPE.ELEMENT) return [];

    const hasStatic = (node.props ?? []).some((prop) => prop.type === NODE_TYPE.ATTRIBUTE && prop.name === 'class');
    const hasBound = (node.props ?? []).some(
      (prop) =>
        prop.type === NODE_TYPE.DIRECTIVE &&
        prop.name === 'bind' &&
        prop.arg?.type === NODE_TYPE.SIMPLE_EXPRESSION &&
        prop.arg.content === 'class'
    );
    const own = hasStatic && hasBound ? [`<${node.tag}> at line ${node.loc.start.line}`] : [];

    return [...own, ...splitClassElements(node.children ?? [])];
  });
}

function offendersIn(source: string) {
  const template = parse(source).descriptor.template;

  return template ? splitClassElements(template.ast!.children as unknown as TemplateNode[]) : [];
}

describe('class and :class are one binding', () => {
  it('no template in the app gives an element both', () => {
    const offenders = Object.entries(SOURCES).flatMap(([path, source]) =>
      offendersIn(source).map((element) => `${path}: ${element}`)
    );

    expect(offenders).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    expect(offendersIn('<template><div><i class="a" :class="b" /></div></template>')).toHaveLength(1);
    expect(offendersIn('<template><i :class="[\'a\', b]" /></template>')).toEqual([]);
  });
});
