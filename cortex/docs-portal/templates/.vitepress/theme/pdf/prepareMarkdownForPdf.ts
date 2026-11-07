/**
 * Prepare a portal page's markdown for the user PDF: drop the frontmatter and
 * everything developer-only (`::: dev` blocks, `<DevOnly>`), replace what only
 * works in a browser (data components, mermaid) with a pointer to the portal,
 * and turn links between exported pages into in-PDF anchors.
 */
import { flattenUserDocPages, linkToId } from './userDocPages.ts';

/**
 * Data/diagram components registered in theme/index.ts. They render from JSON
 * or run client-side, so the PDF shows a pointer instead. pdf-export.test.ts
 * fails when theme/index.ts registers a component that is neither here nor in
 * PDF_HANDLED_COMPONENTS.
 */
const PDF_INTERACTIVE_COMPONENTS = [
  'EndpointTable',
  'EntityDiagram',
  'MermaidRender',
  'ReachabilityMap',
  'SchemaBook',
  'ViewNavMap',
] as const;

/** Registered components the export handles on purpose rather than replacing. */
const PDF_HANDLED_COMPONENTS = ['DevOnly', 'UserOnly', 'PdfExportPage'] as const;

const INTERACTIVE_PLACEHOLDER = '\n\n> **Interactive view:** see the online portal.\n\n';
const DIAGRAM_PLACEHOLDER = '\n\n> **Diagram:** see the online portal for this diagram.\n\n';

const DEVELOPER_CONTAINER = 'dev';

const FENCE_OPEN = /^\s*(`{3,}|~{3,})\s*([\w-]*)/;
const CONTAINER_OPEN = /^\s*:{3,}\s*([\w-]+)/;
const CONTAINER_CLOSE = /^\s*:{3,}\s*$/;
const DEV_ONLY_OPEN = /^\s*<DevOnly\b[^>]*>\s*$/;
const DEV_ONLY_CLOSE = /^\s*<\/DevOnly>\s*$/;

type Segment = { isCode: boolean; text: string };

function stripFrontmatter(source: string): string {
  return source.replace(/^---\r?\n[\s\S]*?\r?\n---\r?\n/, '');
}

function closesFence(line: string, marker: string): boolean {
  const trimmed = line.trim();

  return trimmed.length >= marker.length && /^(`+|~+)$/.test(trimmed) && trimmed[0] === marker[0];
}

/**
 * Walk the page line by line. A regex cannot do this: `::: dev` may hold a
 * `::: tip` of its own, and a lazy match ends the dev block at the tip's
 * closing `:::` — printing the rest of the developer content to the user.
 * Code fences are passed through untouched, so a `:::` shown as an example is
 * not mistaken for a container.
 */
function splitAndStripDeveloperBlocks(source: string): Segment[] {
  const segments: Segment[] = [];
  const containerStack: string[] = [];
  let developerDepth = -1;
  let devOnlyDepth = 0;
  let fence: { marker: string; isMermaid: boolean } | null = null;

  function isDropping(): boolean {
    return developerDepth >= 0 || devOnlyDepth > 0;
  }

  function push(isCode: boolean, text: string) {
    const last = segments[segments.length - 1];

    if (last && last.isCode === isCode) {
      last.text += `${text}\n`;

      return;
    }

    segments.push({ isCode, text: `${text}\n` });
  }

  for (const line of source.split(/\r?\n/)) {
    if (fence) {
      const isClosing = closesFence(line, fence.marker);
      const keepsLine = !isDropping() && !fence.isMermaid;

      if (keepsLine) push(true, line);

      if (isClosing && fence.isMermaid && !isDropping()) push(false, DIAGRAM_PLACEHOLDER);

      if (isClosing) fence = null;

      continue;
    }

    const fenceMatch = line.match(FENCE_OPEN);

    if (fenceMatch) {
      fence = { marker: fenceMatch[1], isMermaid: fenceMatch[2] === 'mermaid' };

      if (!isDropping() && !fence.isMermaid) push(true, line);

      continue;
    }

    const containerMatch = line.match(CONTAINER_OPEN);

    if (containerMatch) {
      containerStack.push(containerMatch[1]);

      const opensDeveloperBlock = developerDepth < 0 && containerMatch[1] === DEVELOPER_CONTAINER;

      if (opensDeveloperBlock) developerDepth = containerStack.length;

      if (!isDropping()) push(false, line);

      continue;
    }

    if (CONTAINER_CLOSE.test(line) && containerStack.length > 0) {
      const closingDepth = containerStack.length;
      const wasDropping = isDropping();

      containerStack.pop();

      if (closingDepth === developerDepth) developerDepth = -1;

      if (!wasDropping) push(false, line);

      continue;
    }

    if (DEV_ONLY_OPEN.test(line)) {
      devOnlyDepth += 1;

      continue;
    }

    if (DEV_ONLY_CLOSE.test(line) && devOnlyDepth > 0) {
      devOnlyDepth -= 1;

      continue;
    }

    if (!isDropping()) push(false, line);
  }

  return segments;
}

function stripInlineDevOnly(prose: string): string {
  return prose.replace(/<DevOnly\b[^>]*>[\s\S]*?<\/DevOnly>/g, '');
}

function unwrapUserOnly(prose: string): string {
  return prose.replace(/<\/?UserOnly\b[^>]*>/g, '');
}

/**
 * Replace the known interactive components with a portal pointer. An allowlist
 * and a closing-tag backreference, so prose such as `<DataTable>` and
 * mismatched nested tags are left alone.
 */
function stripInteractiveComponents(prose: string): string {
  const names = PDF_INTERACTIVE_COMPONENTS.join('|');
  const selfClosing = new RegExp(`<(${names})\\b[^>]*/>`, 'g');
  const paired = new RegExp(`<(${names})\\b[^>]*>[\\s\\S]*?</\\1>`, 'g');

  return prose.replace(selfClosing, INTERACTIVE_PLACEHOLDER).replace(paired, INTERACTIVE_PLACEHOLDER);
}

/**
 * A link to another exported page becomes an anchor inside the PDF. A link to
 * anything else (the developer reference, an external site) is left as it is —
 * the browser prints it as an absolute link back to the portal.
 */
function rewriteInternalLinks(prose: string, exportedIds: Set<string>): string {
  return prose.replace(/\]\((\/[^)#\s]*)(#[^)\s]*)?\)/g, (match, path: string) => {
    const id = linkToId(path.replace(/\.(md|html)$/, ''));

    return exportedIds.has(id) ? `](#${id})` : match;
  });
}

function prepareMarkdownForPdf(
  source: string,
  exportedIds: Set<string> = new Set(flattenUserDocPages().map((page) => page.id)),
): string {
  const segments = splitAndStripDeveloperBlocks(stripFrontmatter(source));

  return segments
    .map((segment) => {
      if (segment.isCode) return segment.text;

      let prose = stripInlineDevOnly(segment.text);

      prose = unwrapUserOnly(prose);
      prose = stripInteractiveComponents(prose);

      return rewriteInternalLinks(prose, exportedIds);
    })
    .join('')
    .trim();
}

export { PDF_HANDLED_COMPONENTS, PDF_INTERACTIVE_COMPONENTS, prepareMarkdownForPdf };
