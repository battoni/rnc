import type { MessageTree } from './deepMergeMessages';

/*
 * The firm in a translation (rnc-4 R14, R15). A project string that needs the firm writes `{firmShort}`
 * (its short name, "a {firmShort} indica…") or `{firmName}` (its full name), and no call site passes it:
 * both become linked messages to `firm.short` / `firm.name`, which the host firm's answer rewrites
 * (`setFirmNames`, called by `useBrandTheme`). A linked message resolves at render time from the
 * reactive message store, so every string on screen follows the firm the moment it answers.
 *
 * The braced form `@:{'firm.short'}` and not `@:firm.short`: the bare form reads a following comma
 * into the key ("a @:firm.short, online" looks up `firm.short,`).
 */
const FIRM_PLACEHOLDERS: Record<string, string> = {
  '{firmShort}': "@:{'firm.short'}",
  '{firmName}': "@:{'firm.name'}",
};

const FIRM_PLACEHOLDER_PATTERN = /\{firmShort\}|\{firmName\}/g;

function withFirmPlaceholders(tree: MessageTree): MessageTree {
  return Object.fromEntries(
    Object.entries(tree).map(([key, value]) => [
      key,
      typeof value === 'string'
        ? value.replace(FIRM_PLACEHOLDER_PATTERN, (placeholder) => FIRM_PLACEHOLDERS[placeholder] ?? placeholder)
        : withFirmPlaceholders(value),
    ])
  );
}

export { withFirmPlaceholders };
