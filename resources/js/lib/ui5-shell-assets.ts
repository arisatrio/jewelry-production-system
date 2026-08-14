import { addCustomCSS } from '@ui5/webcomponents-base/dist/Theming.js';
import '@ui5/webcomponents-react/dist/Assets.js';

// Allow stacked logo + brand text in the ShellBar logo slot.
void addCustomCSS(
    'ui5-shellbar',
    `
  :host {
    --_ui5_shellbar_root_height: 4.25rem;
  }

  .ui5-shellbar-logo {
    overflow: visible;
    align-items: flex-start;
  }

  ::slotted([slot="logo"]) {
    max-height: none;
  }
  `,
);

// Compact SPK Fiori form spacing.
void addCustomCSS(
    'ui5-form',
    `
  .ui5-form-root {
    border-radius: 0;
  }

  .ui5-form-header {
    min-height: 2.25rem;
    padding: 0.5rem 0.75rem;
  }

  .ui5-form-layout {
    row-gap: 0.2rem;
    column-gap: 0.75rem;
    padding: 0.5rem 0.5rem 0.65rem;
  }

  .ui5-form-column {
    padding-top: 0.35rem;
    padding-bottom: 0.5rem;
  }

  .ui5-form-group-heading {
    height: 1.75rem;
    line-height: 1.75rem;
    margin-bottom: 0.15rem;
  }
  `,
);

void addCustomCSS(
    'ui5-form-item',
    `
  :host {
    margin-block: 0.1rem;
  }

  .ui5-form-item-layout {
    min-height: 2rem;
    padding-top: 0.1rem;
    padding-bottom: 0.1rem;
  }

  .ui5-form-item-label {
    padding-top: 0.35rem;
  }

  .ui5-form-item-content {
    padding: 0 0.15rem;
  }

  :host(.spkFioriDetailAlertItem) .ui5-form-item-layout,
  :host(.spkFioriNotesItem) .ui5-form-item-layout {
    grid-template-columns: minmax(0, 1fr) !important;
  }

  :host(.spkFioriDetailAlertItem) .ui5-form-item-label,
  :host(.spkFioriNotesItem) .ui5-form-item-label {
    display: none !important;
  }

  :host(.spkFioriDetailAlertItem) .ui5-form-item-content,
  :host(.spkFioriNotesItem) .ui5-form-item-content {
    padding: 0;
    width: 100%;
  }
  `,
);
