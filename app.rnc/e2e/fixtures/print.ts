import type { Page } from '@playwright/test';

/**
 * What a printable page puts on paper, as the browser computed it in print media. Paper is white:
 * Chrome drops backgrounds when printing unless the person ticks "background graphics", so every
 * line of text has to read on white by itself, whatever scheme the screen was in (rnc-1 R17/R26).
 */
export interface PrintedSheet {
  /** Every visible element of the sheet with its own text whose colour reads under 4.5:1 on white. */
  faintText: { color: string; ratio: number; text: string }[];
  /** `color-scheme` of the root in print media. */
  rootColorScheme: string;
  /** Relative luminance of the sheet's own fill (1 = white); `null` when the fill is transparent. */
  sheetLuminance: number | null;
  /** How many elements with text were measured — a guard against a selector that matched nothing. */
  measured: number;
}

export async function emulateDarkPrint(page: Page): Promise<void> {
  await page.emulateMedia({ colorScheme: 'dark', media: 'print' });
}

export async function readPrintedSheet(page: Page, selector: string): Promise<PrintedSheet> {
  return page.evaluate((sheetSelector) => {
    function channels(color: string): number[] {
      const values = (color.match(/[\d.]+/g) ?? []).map(Number);

      // `color(srgb r g b / a)` (what a `color-mix` computes to) carries the channels as 0–1.
      return color.startsWith('color(srgb') ? values.map((value, index) => (index < 3 ? value * 255 : value)) : values;
    }

    function luminance([r = 0, g = 0, b = 0]: number[]): number {
      const linear = [r, g, b].map((value) => {
        const unit = value / 255;

        return unit <= 0.03928 ? unit / 12.92 : ((unit + 0.055) / 1.055) ** 2.4;
      });

      return 0.2126 * linear[0]! + 0.7152 * linear[1]! + 0.0722 * linear[2]!;
    }

    const sheet = document.querySelector(sheetSelector)!;
    const fill = channels(getComputedStyle(sheet).backgroundColor);
    const faintText: { color: string; ratio: number; text: string }[] = [];
    let measured = 0;

    for (const element of sheet.querySelectorAll<HTMLElement>('*')) {
      const ownText = [...element.childNodes]
        .filter((node) => node.nodeType === Node.TEXT_NODE)
        .map((node) => node.textContent ?? '')
        .join('')
        .trim();

      if (!ownText || element.getClientRects().length === 0) continue;

      measured += 1;

      const color = getComputedStyle(element).color;
      const ratio = 1.05 / (luminance(channels(color)) + 0.05);

      if (ratio < 4.5) faintText.push({ color, ratio: Math.round(ratio * 100) / 100, text: ownText.slice(0, 40) });
    }

    return {
      faintText,
      measured,
      rootColorScheme: getComputedStyle(document.documentElement).colorScheme,
      sheetLuminance: fill[3] === 0 ? null : luminance(fill),
    };
  }, selector);
}
