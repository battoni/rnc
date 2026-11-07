<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useData, withBase } from 'vitepress';
import { prepareMarkdownForPdf } from '../pdf/prepareMarkdownForPdf';
import { renderMarkdownToHtml } from '../pdf/renderMarkdownToHtml';
import { USER_DOC_GROUPS, flattenUserDocPages, linkToMarkdownPath } from '../pdf/userDocPages';

declare const __DOCS_VERSION__: string;

// The copy lives here, in one place, so a portal in another language
// translates one object. The product's name comes from the site config.
const COPY = {
  back: 'Back to the portal',
  printHint: 'In the print dialog, untick “Headers and footers” to hide the browser’s title and URL.',
  generate: 'Save as PDF',
  eyebrow: 'Documentation portal',
  title: 'User documentation',
  version: 'Documentation version',
  generatedAt: 'Generated on',
  outdatedLabel: 'Note:',
  outdatedBefore: 'this PDF may be out of date. The source of truth is always the',
  outdatedLink: 'online portal',
  outdatedAfter: '— use this file as a point-in-time reference only.',
  index: 'Contents',
  backToIndex: 'Back to contents',
  missing: 'Page not found:',
  footerNote: 'May be out of date · See the online portal',
} as const;

const GENERATED_AT_FORMAT: Intl.DateTimeFormatOptions = {
  day: '2-digit',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
};

const docsVersion = typeof __DOCS_VERSION__ !== 'undefined' ? __DOCS_VERSION__ : '0.0.0';

// Vite needs literal globs, so the developer-only folders are excluded here by
// hand; pdf-export.test.ts fails if this list and DEVELOPER_ONLY_PREFIXES drift.
const markdownModules = import.meta.glob<string>(
  [
    '../../../**/*.md',
    '!../../../node_modules/**',
    '!../../../.vitepress/**',
    '!../../../reference/**',
  ],
  { query: '?raw', import: 'default', eager: true },
);

const pages = flattenUserDocPages();

const { site, theme } = useData();

const generatedAt = ref('');

const documentTitle = computed(() => `${site.value.title} — ${COPY.title} — v${docsVersion}`);
const logoSrc = computed(() => {
  const logo = theme.value.logo;
  const source = typeof logo === 'string' ? logo : (logo?.light ?? logo?.src);

  return source ? withBase(source) : '';
});

const renderedSections = computed(() =>
  pages.map((page) => {
    const raw = findRawMarkdown(linkToMarkdownPath(page.link));
    const prepared = raw === null ? `_${COPY.missing} \`${page.link}\`_` : prepareMarkdownForPdf(raw);

    return { ...page, html: renderMarkdownToHtml(prepared) };
  }),
);

onMounted(onComponentMount);
onBeforeUnmount(onComponentUnmount);

// HELPERS
function findRawMarkdown(markdownPath: string): string | null {
  const entry = Object.entries(markdownModules).find(([key]) => key.endsWith(`/${markdownPath}`));

  return entry ? entry[1] : null;
}

function formatGeneratedAt(): string {
  return new Date().toLocaleString(site.value.lang || undefined, GENERATED_AT_FORMAT);
}

// EVENTS
function onComponentMount() {
  // The timestamp is set in the browser, not at build time: it is the moment
  // the reader saves the PDF that matters.
  generatedAt.value = formatGeneratedAt();
  document.documentElement.classList.add('pdf-export');
  document.title = documentTitle.value;
}

function onComponentUnmount() {
  document.documentElement.classList.remove('pdf-export');
}

function onGeneratePdf() {
  generatedAt.value = formatGeneratedAt();
  document.title = documentTitle.value;
  window.print();
}
</script>

<template>
  <div class="pdf-export-root">
    <div class="pdf-toolbar no-print">
      <a
        class="pdf-toolbar-link"
        :href="withBase('/')"
      >
        {{ COPY.back }}
      </a>

      <div class="pdf-toolbar-actions">
        <p class="pdf-toolbar-hint">
          {{ COPY.printHint }}
        </p>

        <button
          class="pdf-toolbar-btn"
          type="button"
          @click="onGeneratePdf"
        >
          {{ COPY.generate }}
        </button>
      </div>
    </div>

    <!--
      Print layout: the tfoot spacer repeats on every page and reserves room, and
      the fixed footer paints into that band, so content cannot run under it (Chrome).
    -->
    <table class="pdf-print-layout">
      <thead>
        <tr>
          <td>
            <div
              aria-hidden="true"
              class="pdf-print-header-space"
            ></div>
          </td>
        </tr>
      </thead>

      <tfoot>
        <tr>
          <td>
            <div
              aria-hidden="true"
              class="pdf-print-footer-space"
            ></div>
          </td>
        </tr>
      </tfoot>

      <tbody>
        <tr>
          <td class="pdf-print-layout-body">
            <header class="pdf-cover">
              <img
                v-if="logoSrc"
                class="pdf-cover-logo"
                :alt="site.title"
                :src="logoSrc"
              />

              <p class="pdf-cover-eyebrow">
                {{ COPY.eyebrow }}
              </p>

              <h1 class="pdf-cover-title">
                {{ COPY.title }}
              </h1>

              <p class="pdf-cover-subtitle">
                {{ site.title }}
              </p>

              <dl class="pdf-cover-meta">
                <div>
                  <dt>{{ COPY.version }}</dt>

                  <dd>{{ docsVersion }}</dd>
                </div>

                <div>
                  <dt>{{ COPY.generatedAt }}</dt>

                  <dd>{{ generatedAt }}</dd>
                </div>
              </dl>

              <aside
                class="pdf-outdated-banner"
                role="note"
              >
                <strong>{{ COPY.outdatedLabel }}</strong>

                {{ COPY.outdatedBefore }}

                <a :href="withBase('/')">{{ COPY.outdatedLink }}</a>

                {{ COPY.outdatedAfter }}
              </aside>
            </header>

            <nav
              class="pdf-toc"
              :aria-label="COPY.index"
            >
              <h2 id="pdf-contents">
                {{ COPY.index }}
              </h2>

              <ol class="pdf-toc-groups">
                <li
                  v-for="group in USER_DOC_GROUPS"
                  :key="group.title"
                >
                  <h3>{{ group.title }}</h3>

                  <ol>
                    <li
                      v-for="page in group.pages"
                      :key="page.id"
                    >
                      <a :href="`#${page.id}`">{{ page.title }}</a>
                    </li>
                  </ol>
                </li>
              </ol>
            </nav>

            <main class="pdf-body">
              <section
                v-for="section in renderedSections"
                class="pdf-section"
                :id="section.id"
                :key="section.id"
              >
                <header class="pdf-section-header">
                  <a
                    class="pdf-section-back"
                    href="#pdf-contents"
                  >
                    {{ COPY.backToIndex }}
                  </a>
                </header>

                <div
                  class="pdf-section-content vp-doc"
                  v-html="section.html"
                ></div>
              </section>
            </main>
          </td>
        </tr>
      </tbody>
    </table>

    <footer class="pdf-print-footer">
      <img
        v-if="logoSrc"
        alt=""
        class="pdf-print-footer-logo"
        :src="logoSrc"
      />

      <div class="pdf-print-footer-text">
        <span>© {{ site.title }}</span>

        <span>v{{ docsVersion }} · {{ COPY.footerNote }}</span>
      </div>
    </footer>
  </div>
</template>
