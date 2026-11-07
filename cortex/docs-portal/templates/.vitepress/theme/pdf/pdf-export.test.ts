import { test } from 'node:test';
import assert from 'node:assert/strict';
import { access, readFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SIDEBAR } from '../../sidebar.ts';
import {
  PDF_HANDLED_COMPONENTS,
  PDF_INTERACTIVE_COMPONENTS,
  prepareMarkdownForPdf,
} from './prepareMarkdownForPdf.ts';
import { renderMarkdownToHtml } from './renderMarkdownToHtml.ts';
import {
  DEVELOPER_ONLY_PREFIXES,
  USER_DOC_GROUPS,
  buildUserDocGroups,
  flattenUserDocPages,
  linkToMarkdownPath,
} from './userDocPages.ts';

const themeDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const docsRoot = resolve(themeDir, '../..');

// --- prepareMarkdownForPdf ---------------------------------------------------

test('prepare: strips frontmatter, ::: dev and mermaid', () => {
  const out = prepareMarkdownForPdf(`---
title: Sample
---

# Title

::: dev
Secret for developers only.
:::

Visible user text.

\`\`\`mermaid
graph TD; A-->B;
\`\`\`
`);

  assert.match(out, /^# Title/);
  assert.match(out, /Visible user text/);
  assert.doesNotMatch(out, /title: Sample/);
  assert.doesNotMatch(out, /Secret for developers/);
  assert.doesNotMatch(out, /graph TD/);
  assert.match(out, /\*\*Diagram:\*\*/);
});

test('prepare: a container nested inside ::: dev does not end the dev block', () => {
  const out = prepareMarkdownForPdf(`
::: dev
Before the tip.

::: tip
Nested tip for developers.
:::

After the tip — still developer-only.
:::

User text after.
`);

  assert.doesNotMatch(out, /Before the tip/);
  assert.doesNotMatch(out, /Nested tip/);
  assert.doesNotMatch(out, /still developer-only/);
  assert.doesNotMatch(out, /^:::/m);
  assert.match(out, /User text after/);
});

test('prepare: ::: dev with no space and a mermaid fence inside leaves nothing behind', () => {
  const out = prepareMarkdownForPdf(`
:::dev
\`\`\`mermaid
graph TD; Hidden-->Diagram;
\`\`\`
:::

Shown.
`);

  assert.doesNotMatch(out, /Hidden/);
  assert.doesNotMatch(out, /Diagram:/);
  assert.equal(out, 'Shown.');
});

test('prepare: container markers and components inside a code fence are left alone', () => {
  const fence = '```md\n::: dev\nexample\n:::\n<SchemaBook />\n```';
  const out = prepareMarkdownForPdf(`Intro.\n\n${fence}\n\nOutro.`);

  assert.ok(out.includes(fence), out);
  assert.match(out, /Outro/);
});

test('prepare: <DevOnly> is removed and <UserOnly> is unwrapped', () => {
  const out = prepareMarkdownForPdf(`
<DevOnly>

Block developer detail.

</DevOnly>

Inline: <DevOnly>inline developer detail</DevOnly> end.

<UserOnly>

For the user.

</UserOnly>
`);

  assert.doesNotMatch(out, /developer detail/);
  assert.doesNotMatch(out, /DevOnly|UserOnly/);
  assert.match(out, /For the user/);
  assert.match(out, /Inline: {2}end\./);
});

test('prepare: replaces allowlisted components, self-closing and paired', () => {
  const out = prepareMarkdownForPdf(`
# Page

<SchemaBook module="auth" />

<EntityDiagram :modules="[
  'auth',
]">
  nested
</EntityDiagram>

<EndpointTable module="auth"></EndpointTable>
`);

  assert.equal((out.match(/Interactive view/g) ?? []).length, 3);
  assert.doesNotMatch(out, /SchemaBook|EntityDiagram|EndpointTable|nested/);
});

test('prepare: prose angle brackets are not treated as components', () => {
  const out = prepareMarkdownForPdf('Never use a raw `<DataTable>` or `<Dialog>` in the app.');

  assert.match(out, /<DataTable>/);
  assert.match(out, /<Dialog>/);
  assert.doesNotMatch(out, /Interactive view/);
});

test('prepare: links to exported pages become anchors; others are untouched', () => {
  const exported = new Set(['guide-getting-started']);
  const out = prepareMarkdownForPdf(
    '[Start](/guide/getting-started#top) · [Also](/guide/getting-started.md) · [Schema](/reference/database-schema) · [Web](https://example.com/guide/x)',
    exported,
  );

  assert.match(out, /\[Start\]\(#guide-getting-started\)/);
  assert.match(out, /\[Also\]\(#guide-getting-started\)/);
  assert.match(out, /\[Schema\]\(\/reference\/database-schema\)/);
  assert.match(out, /\[Web\]\(https:\/\/example\.com\/guide\/x\)/);
});

test('prepare: every component theme/index.ts registers is replaced or handled', async () => {
  const source = await readFile(resolve(themeDir, 'index.ts'), 'utf-8');
  const registered = [...source.matchAll(/app\.component\('(\w+)'/g)].map((match) => match[1]);
  const known = new Set<string>([...PDF_INTERACTIVE_COMPONENTS, ...PDF_HANDLED_COMPONENTS]);

  assert.ok(registered.length > 0, 'no app.component() registrations found');

  for (const name of registered) {
    assert.ok(known.has(name), `${name} is registered but the PDF export does not know it`);
  }
});

// --- renderMarkdownToHtml ----------------------------------------------------

test('render: unwraps ::: user and keeps callouts with their title', () => {
  const html = renderMarkdownToHtml(`
::: user
User-only paragraph.
:::

::: tip Before you start
Helpful tip
:::
`);

  assert.match(html, /pdf-user-block/);
  assert.match(html, /User-only paragraph/);
  assert.match(html, /pdf-callout pdf-tip/);
  assert.match(html, /pdf-callout-title">Before you start</);
  assert.match(html, /Helpful tip/);
});

test('render: a heading and the table after it are kept together', () => {
  const html = renderMarkdownToHtml(`
## Settings at a glance

| Col | Val |
| --- | --- |
| A | 1 |
`);

  assert.match(html, /<div class="pdf-keep-together"><h2>Settings at a glance<\/h2>\s*<table>/);
});

// --- userDocPages ------------------------------------------------------------

function sidebarLinks(items: typeof SIDEBAR): string[] {
  return items.flatMap((item) => [
    ...(item.link ? [item.link] : []),
    ...sidebarLinks(item.items ?? []),
  ]);
}

test('userDocPages: is the sidebar, in order, minus the developer reference', () => {
  const expected = sidebarLinks(SIDEBAR).filter(
    (link) => !DEVELOPER_ONLY_PREFIXES.some((prefix) => link.startsWith(prefix)),
  );
  const pages = flattenUserDocPages();

  assert.ok(pages.length > 0);
  assert.deepEqual(
    pages.map((page) => page.link),
    expected,
  );
  assert.equal(new Set(pages.map((page) => page.id)).size, pages.length, 'duplicate anchor ids');
});

test('userDocPages: never includes a developer-only page', () => {
  for (const page of flattenUserDocPages()) {
    for (const prefix of DEVELOPER_ONLY_PREFIXES) {
      assert.ok(!page.link.startsWith(prefix), `${page.link} is developer-only`);
    }
  }
});

test('userDocPages: every exported page has a markdown file', async () => {
  for (const page of flattenUserDocPages()) {
    await access(resolve(docsRoot, linkToMarkdownPath(page.link)));
  }
});

test('userDocPages: groups follow top-level entries, nested items flatten, dev prefixes drop', () => {
  const groups = buildUserDocGroups([
    { text: 'Home', link: '/' },
    {
      text: 'Guide',
      items: [
        { text: 'Intro', link: '/guide/intro' },
        { text: 'Area', link: '/guide/area/', items: [{ text: 'Detail', link: '/guide/area/detail' }] },
        { text: 'External', link: 'https://example.com' },
      ],
    },
    { text: 'Developer reference', items: [{ text: 'Schema', link: '/reference/schema' }] },
  ]);

  assert.deepEqual(
    groups.map((group) => [group.title, group.pages.map((page) => page.id)]),
    [
      ['Home', ['index']],
      ['Guide', ['guide-intro', 'guide-area', 'guide-area-detail']],
    ],
  );
  assert.equal(linkToMarkdownPath('/guide/area/'), 'guide/area/index.md');
  assert.equal(linkToMarkdownPath('/'), 'index.md');
  assert.equal(linkToMarkdownPath('/guide/intro'), 'guide/intro.md');
});

test('userDocPages: USER_DOC_GROUPS is what the flat list is built from', () => {
  assert.equal(
    flattenUserDocPages().length,
    USER_DOC_GROUPS.reduce((total, group) => total + group.pages.length, 0),
  );
});

// --- wiring ------------------------------------------------------------------

test('wiring: the page glob excludes exactly the developer-only folders', async () => {
  const source = await readFile(resolve(themeDir, 'components/PdfExportPage.vue'), 'utf-8');
  const excluded = [...source.matchAll(/'!\.\.\/\.\.\/\.\.\/([\w.-]+)\/\*\*'/g)].map((match) => match[1]);

  for (const prefix of DEVELOPER_ONLY_PREFIXES) {
    const folder = prefix.replace(/^\/|\/$/g, '');

    assert.ok(excluded.includes(folder), `PdfExportPage.vue glob does not exclude ${folder}/`);
  }

  assert.ok(excluded.includes('node_modules'), 'the glob must not read node_modules');
});

test('wiring: the nav link points at the page that mounts the export', async () => {
  const button = await readFile(resolve(themeDir, 'components/PdfExportButton.vue'), 'utf-8');
  const path = button.match(/const EXPORT_PATH = '([^']+)'/)?.[1];

  assert.ok(path, 'EXPORT_PATH not found in PdfExportButton.vue');

  const page = await readFile(resolve(docsRoot, linkToMarkdownPath(path)), 'utf-8');

  assert.match(page, /<PdfExportPage\s*\/>/);
});
