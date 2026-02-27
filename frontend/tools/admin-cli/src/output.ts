import type { HttpResult } from './types.js';

// ANSI escape helpers
export const ansi = {
  bold: (s: string) => `\x1b[1m${s}\x1b[0m`,
  dim: (s: string) => `\x1b[2m${s}\x1b[0m`,
  green: (s: string) => `\x1b[32m${s}\x1b[0m`,
  red: (s: string) => `\x1b[31m${s}\x1b[0m`,
  reset: '\x1b[0m',
};

export function display(data: unknown, mode: 'human' | 'json'): void {
  if (mode === 'json') {
    console.log(JSON.stringify(data, null, 2));
    return;
  }

  if (typeof data !== 'object' || data === null) {
    console.log('');
    console.log(`  ${String(data)}`);
    console.log('');
    return;
  }

  const obj = data as Record<string, unknown>;

  if (obj['archetype'] === 'document-collection' && Array.isArray(obj['items'])) {
    displayCollection(obj['items'] as unknown[]);
  } else {
    displayDocument(obj);
  }
}

function collectLines(obj: Record<string, unknown>, depth: number): string[] {
  const indent = '  '.repeat(depth);
  const lines: string[] = [];

  for (const [key, value] of Object.entries(obj)) {
    if (isPlainObject(value)) {
      lines.push(`${indent}${key}:`);
      lines.push(...collectLines(value as Record<string, unknown>, depth + 1));
    } else if (Array.isArray(value)) {
      lines.push(`${indent}${key}: ${JSON.stringify(value)}`);
    } else {
      lines.push(`${indent}${key}: ${value == null ? '' : String(value)}`);
    }
  }

  return lines;
}

function displayDocument(obj: Record<string, unknown>): void {
  // Each top-level key is a section separated by a horizontal line.
  const sections: string[][] = Object.entries(obj).map(([key, value]) => {
    if (isPlainObject(value)) {
      return [`${key}:`, ...collectLines(value as Record<string, unknown>, 1)];
    } else if (Array.isArray(value)) {
      return [`${key}: ${JSON.stringify(value)}`];
    } else {
      return [`${key}: ${value == null ? '' : String(value)}`];
    }
  });

  if (sections.length === 0) return;

  const width = Math.max(...sections.flatMap((s) => s.map((l) => l.length)));
  const separator = `+-${'-'.repeat(width)}-+`;

  console.log('');
  console.log(`  ${separator}`);
  for (const section of sections) {
    for (const line of section) {
      console.log(`  | ${line.padEnd(width)} |`);
    }
    console.log(`  ${separator}`);
  }
  console.log('');
}

function displayCollection(items: unknown[]): void {
  if (items.length === 0) {
    console.log('');
    console.log(`  ${ansi.dim('(empty collection)')}`);
    console.log('');
    return;
  }

  // Build column list: uid + header keys + body keys from first item
  const first = items[0] as Record<string, unknown>;
  const headerKeys = Object.keys(isPlainObject(first['header']) ? first['header'] : {});
  const bodyKeys = Object.keys(isPlainObject(first['body']) ? first['body'] : {});
  const columns = ['uid', ...headerKeys, ...bodyKeys];

  // Collect string values for every row
  const rows: string[][] = items.map((item) => {
    const doc = item as Record<string, unknown>;
    const identifiers = isPlainObject(doc['identifiers']) ? doc['identifiers'] : {};
    const header = isPlainObject(doc['header']) ? doc['header'] : {};
    const body = isPlainObject(doc['body']) ? doc['body'] : {};

    return columns.map((col) => {
      if (col === 'uid') return String((identifiers as Record<string, unknown>)['uid'] ?? '');
      if (headerKeys.includes(col))
        return String((header as Record<string, unknown>)[col] ?? '');
      return String((body as Record<string, unknown>)[col] ?? '');
    });
  });

  // Compute column widths
  const widths: number[] = columns.map((col, i) => {
    const maxData = Math.max(...rows.map((row) => (row[i] ?? '').length));
    return Math.max(col.length, maxData);
  });

  const separator = `+-${widths.map((w) => '-'.repeat(w)).join('-+-')}-+`;

  // Header row
  const headerLine =
    '| ' +
    columns.map((col, i) => ansi.bold(col.padEnd(widths[i] ?? col.length))).join(' | ') +
    ' |';

  console.log('');
  console.log(`  ${separator}`);
  console.log(`  ${headerLine}`);
  console.log(`  ${separator}`);

  // Data rows
  for (const row of rows) {
    const line = '| ' + row.map((cell, i) => cell.padEnd(widths[i] ?? cell.length)).join(' | ') + ' |';
    console.log(`  ${line}`);
  }
  console.log(`  ${separator}`);

  console.log(`  ${ansi.dim(`${items.length} item(s)`)}`);
  console.log('');
}

export function displayError(result: HttpResult, mode: 'human' | 'json'): void {
  if (mode === 'json') {
    console.error(JSON.stringify({ error: true, status: result.status, data: result.data }, null, 2));
    return;
  }

  const msg = extractErrorMessage(result.data);
  console.error('');
  if (result.status === 0) {
    console.error(`  ${ansi.red('✗')} ${msg}`);
  } else {
    console.error(`  ${ansi.red(`✗ HTTP ${result.status}`)}: ${msg}`);
  }
  console.error('');
}

export function displaySuccess(message: string): void {
  console.log('');
  console.log(`  ${ansi.green('✓')} ${message}`);
  console.log('');
}

export function printError(message: string): void {
  console.error('');
  console.error(`  ${ansi.red('✗')} ${message}`);
  console.error('');
}

function extractErrorMessage(data: unknown): string {
  if (typeof data === 'string') return data;
  if (isPlainObject(data)) {
    const obj = data as Record<string, unknown>;
    if (typeof obj['message'] === 'string') return obj['message'];
    if (typeof obj['error'] === 'string') return obj['error'];
    return JSON.stringify(data);
  }
  return 'Unknown error';
}


function isPlainObject(val: unknown): val is Record<string, unknown> {
  return typeof val === 'object' && val !== null && !Array.isArray(val);
}
