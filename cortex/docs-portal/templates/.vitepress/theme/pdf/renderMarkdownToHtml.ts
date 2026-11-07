/**
 * Render prepared markdown to HTML for the PDF. The site's own markdown
 * pipeline runs at build time per page; the export concatenates raw sources in
 * the browser, so it renders them again with a plain markdown-it and its own
 * container set.
 */
import MarkdownIt from 'markdown-it';
import container from 'markdown-it-container';

type ContainerToken = { nesting: number; info: string };

const CALLOUT_CONTAINERS = ['tip', 'info', 'warning', 'danger', 'details'] as const;

function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function calloutRender(name: string) {
  return (tokens: ContainerToken[], index: number): string => {
    if (tokens[index].nesting !== 1) return '</div>\n';

    // `::: tip Before you start` — keep the custom title the page gave it.
    const title = tokens[index].info.trim().slice(name.length).trim();
    const heading = title ? `<p class="pdf-callout-title">${escapeHtml(title)}</p>\n` : '';

    return `<div class="pdf-callout pdf-${name}">\n${heading}`;
  };
}

function createPdfMarkdown(): MarkdownIt {
  const markdown = new MarkdownIt({ html: true, linkify: true, typographer: true });

  for (const name of CALLOUT_CONTAINERS) {
    markdown.use(container, name, { render: calloutRender(name) });
  }

  // The export is the user's copy: `::: user` content prints, unwrapped.
  markdown.use(container, 'user', {
    render(tokens: ContainerToken[], index: number) {
      return tokens[index].nesting === 1 ? '<div class="pdf-user-block">\n' : '</div>\n';
    },
  });

  // `::: dev` is removed before rendering; a leftover renders nothing but its
  // wrapper, so a miss upstream is an empty box rather than a markdown-it error.
  markdown.use(container, 'dev', {
    render(tokens: ContainerToken[], index: number) {
      return tokens[index].nesting === 1 ? '<div class="pdf-dev-leftover" hidden>\n' : '</div>\n';
    },
  });

  return markdown;
}

const PDF_MARKDOWN = createPdfMarkdown();

/**
 * A heading must not be the last line of a page with its table on the next.
 * `break-after: avoid` alone is not honoured by every engine before a table,
 * so the heading and the table it introduces are wrapped together.
 */
function keepHeadingWithNext(html: string): string {
  const headingThenTable = /(<h([2-4])\b[^>]*>[\s\S]*?<\/h\2>)(\s*)(<table\b[\s\S]*?<\/table>)/gi;

  return html.replace(headingThenTable, '<div class="pdf-keep-together">$1$3$4</div>');
}

function renderMarkdownToHtml(source: string): string {
  return keepHeadingWithNext(PDF_MARKDOWN.render(source));
}

export { renderMarkdownToHtml };
