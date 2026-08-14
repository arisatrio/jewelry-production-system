'use client';

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
