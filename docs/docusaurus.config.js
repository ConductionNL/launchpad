// @ts-check

/**
 * MyDash documentation site.
 *
 * Built on @conduction/docusaurus-preset for brand defaults (tokens,
 * theme swizzles for Navbar / Footer, four-locale i18n scaffolding,
 * KvK / BTW copyright). Site-specific overrides — locales, sidebar
 * path, mermaid theme, custom prism themes, mydash-only navbar items —
 * are passed through createConfig() opts.
 */

const { createConfig, baseFooterLinks } = require('@conduction/docusaurus-preset');

/* createConfig replaces themes wholesale when `themes:` is passed, so
   we re-include the brand theme plugin alongside @docusaurus/theme-mermaid.
   Without the brand theme entry the Navbar/Footer swizzles and
   brand.css auto-load would silently drop. */
const BRAND_THEME = require.resolve('@conduction/docusaurus-preset/theme');

const config = createConfig({
  title: 'MyDash',
  tagline: 'Your customizable dashboard for Nextcloud',
  url: 'https://mydash.conduction.nl',
  baseUrl: '/',

  organizationName: 'ConductionNL',
  projectName: 'mydash',

  /* English-only for now. Dutch was dropped on the previous config
     because i18n/nl/ carries stale strings without translated markdown
     and broke Dutch SSR on a handful of recent doc pages. Re-enable by
     adding 'nl' back once the Dutch translation pass has been completed
     or the metadata audited for stale references. The brand preset's
     default i18n block (nl/en/de/fr) is replaced wholesale here. */
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
    localeConfigs: {
      en: { label: 'English' },
    },
  },

  /* The mydash docs source lives at the repo root of `docs/` rather
     than under a `docs/` subfolder, so we override the preset's default
     `presets:` block to point `docs.path` at './' and disable the blog
     plugin. customCss carries mydash-specific CSS only — brand tokens
     and the theme swizzles are auto-loaded by the brand theme entry in
     `themes:` below. */
  presets: [
    [
      'classic',
      {
        docs: {
          path: './',
          exclude: ['**/node_modules/**'],
          sidebarPath: require.resolve('./sidebars.js'),
          editUrl: 'https://github.com/ConductionNL/mydash/tree/main/docs/',
        },
        blog: false,
        theme: {
          customCss: require.resolve('./src/css/custom.css'),
        },
      },
    ],
  ],

  themes: [BRAND_THEME, '@docusaurus/theme-mermaid'],

  /* Brand navbar provides locale dropdown + GitHub by default; we
     replace items[] with mydash's own (Documentation sidebar link,
     mydash GitHub link). Object.assign in createConfig is shallow, so
     items: replaces wholesale — re-include the locale dropdown and
     add the mydash GitHub repo link explicitly. */
  navbar: {
    items: [
      {
        type: 'docSidebar',
        sidebarId: 'tutorialSidebar',
        position: 'left',
        label: 'Documentation',
      },
      {
        href: 'https://github.com/ConductionNL/mydash',
        label: 'GitHub',
        position: 'right',
      },
      { type: 'localeDropdown', position: 'right' },
    ],
  },

  /* Per-property footer override (preset 1.2.0+): we pass `links` only,
     so the brand `style: 'dark'` and the brand KvK/BTW/IBAN/address
     copyright string both inherit unchanged. Mydash currently ships
     just the brand "Conduction" column; site-specific Product / Support
     columns will be added in a follow-up. */
  footer: {
    links: baseFooterLinks().filter((column) => column.title === 'Conduction'),
  },

  /* themeConfig is shallow-merged into the preset's defaults
     (colorMode + navbar + footer). prism + mermaid land alongside. */
  themeConfig: {
    prism: {
      theme: require('prism-react-renderer/themes/github'),
      darkTheme: require('prism-react-renderer/themes/dracula'),
    },
    mermaid: {
      theme: { light: 'default', dark: 'dark' },
    },
  },
});

/* createConfig doesn't pass-through arbitrary top-level fields; assign
   markdown + onBrokenAnchors + trailingSlash directly so they make it
   into the final Docusaurus config. trailingSlash is flipped to false
   because the previous config locked it that way and the GH Pages CNAME
   target depends on it (true would 301-redirect to /-suffix URLs). */
config.trailingSlash = false;
config.onBrokenAnchors = 'warn';
config.markdown = {
  mermaid: true,
  /* Tutorial pages reference screenshots that are populated by
     `tests/e2e/docs-screenshots.spec.ts`. The Playwright capture run is
     separate from the docs build, so the build needs to succeed even
     when a fresh checkout doesn't have every PNG yet. Warn instead of
     failing — the absence is visible at preview time and the capture
     spec brings everything back on demand. */
  hooks: {
    onBrokenMarkdownImages: 'warn',
  },
};

module.exports = config;
