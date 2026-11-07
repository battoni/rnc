/**
 * The user-facing pages the PDF export prints, DERIVED from the sidebar — never
 * listed by hand. A page added to the sidebar is in the next PDF; a page
 * removed from it is gone. Order is sidebar order (sidebar order = reading order).
 *
 * Everything under a developer-only prefix is left out: the export is the
 * user's copy, and the developer reference is generated tables that print badly
 * and date fast.
 */
import type { DefaultTheme } from 'vitepress';
import { SIDEBAR } from '../../sidebar.ts';

type UserDocPage = {
  /** Stable DOM id / TOC anchor (e.g. guide-getting-started). */
  id: string;
  /** Sidebar label. */
  title: string;
  /** Site path as the sidebar spells it (e.g. /guide/getting-started). */
  link: string;
};

type UserDocGroup = {
  title: string;
  pages: UserDocPage[];
};

/**
 * Path prefixes that are developer-only and never printed. PdfExportPage.vue's
 * `import.meta.glob` must exclude the same folders (Vite needs literal globs,
 * so it cannot read this constant) — pdf-export.test.ts holds the two together.
 */
const DEVELOPER_ONLY_PREFIXES = ['/reference/'] as const;

function linkToId(link: string): string {
  return link.replace(/#.*$/, '').replace(/^\/|\/$/g, '').replace(/\//g, '-') || 'index';
}

function isUserLink(link: string): boolean {
  const isExternal = /^[a-z]+:/i.test(link);

  if (isExternal || !link.startsWith('/')) return false;

  return !DEVELOPER_ONLY_PREFIXES.some((prefix) => link.startsWith(prefix));
}

function collectPages(items: DefaultTheme.SidebarItem[]): UserDocPage[] {
  return items.flatMap((item) => {
    const own = item.link && isUserLink(item.link)
      ? [{ id: linkToId(item.link), title: item.text ?? item.link, link: item.link }]
      : [];

    return [...own, ...collectPages(item.items ?? [])];
  });
}

/**
 * Top-level sidebar entries become the PDF's index groups. A top-level link
 * with no group of its own goes into a group titled with its own text.
 */
function buildUserDocGroups(sidebar: DefaultTheme.SidebarItem[]): UserDocGroup[] {
  return sidebar
    .map((entry) => ({ title: entry.text ?? '', pages: collectPages([entry]) }))
    .filter((group) => group.pages.length > 0);
}

const USER_DOC_GROUPS: UserDocGroup[] = buildUserDocGroups(SIDEBAR);

/** Flat list in sidebar order — used for the PDF body. */
function flattenUserDocPages(groups: UserDocGroup[] = USER_DOC_GROUPS): UserDocPage[] {
  return groups.flatMap((group) => group.pages);
}

/** Map a site link to its markdown path relative to the docs root. */
function linkToMarkdownPath(link: string): string {
  const path = link.replace(/^\//, '').replace(/#.*$/, '');

  return path === '' || path.endsWith('/') ? `${path}index.md` : `${path}.md`;
}

export {
  DEVELOPER_ONLY_PREFIXES,
  USER_DOC_GROUPS,
  buildUserDocGroups,
  flattenUserDocPages,
  linkToId,
  linkToMarkdownPath,
};
export type { UserDocGroup, UserDocPage };
