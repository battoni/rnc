type MessageTree = { [key: string]: MessageTree | string };

function deepMergeMessages(base: MessageTree, override: MessageTree): MessageTree {
  const merged: MessageTree = { ...base };

  for (const key of Object.keys(override)) {
    const overrideValue = override[key];

    if (overrideValue === undefined) continue;

    const baseValue = merged[key];
    const bothBranches =
      !!baseValue && typeof baseValue === 'object' && !!overrideValue && typeof overrideValue === 'object';

    merged[key] = bothBranches
      ? deepMergeMessages(baseValue as MessageTree, overrideValue as MessageTree)
      : overrideValue;
  }

  return merged;
}

export default deepMergeMessages;
export type { MessageTree };
