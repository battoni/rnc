import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

// Button severity mappings. The fills reference tokens that resolve per scheme ({success.hover.color}
// is 600 in light and 400 in dark), so the LABELS cannot be shared: every label here was chosen by
// measuring it on the fill it sits on — rest, hover and active — against the 4.5:1 floor for normal
// text. The table, with the numbers, is in `styles/theme/RULES.md` ("What sits ON a fill"), and
// `e2e/theme/contrast.spec.ts` measures every one of them in both schemes.
//
// Light walks the state ramps DOWN (500 → 600 → 700), so a label can change on the way: ink reads
// on the pale end and white on the deep end, and no single colour reads on all three steps of
// success, warn, info, danger or help. White used to be the answer for all of them: 2.28:1 on
// success-500, 2.15:1 on warn-500, 3.68:1 on info-500, 3.76:1 on danger-500, 3.96:1 on help-500.
const WHITE_LABEL = '#ffffff';
/*
 * The state palettes are the same in every theme; the ink is not. A label read from the THEME's
 * `ink-900` measured 4.90:1 on `danger-500` in battoni-dev and 4.49:1 in largatudoevai's maresia,
 * whose ink-900 is a dark teal — under the floor with nothing in the state palette changed. The
 * darkest step, `ink-950`, is near-black in any theme that keeps the ramp's shape (5.58 · 5.13).
 */
const INK_LABEL = '{ink.950}';

const BUTTON_ROOT = {
  primary: {
    /*
     * `--color-primary-button`, falling back to the brand fill. A theme whose label cannot clear
     * 4.5:1 on `primary-500` darkens the BUTTON a step without moving the brand everywhere else —
     * moving `primary-500` would recolour every icon and highlight, and moving the button to
     * `primary-600` would collide with hover, which IS `primary-600`. A theme that declares
     * nothing gets exactly what it had. See `styles/theme/RULES.md`.
     */
    background: 'var(--color-primary-button, var(--color-primary-500))',
    hoverBackground: '{primary.hover.color}',
    activeBackground: '{primary.active.color}',
    borderColor: 'var(--color-primary-button, var(--color-primary-500))',
    hoverBorderColor: '{primary.hover.color}',
    activeBorderColor: '{primary.active.color}',
    color: '{primary.contrast.color}',
    hoverColor: '{primary.contrast.color}',
    activeColor: '{primary.contrast.color}',
    focusRing: {
      color: '{primary.color}',
      shadow: 'none',
    },
  },
  secondary: {
    background: '{surface.100}',
    hoverBackground: '{surface.200}',
    activeBackground: '{surface.300}',
    borderColor: '{surface.100}',
    hoverBorderColor: '{surface.200}',
    activeBorderColor: '{surface.300}',
    // `surface.600` on the `surface.100` fill measured 3.13:1 — under the 4.5:1 floor for a label.
    // `surface.900` clears it on all three fills (8.1 / 7.2 / 6.4:1), same as the secondary Tag fix.
    color: '{surface.900}',
    hoverColor: '{surface.900}',
    activeColor: '{surface.950}',
    focusRing: {
      color: '{surface.600}',
      shadow: 'none',
    },
  },
  success: {
    background: '{success.color}',
    hoverBackground: '{success.hover.color}',
    activeBackground: '{success.active.color}',
    borderColor: '{success.color}',
    hoverBorderColor: '{success.hover.color}',
    activeBorderColor: '{success.active.color}',
    // ink on 500 · 600 (9.22 · 6.37), white on 700 (5.02 — ink is 4.19 there).
    color: '{success.contrast.color}',
    hoverColor: '{success.contrast.color}',
    activeColor: WHITE_LABEL,
    focusRing: {
      color: '{success.color}',
      shadow: 'none',
    },
  },
  info: {
    background: '{info.color}',
    hoverBackground: '{info.hover.color}',
    activeBackground: '{info.active.color}',
    borderColor: '{info.color}',
    hoverBorderColor: '{info.hover.color}',
    activeBorderColor: '{info.active.color}',
    // ink on 500 (5.71 — white is 3.68), white on 600 · 700 (5.17 · 6.70).
    color: '{info.contrast.color}',
    hoverColor: WHITE_LABEL,
    activeColor: WHITE_LABEL,
    focusRing: {
      color: '{info.color}',
      shadow: 'none',
    },
  },
  warn: {
    background: '{warn.color}',
    hoverBackground: '{warn.hover.color}',
    activeBackground: '{warn.active.color}',
    borderColor: '{warn.color}',
    hoverBorderColor: '{warn.hover.color}',
    activeBorderColor: '{warn.active.color}',
    // ink on 500 · 600 (9.78 · 6.59), white on 700 (5.02 — ink is 4.18 there).
    color: '{warn.contrast.color}',
    hoverColor: '{warn.contrast.color}',
    activeColor: WHITE_LABEL,
    focusRing: {
      color: '{warn.color}',
      shadow: 'none',
    },
  },
  danger: {
    background: '{danger.color}',
    hoverBackground: '{danger.hover.color}',
    activeBackground: '{danger.active.color}',
    borderColor: '{danger.color}',
    hoverBorderColor: '{danger.hover.color}',
    activeBorderColor: '{danger.active.color}',
    // ink on 500 (5.58 — white is 3.76), white on 600 · 700 (4.83 · 6.47).
    color: '{danger.contrast.color}',
    hoverColor: WHITE_LABEL,
    activeColor: WHITE_LABEL,
    focusRing: {
      color: '{danger.color}',
      shadow: 'none',
    },
  },
  help: {
    background: '{help.500}',
    hoverBackground: '{help.600}',
    activeBackground: '{help.700}',
    borderColor: '{help.500}',
    hoverBorderColor: '{help.600}',
    activeBorderColor: '{help.700}',
    // ink on 500 (5.31 — white is 3.96), white on 600 · 700 (5.38 · 6.98). Same fills in both schemes.
    color: INK_LABEL,
    hoverColor: WHITE_LABEL,
    activeColor: WHITE_LABEL,
    focusRing: {
      color: '{help.500}',
      shadow: 'none',
    },
  },
  contrast: {
    background: '{contrast.500}',
    hoverBackground: '{contrast.600}',
    activeBackground: '{contrast.700}',
    borderColor: '{contrast.500}',
    hoverBorderColor: '{contrast.600}',
    activeBorderColor: '{contrast.700}',
    // white on 500 · 600 · 700: 4.74 · 7.81 · 10.37. Same fills in both schemes.
    color: WHITE_LABEL,
    hoverColor: WHITE_LABEL,
    activeColor: WHITE_LABEL,
    focusRing: {
      color: '{contrast.500}',
      shadow: 'none',
    },
  },
};

// Dark walks the state ramps UP (500 → 400 → 300), so the fill a person presses is lighter than the
// one they started on and ink reads on all three: success 9.22 · 12.05 · 14.96, info 5.71 · 8.26 ·
// 11.65, warn 9.78 · 12.58 · 14.56, danger 5.58 · 7.59 · 11.06. White on info-300 was 1.80:1.
// `secondary` reads neutral surface shades that mean different things per scheme; in dark it needs
// the dark end of the (non-inverted) surface scale.
/*
 * The brand ramp as a WORD, in light. Aura writes the outlined, text and link buttons in
 * `{primary.color}` — `primary-500`, 1.51:1 on white in `battoni-dev`, because the brand is a lime.
 * `primary-950` is the darkest step the ramp has and the only one that clears 4.5:1 on white
 * (4.78), and on the tints these buttons take under the hand (4.66 on `primary-50`, 4.54 on
 * `primary-100`). It does NOT clear on the panel (4.34) — see the known gap in the theme RULES.
 * Dark keeps Aura's `{primary.color}`, which reads on every dark ground.
 */
const BRAND_TEXT_LIGHT = '{primary.950}';

const BUTTON_VARIANTS_LIGHT = {
  outlined: {
    primary: {
      color: BRAND_TEXT_LIGHT,
    },
  },
  text: {
    primary: {
      color: BRAND_TEXT_LIGHT,
    },
    /*
     * Aura's `surface.500` was 3.43:1 on the canvas and 2.63:1 on panel-muted — the ground this
     * button is pressed on. `--color-muted` is the text tier measured on all three grounds
     * (7.15 · 6.49 · 5.48), and a role token, so another theme's muted answers here too.
     */
    secondary: {
      color: 'var(--color-muted)',
    },
  },
  link: {
    color: BRAND_TEXT_LIGHT,
    hoverColor: BRAND_TEXT_LIGHT,
    activeColor: BRAND_TEXT_LIGHT,
  },
};

const BADGE_SCHEME = {
  root: {
    background: '{primary.color}',
    color: '{primary.contrast.color}',
  },
  secondary: {
    background: '{surface.200}',
    color: INK_LABEL,
  },
  success: {
    background: '{success.500}',
    color: INK_LABEL,
  },
  info: {
    background: '{info.500}',
    color: INK_LABEL,
  },
  warn: {
    background: '{warn.500}',
    color: INK_LABEL,
  },
  danger: {
    background: '{danger.500}',
    color: INK_LABEL,
  },
  contrast: {
    background: '{surface.900}',
    color: '{surface.0}',
  },
} as any;

const BUTTON_ROOT_DARK = {
  ...BUTTON_ROOT,
  success: {
    ...BUTTON_ROOT.success,
    activeColor: '{success.contrast.color}',
  },
  info: {
    ...BUTTON_ROOT.info,
    hoverColor: '{info.contrast.color}',
    activeColor: '{info.contrast.color}',
  },
  warn: {
    ...BUTTON_ROOT.warn,
    activeColor: '{warn.contrast.color}',
  },
  danger: {
    ...BUTTON_ROOT.danger,
    hoverColor: '{danger.contrast.color}',
    activeColor: '{danger.contrast.color}',
  },
  secondary: {
    background: '{surface.800}',
    hoverBackground: '{surface.700}',
    // Pressed goes DARKER: `surface.600` under the white label measured 4.09:1, under the floor.
    activeBackground: '{surface.900}',
    borderColor: '{surface.800}',
    hoverBorderColor: '{surface.700}',
    activeBorderColor: '{surface.900}',
    color: '{surface.0}',
    hoverColor: '{surface.0}',
    activeColor: '{surface.0}',
    focusRing: {
      color: '{surface.300}',
      shadow: 'none',
    },
  },
};

/*
 * A secondary text button in dark keeps Aura's `surface.400` word and changes where it is pressed:
 * Aura lit the hover to `surface.800` (3.77:1) and the press to `surface.700` (2.58:1). One and two
 * steps up from the dark canvas instead — `surface.950` 8.00:1, `surface.900` 5.58:1.
 */
/*
 * The brand ramp as a WORD, in dark. Aura answers `{primary.color}` here too, and a pale brand reads
 * on a dark ground while a dark one does not: largatudoevai moved its teal down a step for its
 * filled button's sake and the outlined button fell to 4.00:1 on the canvas. `--color-primary-text`
 * is the theme's answer for this, and the fallback keeps `primary-500` for a theme that declares
 * nothing — `battoni-dev`'s lime is unchanged by this line.
 */
const BRAND_TEXT_DARK = 'var(--color-primary-text, var(--color-primary-500))';

const BUTTON_VARIANTS_DARK = {
  outlined: {
    primary: {
      color: BRAND_TEXT_DARK,
    },
  },
  text: {
    primary: {
      color: BRAND_TEXT_DARK,
    },
    secondary: {
      hoverBackground: '{surface.950}',
      activeBackground: '{surface.900}',
    },
  },
  link: {
    color: BRAND_TEXT_DARK,
    hoverColor: BRAND_TEXT_DARK,
    activeColor: BRAND_TEXT_DARK,
  },
};

export default definePreset(Aura, {
  primitive: {
    // Radius reads from the active theme's CSS vars (the same ones Tailwind's
    // rounded-* utilities use), with fallbacks to the app.rnc defaults. A theme
    // file can reshape every component's geometry by overriding --radius-*.
    borderRadius: {
      none: '0',
      xs: 'var(--radius-xs, 2px)',
      sm: 'var(--radius-sm, 4px)',
      md: 'var(--radius-md, 6px)',
      lg: 'var(--radius-lg, 8px)',
      xl: 'var(--radius-xl, 10px)',
    },

    primary: {
      50: 'var(--color-primary-50)',
      100: 'var(--color-primary-100)',
      200: 'var(--color-primary-200)',
      300: 'var(--color-primary-300)',
      400: 'var(--color-primary-400)',
      500: 'var(--color-primary-500)',
      600: 'var(--color-primary-600)',
      700: 'var(--color-primary-700)',
      800: 'var(--color-primary-800)',
      900: 'var(--color-primary-900)',
      950: 'var(--color-primary-950)',
    },
    ink: {
      50: 'var(--color-ink-50)',
      100: 'var(--color-ink-100)',
      200: 'var(--color-ink-200)',
      300: 'var(--color-ink-300)',
      400: 'var(--color-ink-400)',
      500: 'var(--color-ink-500)',
      600: 'var(--color-ink-600)',
      700: 'var(--color-ink-700)',
      800: 'var(--color-ink-800)',
      900: 'var(--color-ink-900)',
      950: 'var(--color-ink-950)',
    },
    surface: {
      50: 'var(--color-surface-50)',
      100: 'var(--color-surface-100)',
      200: 'var(--color-surface-200)',
      300: 'var(--color-surface-300)',
      400: 'var(--color-surface-400)',
      500: 'var(--color-surface-500)',
      600: 'var(--color-surface-600)',
      700: 'var(--color-surface-700)',
      800: 'var(--color-surface-800)',
      900: 'var(--color-surface-900)',
      950: 'var(--color-surface-950)',
    },
    success: {
      50: 'var(--color-success-50)',
      100: 'var(--color-success-100)',
      200: 'var(--color-success-200)',
      300: 'var(--color-success-300)',
      400: 'var(--color-success-400)',
      500: 'var(--color-success-500)',
      600: 'var(--color-success-600)',
      700: 'var(--color-success-700)',
      800: 'var(--color-success-800)',
      900: 'var(--color-success-900)',
      950: 'var(--color-success-950)',
    },
    info: {
      50: 'var(--color-info-50)',
      100: 'var(--color-info-100)',
      200: 'var(--color-info-200)',
      300: 'var(--color-info-300)',
      400: 'var(--color-info-400)',
      500: 'var(--color-info-500)',
      600: 'var(--color-info-600)',
      700: 'var(--color-info-700)',
      800: 'var(--color-info-800)',
      900: 'var(--color-info-900)',
      950: 'var(--color-info-950)',
    },
    warn: {
      50: 'var(--color-warn-50)',
      100: 'var(--color-warn-100)',
      200: 'var(--color-warn-200)',
      300: 'var(--color-warn-300)',
      400: 'var(--color-warn-400)',
      500: 'var(--color-warn-500)',
      600: 'var(--color-warn-600)',
      700: 'var(--color-warn-700)',
      800: 'var(--color-warn-800)',
      900: 'var(--color-warn-900)',
      950: 'var(--color-warn-950)',
    },
    danger: {
      50: 'var(--color-danger-50)',
      100: 'var(--color-danger-100)',
      200: 'var(--color-danger-200)',
      300: 'var(--color-danger-300)',
      400: 'var(--color-danger-400)',
      500: 'var(--color-danger-500)',
      600: 'var(--color-danger-600)',
      700: 'var(--color-danger-700)',
      800: 'var(--color-danger-800)',
      900: 'var(--color-danger-900)',
      950: 'var(--color-danger-950)',
    },
    help: {
      50: 'var(--color-help-50)',
      100: 'var(--color-help-100)',
      200: 'var(--color-help-200)',
      300: 'var(--color-help-300)',
      400: 'var(--color-help-400)',
      500: 'var(--color-help-500)',
      600: 'var(--color-help-600)',
      700: 'var(--color-help-700)',
      800: 'var(--color-help-800)',
      900: 'var(--color-help-900)',
      950: 'var(--color-help-950)',
    },
    contrast: {
      50: 'var(--color-contrast-50)',
      100: 'var(--color-contrast-100)',
      200: 'var(--color-contrast-200)',
      300: 'var(--color-contrast-300)',
      400: 'var(--color-contrast-400)',
      500: 'var(--color-contrast-500)',
      600: 'var(--color-contrast-600)',
      700: 'var(--color-contrast-700)',
      800: 'var(--color-contrast-800)',
      900: 'var(--color-contrast-900)',
      950: 'var(--color-contrast-950)',
    },
  },

  semantic: {
    /*
     * The ACTIVE THEME's faces, never a family named here. These named the `battoni-dev` pair
     * outright, so a theme declaring its own `--font-sans` loaded a typeface it never got to use.
     * `body` was mono — a house choice of that one theme, which now says so in its own file.
     */
    fontFamily: {
      base: 'var(--font-sans)',
      heading: 'var(--font-sans)',
      body: 'var(--font-sans)',
      mono: 'var(--font-mono)',
    },

    // Mirror `primitive` scales here with raw `var(--color-*)` (not `{primary.500}`) so we override
    // Aura’s emerald/semantic defaults without a self-referential token that collides on `primary.500`.

    primary: {
      50: 'var(--color-primary-50)',
      100: 'var(--color-primary-100)',
      200: 'var(--color-primary-200)',
      300: 'var(--color-primary-300)',
      400: 'var(--color-primary-400)',
      500: 'var(--color-primary-500)',
      600: 'var(--color-primary-600)',
      700: 'var(--color-primary-700)',
      800: 'var(--color-primary-800)',
      900: 'var(--color-primary-900)',
      950: 'var(--color-primary-950)',
    },

    colorScheme: {
      light: {
        surface: {
          0: '#ffffff',
          50: 'var(--color-surface-50)',
          100: 'var(--color-surface-100)',
          200: 'var(--color-surface-200)',
          300: 'var(--color-surface-300)',
          400: 'var(--color-surface-400)',
          500: 'var(--color-surface-500)',
          600: 'var(--color-surface-600)',
          700: 'var(--color-surface-700)',
          800: 'var(--color-surface-800)',
          900: 'var(--color-surface-900)',
          950: 'var(--color-surface-950)',
        },

        primary: {
          color: '{primary.500}',
          // The THEME decides what reads on the brand fill (`--color-primary-contrast`); the preset
          // asks it. A literal here was a second answer to one question, and it won.
          contrastColor: 'var(--color-primary-contrast)',
          contrast: {
            color: 'var(--color-primary-contrast)',
          },
          hover: {
            // Darkens under the hand, as light does. Dark walked the ramp UP (400, 300), so a press
            // read as a flash of a lighter brand; `primary-contrast` holds on 600/700 (see RULES).
            color: '{primary.600}',
          },
          active: {
            color: '{primary.700}',
          },
          hoverColor: '{primary.600}',
          activeColor: '{primary.700}',
        },

        focusRing: {
          color: '{primary.500}',
          width: '1px',
          style: 'solid',
          offset: '2px',
        },

        // The selected option of a list, a chip, a checked item: a word on a pale brand tint. Aura
        // writes it in `primary-700` / `-800` — 1.88:1 and 2.41:1 on `primary-50`/`-100` here.
        highlight: {
          background: '{primary.50}',
          focusBackground: '{primary.100}',
          color: BRAND_TEXT_LIGHT,
          focusColor: BRAND_TEXT_LIGHT,
        },

        text: {
          color: '{surface.950}',
          hoverColor: '{surface.950}',
          // Secondary text is read, so it asks the theme's readable tier. `{surface.600}` measured
          // 3.71:1 on the panel in `battoni-dev`, under the 4.5:1 floor (theme RULES).
          mutedColor: 'var(--color-muted)',
          highlightBackground: '{primary.50}',
          highlightColor: BRAND_TEXT_LIGHT,
        },

        formField: {
          // Aura paints these from `{surface.500}` / `{surface.400}`: a placeholder at 3.43:1 and a
          // field icon at 1.90:1 on white. A placeholder and a float label are text (muted, 4.5:1);
          // the icon is a graphic (subtle, 3:1). Dark keeps Aura's pair, which already clears both.
          floatLabelColor: 'var(--color-muted)',
          // A label that holds a value, or sits in a focused field, is still that label. Aura painted
          // them `surface.500` (3.11:1 on the panel) and `primary.600` (1.69:1 on white); no step of
          // the lime ramp reads on the panel, so focus goes UP a tier instead of taking the brand —
          // the focus ring already carries it.
          floatLabelActiveColor: 'var(--color-muted)',
          floatLabelFocusColor: 'var(--color-heading)',
          iconColor: 'var(--color-subtle)',
          placeholderColor: 'var(--color-muted)',
          paddingX: '0.75rem',
          paddingY: '0.5rem',
          borderRadius: '{border.radius.md}',
          focusRing: {
            width: '1px',
            style: 'solid',
            color: '{primary.500}',
            offset: '0',
          },
        },

        content: {
          borderRadius: '{border.radius.md}',
        },

        overlay: {
          select: {
            borderRadius: '{border.radius.md}',
          },
          popover: {
            borderRadius: '{border.radius.md}',
          },
        },

        success: {
          color: '{success.500}',
          // What reads on `success.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{success.600}',
          },
          active: {
            color: '{success.700}',
          },
          hoverColor: '{success.600}',
          activeColor: '{success.700}',
        },
        info: {
          color: '{info.500}',
          // What reads on `info.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{info.600}',
          },
          active: {
            color: '{info.700}',
          },
          hoverColor: '{info.600}',
          activeColor: '{info.700}',
        },
        warn: {
          color: '{warn.500}',
          // What reads on `warn.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{warn.600}',
          },
          active: {
            color: '{warn.700}',
          },
          hoverColor: '{warn.600}',
          activeColor: '{warn.700}',
        },
        danger: {
          color: '{danger.500}',
          // What reads on `danger.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{danger.600}',
          },
          active: {
            color: '{danger.700}',
          },
          hoverColor: '{danger.600}',
          activeColor: '{danger.700}',
        },
      },
      dark: {
        // Standard orientation (0 = lightest, 950 = darkest), brand-warm values.
        // Aura's dark tokens reference high indices for backgrounds
        // (content.background={surface.900}, formField.background={surface.950})
        // and low indices for text ({surface.0}), so the scale must NOT be inverted.
        surface: {
          0: '#ffffff',
          50: '#f3f4f3',
          100: '#dde2e6',
          200: '#d1d6d9',
          300: '#c5c9cc',
          400: '#b8bdbf',
          500: '#888b8d',
          600: '#7b7e80',
          700: '#6f7173',
          800: '#565859',
          900: '#252626', // cards / panels (content.background)
          950: '#141413', // deepest / inputs (formField.background) — brand signature dark
        },

        primary: {
          color: '{primary.500}',
          // The THEME decides what reads on the brand fill (`--color-primary-contrast`); the preset
          // asks it. A literal here was a second answer to one question, and it won.
          contrastColor: 'var(--color-primary-contrast)',
          contrast: {
            color: 'var(--color-primary-contrast)',
          },
          hover: {
            color: '{primary.600}',
          },
          active: {
            color: '{primary.700}',
          },
          hoverColor: '{primary.600}',
          activeColor: '{primary.700}',
        },

        focusRing: {
          color: '{primary.500}',
          width: '1px',
          style: 'solid',
          offset: '2px',
        },

        text: {
          color: '{surface.0}',
          hoverColor: '{surface.0}',
          mutedColor: '{surface.400}',
          highlightBackground: '{surface.800}',
          highlightColor: '{primary.400}',
        },

        formField: {
          // Inputs are raised (#252626) so they read on the body-toned dialog (#141413).
          background: '{surface.900}',
          /*
           * A focused float label is the brand as a WORD on a dark ground. Aura answers
           * `{primary.color}` (`primary-500`): a lime reads there, a dark brand does not — maresia's
           * teal measured 4.38:1 on its panel. `--color-primary-text` lets a theme name a lighter
           * step in its dark block; a theme that declares nothing keeps `primary-500`.
           */
          floatLabelFocusColor: 'var(--color-primary-text, var(--color-primary-500))',
          borderColor: '{surface.700}',
          color: '{surface.0}',
          paddingX: '0.75rem',
          paddingY: '0.5rem',
          borderRadius: '{border.radius.md}',
          focusRing: {
            width: '1px',
            style: 'solid',
            color: '{primary.500}',
            offset: '0',
          },
        },

        content: {
          borderRadius: '{border.radius.md}',
        },

        overlay: {
          select: {
            borderRadius: '{border.radius.md}',
          },
          popover: {
            borderRadius: '{border.radius.md}',
          },
        },

        success: {
          color: '{success.500}',
          // What reads on `success.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{success.400}',
          },
          active: {
            color: '{success.300}',
          },
          hoverColor: '{success.400}',
          activeColor: '{success.300}',
        },
        info: {
          color: '{info.500}',
          // What reads on `info.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{info.400}',
          },
          active: {
            color: '{info.300}',
          },
          hoverColor: '{info.400}',
          activeColor: '{info.300}',
        },
        warn: {
          color: '{warn.500}',
          // What reads on `warn.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{warn.400}',
          },
          active: {
            color: '{warn.300}',
          },
          hoverColor: '{warn.400}',
          activeColor: '{warn.300}',
        },
        danger: {
          color: '{danger.500}',
          // What reads on `danger.500`: `ink-950` (see BUTTON_ROOT for the other steps).
          contrastColor: INK_LABEL,
          contrast: {
            color: INK_LABEL,
          },
          hover: {
            color: '{danger.400}',
          },
          active: {
            color: '{danger.300}',
          },
          hoverColor: '{danger.400}',
          activeColor: '{danger.300}',
        },
      },
    },

    success: {
      50: 'var(--color-success-50)',
      100: 'var(--color-success-100)',
      200: 'var(--color-success-200)',
      300: 'var(--color-success-300)',
      400: 'var(--color-success-400)',
      500: 'var(--color-success-500)',
      600: 'var(--color-success-600)',
      700: 'var(--color-success-700)',
      800: 'var(--color-success-800)',
      900: 'var(--color-success-900)',
      950: 'var(--color-success-950)',
    },
    info: {
      50: 'var(--color-info-50)',
      100: 'var(--color-info-100)',
      200: 'var(--color-info-200)',
      300: 'var(--color-info-300)',
      400: 'var(--color-info-400)',
      500: 'var(--color-info-500)',
      600: 'var(--color-info-600)',
      700: 'var(--color-info-700)',
      800: 'var(--color-info-800)',
      900: 'var(--color-info-900)',
      950: 'var(--color-info-950)',
    },
    warn: {
      50: 'var(--color-warn-50)',
      100: 'var(--color-warn-100)',
      200: 'var(--color-warn-200)',
      300: 'var(--color-warn-300)',
      400: 'var(--color-warn-400)',
      500: 'var(--color-warn-500)',
      600: 'var(--color-warn-600)',
      700: 'var(--color-warn-700)',
      800: 'var(--color-warn-800)',
      900: 'var(--color-warn-900)',
      950: 'var(--color-warn-950)',
    },
    danger: {
      50: 'var(--color-danger-50)',
      100: 'var(--color-danger-100)',
      200: 'var(--color-danger-200)',
      300: 'var(--color-danger-300)',
      400: 'var(--color-danger-400)',
      500: 'var(--color-danger-500)',
      600: 'var(--color-danger-600)',
      700: 'var(--color-danger-700)',
      800: 'var(--color-danger-800)',
      900: 'var(--color-danger-900)',
      950: 'var(--color-danger-950)',
    },
    help: {
      50: 'var(--color-help-50)',
      100: 'var(--color-help-100)',
      200: 'var(--color-help-200)',
      300: 'var(--color-help-300)',
      400: 'var(--color-help-400)',
      500: 'var(--color-help-500)',
      600: 'var(--color-help-600)',
      700: 'var(--color-help-700)',
      800: 'var(--color-help-800)',
      900: 'var(--color-help-900)',
      950: 'var(--color-help-950)',
    },
    contrast: {
      50: 'var(--color-contrast-50)',
      100: 'var(--color-contrast-100)',
      200: 'var(--color-contrast-200)',
      300: 'var(--color-contrast-300)',
      400: 'var(--color-contrast-400)',
      500: 'var(--color-contrast-500)',
      600: 'var(--color-contrast-600)',
      700: 'var(--color-contrast-700)',
      800: 'var(--color-contrast-800)',
      900: 'var(--color-contrast-900)',
      950: 'var(--color-contrast-950)',
    },

    datatable: {
      border: {
        color: '{surface.200}',
      },
    },
  },

  components: {
    global: {
      /*
       * Typography belongs to the theme. This block named two families and handed them out by
       * selector, so `--font-sans` in the active theme decided nothing. `*` stays only because
       * PrimeVue's components set families internally; it points at the token now.
       */
      css: `
        :root {
          --font-family: var(--font-sans);
        }

        * {
          font-family: var(--font-sans);
        }

        code, pre, .p-code {
          font-family: var(--font-mono);
        }
      `,
    },

    button: {
      root: {
        borderRadius: '{border.radius.md}',
        roundedBorderRadius: '{border.radius.lg}',
      },
      colorScheme: {
        light: { root: BUTTON_ROOT, ...BUTTON_VARIANTS_LIGHT } as any,
        dark: { root: BUTTON_ROOT_DARK, ...BUTTON_VARIANTS_DARK } as any,
      },
    },

    card: {
      root: {
        borderRadius: '{border.radius.lg}',
      },
    },

    message: {
      colorScheme: {
        light: {
          success: {
            background: '{success.50}',
            borderColor: '{success.200}',
            color: '{success.700}',
            shadow: '0px 4px 8px 0px rgba(34, 197, 94, 0.04)',
          },
          info: {
            background: '{info.50}',
            borderColor: '{info.200}',
            color: '{info.700}',
            shadow: '0px 4px 8px 0px rgba(59, 130, 246, 0.04)',
          },
          warn: {
            background: '{warn.50}',
            borderColor: '{warn.200}',
            color: '{warn.700}',
            shadow: '0px 4px 8px 0px rgba(245, 158, 11, 0.04)',
          },
          error: {
            background: '{danger.50}',
            borderColor: '{danger.200}',
            color: '{danger.700}',
            shadow: '0px 4px 8px 0px rgba(239, 68, 68, 0.04)',
          },
        },
        dark: {
          success: {
            background: '{success.950}',
            borderColor: '{success.800}',
            color: '{success.300}',
          },
          info: {
            background: '{info.950}',
            borderColor: '{info.800}',
            color: '{info.300}',
          },
          warn: {
            background: '{warn.950}',
            borderColor: '{warn.800}',
            color: '{warn.300}',
          },
          error: {
            background: '{danger.950}',
            borderColor: '{danger.800}',
            color: '{danger.300}',
          },
        },
      },
    },

    /*
     * The default Tag is painted from `tag.primary.*` — `.p-tag` reads nothing else. This block set
     * `tag.root.*` in both schemes, which no rule consumes, so Aura's `primary.600` on `primary.100`
     * (1.83:1) rendered on every `rounded` default Tag in light. (`styles/components/tag.css` paints
     * a default Tag only while `data-p` is empty, and `rounded` fills it.) The pair is now the one
     * tag.css paints: the brand's lightest tint under ink, 17.98:1. Ink and not
     * `{primary.contrast.color}`: that token answers for the 500 FILL, and a brand whose fill takes
     * a white label would put white on this pale tint. Dark keeps Aura's pair, which reads.
     */
    tag: {
      colorScheme: {
        light: {
          primary: {
            background: '{primary.50}',
            color: INK_LABEL,
          },
          /*
           * `--color-body`, not Aura's `surface.600`. The secondary Tag is the neutral chip, and on
           * `surface.100` Aura's text measured 3.13:1 here — under the 4.5:1 floor for text read at
           * 14px. `--color-body` is 14.13:1 and is a role token, so it moves with the scheme.
           */
          secondary: {
            background: '{surface.100}',
            color: 'var(--color-body)',
          },
          success: {
            background: '{success.100}',
            color: '{success.700}',
          },
          info: {
            background: '{info.100}',
            color: '{info.700}',
          },
          warn: {
            background: '{warn.100}',
            color: '{warn.700}',
          },
          danger: {
            background: '{danger.100}',
            color: '{danger.700}',
          },
        } as any,
        dark: {
          /*
           * Aura's dark pair — `surface.300` on `surface.800` — measured 4.29:1 on this ramp. The
           * `surface` ramp does NOT invert between schemes (800 is dark in both), so the ground
           * stays and the text becomes the role token: 5.06:1.
           */
          secondary: {
            background: '{surface.800}',
            color: 'var(--color-body)',
          },
          success: {
            background: '{success.950}',
            color: '{success.300}',
          },
          info: {
            background: '{info.950}',
            color: '{info.300}',
          },
          warn: {
            background: '{warn.950}',
            color: '{warn.300}',
          },
          danger: {
            background: '{danger.950}',
            color: '{danger.300}',
          },
        } as any,
      },
    },

    /*
     * Measured, like a button label: a badge's number is text. White sat on every state fill (2.28:1
     * on `success.500`, 2.15:1 on `warn.500`) and `surface.700` on `surface.200` was 3.34:1. The
     * surface ramp does not invert between schemes, so the same pale chip takes the same ink in both.
     * The default badge is the brand fill with the brand's own label — it rendered lime already, from
     * a global rule in `primevue.css` that also repainted every severity and is gone.
     */
    badge: {
      colorScheme: {
        light: BADGE_SCHEME,
        dark: BADGE_SCHEME,
      },
    },

    /*
     * The unselected option of a SelectButton / ToggleButton sits on `surface.100`, where Aura's
     * `surface.500` word measured 2.63:1 and its `surface.700` hover 3.76:1. Muted reads there
     * (5.48:1) and the hover takes the checked option's `surface.900` (8.11:1). Dark reads as Aura
     * ships it.
     */
    togglebutton: {
      colorScheme: {
        light: {
          root: {
            color: 'var(--color-muted)',
            hoverColor: '{surface.900}',
          },
          icon: {
            color: 'var(--color-muted)',
            hoverColor: '{surface.900}',
          },
        },
      },
    },

    tabs: {
      colorScheme: {
        light: {
          tab: {
            active: {
              // `primary-700` was 1.93:1 on white. The bar under the tab stays `primary-500`.
              color: BRAND_TEXT_LIGHT,
            },
          },
        } as any,
        dark: {
          tab: {
            active: {
              color: '{primary.400}',
            },
          },
        } as any,
      },
    },

    /*
     * The `on` variant's label straddles the field's top border, so it needs a backing plate the
     * width of the word. Aura resolves it to `{form.field.background}`, which the light scheme
     * never sets — so it fell through to white, a pill on whatever panel the form sits on.
     */
    floatlabel: {
      on: {
        active: {
          background: 'var(--color-panel)',
        },
      },
    },

    /*
     * A divider is a LINE, not a brand accent. `{primary.color}` painted every Divider in the
     * brand colour, and call sites had grown `before:border-line` to undo it.
     */
    divider: {
      colorScheme: {
        light: {
          root: {
            borderColor: 'var(--color-line)',
          },
        },
        dark: {
          root: {
            borderColor: 'var(--color-line)',
          },
        },
      },
    },

    // In dark, the dialog takes the body tone (#141413) so it reads as part of the
    // page (matching light, where dialog == body white) — raised inputs (#252626)
    // provide the contrast. This also removes the footer seam.
    // The dialog reads the body tone (--color-canvas) in every theme + scheme, so
    // it blends with the page instead of floating as a lighter box. Raised inputs
    // provide the contrast. A border keeps it delineated where bg == body exactly.
    dialog: {
      colorScheme: {
        light: {
          root: {
            background: 'var(--color-canvas)',
            borderColor: 'var(--color-line-strong)',
          },
        },
        dark: {
          root: {
            background: 'var(--color-canvas)',
            borderColor: 'var(--color-line-strong)',
          },
        },
      },
    },

    /*
     * 12px between the two buttons of a confirm popup, not Aura's 8px.
     *
     * A small button's hit area grows 6px on each side under `pointer: coarse` (see
     * `styles/components/touch-target.css`). 6 + 6 = 12 against an 8px gap means the two areas
     * OVERLAP by 4px, and PrimeVue renders reject first and accept second — so the later sibling
     * wins the overlap. A thumb landing on the right edge of "Cancel" fires the destructive
     * accept and deletes the row.
     *
     * This is the one popup where that costs something, and it is the exact hazard the 12px
     * minimum-gap rule in `celer-06-design-system` exists for. The rule reaches this app's own
     * components and not this one, because this gap belongs to the framework.
     */
    confirmpopup: {
      footer: {
        gap: '0.75rem',
      },
    },
  },
});
