// Removes PHP comments — `/* … */`, `// …` and `# …` (but not a `#[Attribute]`) — while leaving
// string literals untouched, so a `//` inside `'https://…'` survives and an example route inside a
// doc-comment never parses as a real one (api.local.php ships only such an example).
export function stripPhpComments(src) {
  let out = '';
  let index = 0;

  while (index < src.length) {
    const char = src[index];
    const next = src[index + 1];

    if (char === "'" || char === '"') {
      let end = index + 1;
      while (end < src.length && src[end] !== char) {
        if (src[end] === '\\') end++;
        end++;
      }
      out += src.slice(index, end + 1);
      index = end + 1;
      continue;
    }

    if (char === '/' && next === '*') {
      const end = src.indexOf('*/', index + 2);
      index = end === -1 ? src.length : end + 2;
      continue;
    }

    const isLineComment = (char === '/' && next === '/') || (char === '#' && next !== '[');
    if (isLineComment) {
      const end = src.indexOf('\n', index);
      index = end === -1 ? src.length : end;
      continue;
    }

    out += char;
    index++;
  }

  return out;
}
