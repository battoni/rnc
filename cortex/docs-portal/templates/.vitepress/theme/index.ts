import DefaultTheme from 'vitepress/theme';
import { h } from 'vue';
import type { Theme } from 'vitepress';
import ModeToggle from './components/ModeToggle.vue';
import PdfExportButton from './components/PdfExportButton.vue';
import PdfExportPage from './components/PdfExportPage.vue';
import DevOnly from './components/DevOnly.vue';
import UserOnly from './components/UserOnly.vue';
import EndpointTable from './components/EndpointTable.vue';
import SchemaBook from './components/SchemaBook.vue';
import ViewNavMap from './components/ViewNavMap.vue';
import EntityDiagram from './components/EntityDiagram.vue';
import MermaidRender from './components/MermaidRender.vue';
import ReachabilityMap from './components/ReachabilityMap.vue';
import 'primeicons/primeicons.css';
import './styles/brand.css';
import './styles/mode.css';
import './styles/components.css';
import './styles/pdf-export.css';

export default {
  extends: DefaultTheme,
  Layout() {
    // Inject the User/Developer toggle and the PDF export link into the top nav bar.
    return h(DefaultTheme.Layout, null, {
      'nav-bar-content-after': () => [h(ModeToggle), h(PdfExportButton)],
    });
  },
  enhanceApp({ app }) {
    // Audience toggle helpers.
    app.component('DevOnly', DevOnly);
    app.component('UserOnly', UserOnly);
    // The /export-pdf page (export-pdf.md) mounts this.
    app.component('PdfExportPage', PdfExportPage);
    // Data components (fed by .vitepress/data/*.json from the extractors).
    // A new one here must also be listed in theme/pdf/prepareMarkdownForPdf.ts.
    app.component('EndpointTable', EndpointTable);
    app.component('SchemaBook', SchemaBook);
    app.component('ViewNavMap', ViewNavMap);
    // Diagrams: an ER per module from the schema, and every screen grouped by how it is reached.
    app.component('EntityDiagram', EntityDiagram);
    app.component('MermaidRender', MermaidRender);
    app.component('ReachabilityMap', ReachabilityMap);
  },
} satisfies Theme;
