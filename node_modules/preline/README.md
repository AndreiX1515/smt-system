[![Hero Image](https://preline.co/hero-image-2.jpg)](https://preline.co)

<a href="https://preline.co"><img src="https://preline.co/preline-logo.svg" alt="Preline UI logo" width="200" height="auto"></a>

Preline UI is an open-source set of prebuilt, accessible UI components based on the utility-first Tailwind CSS framework.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## ✨ About Preline

Preline helps teams build modern websites and web apps faster with reusable [Tailwind CSS components](https://preline.co/docs/index.html), interactive [headless Tailwind CSS plugins](https://preline.co/plugins.html), [Tailwind CSS templates](https://preline.co/templates.html), and production-ready [Tailwind CSS examples](https://preline.co/examples.html). It is designed for developers who want flexible markup, scalable design systems, and polished UI patterns without rebuilding everything from scratch.

- [220+ free Tailwind CSS blocks and sections](https://preline.co/examples.html)
- [5 free Tailwind CSS templates](https://preline.co/templates.html)
- [27 headless Tailwind CSS plugins](https://preline.co/plugins.html)
- [Full documentation](https://preline.co/docs/index.html), [Figma](https://preline.co/figma.html), [themes](https://preline.co/docs/themes.html), and [agent skills](https://preline.co/docs/agent-skills.html)

## ⚡ Getting Started

Start with any working [Tailwind CSS](https://tailwindcss.com/) project and make sure [Node.js](https://nodejs.org/en/) and [npm](https://www.npmjs.com/) are installed.

### Install via npm

1. Install `preline`:

```bash
npm i preline
```

2. Import the Preline CSS variants file into your Tailwind CSS file after the `tailwindcss` import:

```css
@import "tailwindcss";

/* Preline UI */
@source "./node_modules/preline/dist/*.js";
@import "./node_modules/preline/variants.css";

/* Preline Themes */
@import "./themes/theme.css";
```

3. Add the Preline JavaScript file near the end of your `<body>` tag:

```html
<script src="./node_modules/preline/dist/preline.js"></script>
```

For setup details, framework integration, and configuration guides, visit the [Preline documentation](https://preline.co/docs/index.html).

## 🤖 Agent Skills

Preline UI includes [Agent Skills](https://preline.co/docs/agent-skills.html) for agentic coding tools such as Cursor, Claude Code, and Gemini CLI, making it easier to automate theme generation and UI workflows.

Install via CLI:

```bash
npx skills add htmlstreamofficial/preline
```

## ♿ Accessibility

Preline UI includes enterprise-grade accessibility built into its components, helping teams create more inclusive interfaces with accessible Tailwind CSS components, keyboard-friendly interactions, proper focus management, and stronger support for assistive technologies. Learn more in the dedicated [Accessibility documentation](https://preline.co/docs/accessibility.html).

## 🧩 Headless Tailwind CSS Plugins

Explore [headless Tailwind CSS plugins](https://preline.co/plugins.html) for accessible UI behavior, interactions, forms, navigation, overlays, and productivity workflows.

| Category | Plugin Pages |
| --- | --- |
| Disclosure | [Accordion](https://preline.co/plugins/html/accordion.html), [Collapse](https://preline.co/plugins/html/collapse.html), [Tree View](https://preline.co/plugins/html/tree-view.html) |
| Navigations | [Tabs](https://preline.co/plugins/html/tabs.html), [Scrollspy](https://preline.co/plugins/html/scrollspy.html), [Scroll Nav](https://preline.co/plugins/html/scroll-nav.html), [Stepper](https://preline.co/plugins/html/stepper.html) |
| Overlays | [Dropdown](https://preline.co/plugins/html/dropdown.html), [Overlay](https://preline.co/plugins/html/overlay.html), [Tooltip](https://preline.co/plugins/html/tooltip.html) |
| Forms | [Select](https://preline.co/plugins/html/advanced-select.html), [ComboBox](https://preline.co/plugins/html/combobox.html), [Datepicker](https://preline.co/plugins/html/advanced-datepicker.html), [Range Slider](https://preline.co/plugins/html/advanced-range-slider.html), [Input Number](https://preline.co/plugins/html/input-number.html), [File Upload](https://preline.co/plugins/html/file-upload.html), [Strong Password](https://preline.co/plugins/html/strong-password.html), [Toggle Password](https://preline.co/plugins/html/toggle-password.html), [Toggle Count](https://preline.co/plugins/html/toggle-count.html), [Copy Markup](https://preline.co/plugins/html/copy-markup.html), [PIN Input](https://preline.co/plugins/html/pin-input.html), [Textarea Auto Height](https://preline.co/plugins/html/textarea-autoheight.html) |
| Miscellaneous | [DataTable](https://preline.co/plugins/html/datatables.html), [Carousel](https://preline.co/plugins/html/carousel.html), [Layout Splitter](https://preline.co/plugins/html/layout-splitter.html), [Remove Element](https://preline.co/plugins/html/remove-element.html), [Theme Switch](https://preline.co/plugins/html/theme-switch.html) |

## 🧱 Tailwind CSS Components

Browse [Tailwind CSS component docs](https://preline.co/docs/index.html) across layout, base UI, forms, navigation, overlays, tables, and advanced integrations.

| Category | Component Pages |
| --- | --- |
| Layout & Content | [Container](https://preline.co/docs/container.html), [Columns](https://preline.co/docs/columns.html), [Grid](https://preline.co/docs/grid.html), [Layout Splitter](https://preline.co/docs/layout-splitter.html), [Typography](https://preline.co/docs/typography.html), [Images](https://preline.co/docs/images.html), [Links](https://preline.co/docs/links.html), [Dividers & HR](https://preline.co/docs/dividers.html), [KBD](https://preline.co/docs/kbd.html), [Custom Scrollbar](https://preline.co/docs/custom-scrollbar.html) |
| Base Components | [Accordion](https://preline.co/docs/accordion.html), [Alerts](https://preline.co/docs/alerts.html), [Avatar](https://preline.co/docs/avatar.html), [Avatar Group](https://preline.co/docs/avatar-group.html), [Badge](https://preline.co/docs/badge.html), [Blockquote](https://preline.co/docs/blockquote.html), [Buttons](https://preline.co/docs/buttons.html), [Button Group](https://preline.co/docs/button-group.html), [Card](https://preline.co/docs/card.html), [Chat Bubbles](https://preline.co/docs/chat-bubbles.html), [Carousel](https://preline.co/docs/carousel.html), [Collapse](https://preline.co/docs/collapse.html), [Datepicker](https://preline.co/docs/datepicker.html), [Devices](https://preline.co/docs/devices.html), [Lists](https://preline.co/docs/lists.html), [List Group](https://preline.co/docs/list-group.html), [Legend Indicator](https://preline.co/docs/legend-indicator.html), [Progress](https://preline.co/docs/progress.html), [File Uploading Progress](https://preline.co/docs/file-uploading-progress-form.html), [Ratings](https://preline.co/docs/ratings.html), [Skeleton](https://preline.co/docs/skeleton.html), [Spinners](https://preline.co/docs/spinners.html), [Styled Icons](https://preline.co/docs/static-icons.html), [Toasts](https://preline.co/docs/toasts.html), [Timeline](https://preline.co/docs/timeline.html), [Tree View](https://preline.co/docs/tree-view.html) |
| Navigations | [Navbar](https://preline.co/docs/navbar.html), [Mega Menu](https://preline.co/docs/mega-menu.html), [Navs](https://preline.co/docs/navs.html), [Tabs](https://preline.co/docs/tabs.html), [Sidebar](https://preline.co/docs/sidebar.html), [Scrollspy](https://preline.co/docs/scrollspy.html), [Breadcrumb](https://preline.co/docs/breadcrumb.html), [Pagination](https://preline.co/docs/pagination.html), [Stepper](https://preline.co/docs/stepper.html) |
| Basic Forms | [Input](https://preline.co/docs/input.html), [Input Group](https://preline.co/docs/input-group.html), [Textarea](https://preline.co/docs/textarea.html), [File Input](https://preline.co/docs/file-input.html), [Checkbox](https://preline.co/docs/checkbox.html), [Radio](https://preline.co/docs/radio.html), [Switch](https://preline.co/docs/switch.html), [Select](https://preline.co/docs/select.html), [Range Slider](https://preline.co/docs/range-slider.html), [Color Picker](https://preline.co/docs/color-picker.html), [Time Picker](https://preline.co/docs/time-picker.html) |
| Advanced Forms | [Advanced Select](https://preline.co/docs/advanced-select.html), [ComboBox](https://preline.co/docs/combobox.html), [SearchBox](https://preline.co/docs/searchbox.html), [Input Number](https://preline.co/docs/input-number.html), [Strong Password](https://preline.co/docs/strong-password.html), [Toggle Password](https://preline.co/docs/toggle-password.html), [Toggle Count](https://preline.co/docs/toggle-count.html), [Copy Markup](https://preline.co/docs/copy-markup.html), [PIN Input](https://preline.co/docs/pin-input.html) |
| Overlays | [Dropdown](https://preline.co/docs/dropdown.html), [Context Menu](https://preline.co/docs/context-menu.html), [Modal](https://preline.co/docs/modal.html), [Offcanvas](https://preline.co/docs/offcanvas.html), [Popover](https://preline.co/docs/popover.html), [Tooltip](https://preline.co/docs/tooltip.html) |
| Tables | [Tables](https://preline.co/docs/tables.html) |
| Third-Party Plugins | [Advanced Range Slider](https://preline.co/docs/advanced-range-slider.html), [Advanced Datepicker](https://preline.co/docs/advanced-datepicker.html), [Charts](https://preline.co/docs/charts.html), [Clipboard](https://preline.co/docs/clipboard.html), [Confetti Animation](https://preline.co/docs/confetti.html), [Datamaps](https://preline.co/docs/datamaps.html), [Datatables](https://preline.co/docs/datatables.html), [Drag and Drop](https://preline.co/docs/drag-and-drop.html), [File Upload](https://preline.co/docs/file-upload.html), [Maps](https://preline.co/docs/maps.html), [Toast Notifications](https://preline.co/docs/toast-notifications.html), [WYSIWYG Editor](https://preline.co/docs/text-editor.html) |

## 🎨 Templates and Examples

Explore free and premium layouts for landing pages, dashboards, SaaS apps, ecommerce stores, CMS products, portfolios, and more.

### Free Tailwind CSS Templates

| Template | Template | Template | Template | Template |
| --- | --- | --- | --- | --- |
| [Agency](https://preline.co/templates/agency/) | [AI Chat](https://preline.co/templates/ai-chat/) | [CMS](https://preline.co/templates/cms/) | [Coffee Shop](https://preline.co/templates/coffee-shop/) | [Personal](https://preline.co/templates/personal/) |

### Explore More

- [Free Tailwind CSS Blocks and Sections](https://preline.co/examples.html)
- [Free Tailwind CSS Templates](https://preline.co/templates.html)

## 🚀 Preline Pro

[Preline Pro](https://preline.co/pro/index.html) extends the free library with premium UI for serious product teams and commercial apps. It includes [780+ premium Tailwind CSS blocks and sections](https://preline.co/pro/examples.html), [21 premium Tailwind CSS templates](https://preline.co/pro/templates.html), and over 300 pages for admin dashboards, SaaS products, ecommerce, CMS, CRM, analytics, finance, chat, startup, and marketing use cases.

- [Preline Pro overview](https://preline.co/pro/index.html)
- [Premium Tailwind CSS Blocks and Sections](https://preline.co/pro/examples.html)
- [Premium Tailwind CSS Templates](https://preline.co/pro/templates.html)

## 📚 Documentation and Resources

- [Preline UI documentation](https://preline.co/docs/index.html)
- [Preline JavaScript](https://preline.co/docs/preline-javascript.html)
- [Framework guides](https://preline.co/docs/frameworks.html)
- [Accessibility](https://preline.co/docs/accessibility.html)
- [Themes](https://preline.co/docs/themes.html)
- [Resources](https://preline.co/docs/resources.html)
- [Figma UI kit](https://preline.co/figma.html)

## 🤝 Community

For help, best practices, and product discussions, use [GitHub Discussions](https://github.com/htmlstreamofficial/preline/discussions).

Follow [Preline on X](https://x.com/prelineUI) and support the project on [Product Hunt](https://www.producthunt.com/products/preline-ui/reviews).

## 📄 License

Preline UI is free for personal and commercial projects under the [MIT License](https://preline.co/docs/license.html) and the [Preline UI Fair Use License](https://preline.co/docs/license.html). Copyright 2026 by Preline Labs Ltd.

The [Preline UI Figma](https://preline.co/figma.html) resources are also available for personal and commercial use. All brand icons are trademarks of their respective owners, and their use does not imply endorsement.

## A Product of Htmlstream

Preline UI is built by the [Htmlstream](https://htmlstream.com) team, crafting UI components and templates since 2013—helping teams ship faster with scalable, flexible design systems for real-world products.

Share your thoughts about Preline on [Twitter](https://x.com/prelineUI) or leave supportive review on [ProductHunt](https://www.producthunt.com/products/preline-ui/reviews).
