import type { Interface as RlInterface } from 'node:readline';
import type { CollectionConfig, DocumentConfig, ControllerConfig, FieldConfig, Session } from './types.js';
import {
  nodeKind,
  currentUrl,
  navigableChildren,
  invokableControllers,
  navigateInto,
  navigateUp,
  navigateRoot,
} from './session.js';
import { request } from './http.js';
import { display, displayError, displaySuccess, printError, ansi } from './output.js';

function ask(rl: RlInterface, prompt: string): Promise<string> {
  return new Promise((resolve) => rl.question(prompt, resolve));
}

export async function dispatch(input: string, rl: RlInterface, session: Session): Promise<void> {
  const parts = input.trim().split(/\s+/);
  const cmd = parts[0] ?? '';
  const args = parts.slice(1);

  switch (cmd) {
    case 'help':
      cmdHelp(session);
      return;
    case 'ls':
      cmdLs(session);
      return;
    case 'pwd':
      cmdPwd(session);
      return;
    case 'clear':
      process.stdout.write('\x1Bc');
      return;
    case 'output':
      cmdOutput(args, session);
      return;
    case 'exit':
    case 'quit':
      process.exit(0);
    case 'cd':
      await cmdCd(args, rl, session);
      return;
    case 'index':
      if (nodeKind(session) !== 'collection') {
        printError('"index" is only available at a collection node');
        return;
      }
      await cmdIndex(session);
      return;
    case 'create':
      if (nodeKind(session) !== 'collection') {
        printError('"create" is only available at a collection node');
        return;
      }
      await cmdCreate(rl, session);
      return;
    case 'show':
      if (nodeKind(session) !== 'document') {
        printError('"show" is only available at a document node');
        return;
      }
      await cmdShow(session);
      return;
    case 'update':
      if (nodeKind(session) !== 'document') {
        printError('"update" is only available at a document node');
        return;
      }
      await cmdUpdate(rl, session);
      return;
    case 'delete':
      if (nodeKind(session) !== 'document') {
        printError('"delete" is only available at a document node');
        return;
      }
      await cmdDelete(rl, session);
      return;
    case 'invoke':
      if (nodeKind(session) === 'root') {
        printError('"invoke" is not available at the root');
        return;
      }
      await cmdInvoke(args, rl, session);
      return;
    default:
      printError(`Unknown command: "${cmd}". Type "help" for available commands.`);
  }
}

// ─── Universal commands ────────────────────────────────────────────────────────

function cmdHelp(session: Session): void {
  const kind = nodeKind(session);
  const path = session.path.length === 0 ? '/' : `/${session.path.join('/')}`;

  console.log(`\n${ansi.bold('Node:')} ${kind} — ${path}`);

  if (session.currentNode?.description) {
    console.log(`${ansi.bold('Description:')} ${session.currentNode.description}`);
  }

  console.log(`\n${ansi.bold('Universal commands:')}`);
  const universal: [string, string][] = [
    ['help', 'Show this help message'],
    ['ls', 'List navigable children and available actions'],
    ['pwd', 'Show the current path'],
    ['clear', 'Clear the terminal screen'],
    ['cd <name|id>', 'Navigate into a child node'],
    ['cd ..', 'Navigate to the parent node'],
    ['cd /', 'Navigate to the root'],
    ['output human|json', 'Switch output mode'],
    ['exit / quit', 'Exit the CLI'],
  ];
  for (const [cmd, desc] of universal) {
    console.log(`  ${cmd.padEnd(22)} ${desc}`);
  }

  if (kind === 'collection') {
    console.log(`\n${ansi.bold('Collection commands:')}`);
    console.log(`  ${'index'.padEnd(22)} Fetch and display all documents in this collection`);
    console.log(`  ${'create'.padEnd(22)} Create a new document`);
  }

  if (kind === 'document') {
    console.log(`\n${ansi.bold('Document commands:')}`);
    console.log(`  ${'show'.padEnd(22)} Fetch and display this document`);
    console.log(`  ${'update'.padEnd(22)} Update this document`);
    console.log(`  ${'delete'.padEnd(22)} Delete this document (with confirmation)`);
  }

  if (kind !== 'root') {
    const controllers = invokableControllers(session);
    if (Object.keys(controllers).length > 0) {
      console.log(`\n${ansi.bold('Available actions (invoke <name>):')}`);
      for (const [name, ctrl] of Object.entries(controllers)) {
        const desc = ctrl.description ?? '';
        const confirm = ctrl.requiresConfirmation ? ansi.dim(' (requires confirmation)') : '';
        console.log(`  ${'invoke ' + name.padEnd(15)} ${desc}${confirm}`);
      }
    }
  }

  const children = navigableChildren(session);
  if (kind === 'collection') {
    const url = currentUrl(session);
    const knownIds = session.knownIds.get(url) ?? [];
    if (knownIds.length > 0) {
      console.log(
        `\n${ansi.bold('Documents:')} (${knownIds.length} known — navigate with "cd <id>")`,
      );
    } else {
      console.log(`\n${ansi.dim('Run "index" to load documents and enable navigation by ID.')}`);
    }
  } else if (Object.keys(children).length > 0) {
    console.log(`\n${ansi.bold('Navigable children:')}`);
    for (const [name, child] of Object.entries(children)) {
      const desc = child.description ? ` — ${child.description}` : '';
      console.log(`  ${name.padEnd(22)} [${child.archetype}]${desc}`);
    }
  }

  console.log('');
}

function cmdLs(session: Session): void {
  const kind = nodeKind(session);
  const children = navigableChildren(session);
  const controllers = invokableControllers(session);
  let printed = false;

  console.log('');

  if (kind === 'collection') {
    const url = currentUrl(session);
    const knownIds = session.knownIds.get(url) ?? [];
    if (knownIds.length > 0) {
      console.log(`  ${ansi.bold('Documents:')} (${knownIds.length})`);
      for (const id of knownIds) {
        console.log(`    ${id}  ${ansi.dim('[document]')}`);
      }
      printed = true;
    } else {
      console.log(
        `  ${ansi.dim('Documents not yet loaded — run "index" to discover document IDs.')}`,
      );
      printed = true;
    }
  } else if (Object.keys(children).length > 0) {
    console.log(`  ${ansi.bold('Children:')}`);
    for (const [name, child] of Object.entries(children)) {
      const desc = child.description ? `  ${ansi.dim('—')} ${ansi.dim(child.description)}` : '';
      console.log(`    ${name}  ${ansi.dim(`[${child.archetype}]`)}${desc}`);
    }
    printed = true;
  }

  if (Object.keys(controllers).length > 0) {
    if (printed) console.log('');
    console.log(`  ${ansi.bold('Actions:')}`);
    for (const [name, ctrl] of Object.entries(controllers)) {
      const desc = ctrl.description ? `  ${ansi.dim('—')} ${ansi.dim(ctrl.description)}` : '';
      console.log(`    ${name}  ${ansi.dim('[controller]')}${desc}`);
    }
    printed = true;
  }

  if (!printed) {
    console.log(`  ${ansi.dim('(no children)')}`);
  }

  console.log('');
}

function cmdPwd(session: Session): void {
  const path = session.path.length === 0 ? '/' : `/${session.path.join('/')}`;
  console.log(path);
}

function cmdOutput(args: string[], session: Session): void {
  const mode = args[0];
  if (mode === 'human' || mode === 'json') {
    session.outputMode = mode;
    console.log(`Output mode: ${ansi.bold(mode)}`);
  } else {
    printError(`Unknown output mode "${mode}". Use "human" or "json".`);
  }
}

// ─── cd ───────────────────────────────────────────────────────────────────────

async function cmdCd(args: string[], rl: RlInterface, session: Session): Promise<void> {
  const segment = args[0];

  if (!segment) {
    printError('cd: missing argument');
    return;
  }

  if (segment === '..') {
    navigateUp(session);
    return;
  }

  if (segment === '/') {
    navigateRoot(session);
    return;
  }

  const kind = nodeKind(session);

  if (kind === 'root') {
    const target = session.config.resources[segment];
    if (target === undefined) {
      printError(
        `No resource "${segment}" at root. Available: ${Object.keys(session.config.resources).join(', ')}`,
      );
      return;
    }
    navigateInto(session, segment, target);
    return;
  }

  if (kind === 'collection') {
    const collectionNode = session.currentNode as CollectionConfig;
    const docConfig = collectionNode.dynamicChild;
    const url = `${currentUrl(session)}/${segment}`;
    const result = await request('GET', url);
    if (!result.ok) {
      displayError(result, session.outputMode);
      return;
    }
    navigateInto(session, segment, docConfig);
    return;
  }

  // document
  const docNode = session.currentNode as DocumentConfig;
  const target = docNode.staticChildren?.[segment];
  if (target === undefined || target.archetype === 'controller') {
    const navigable = Object.entries(docNode.staticChildren ?? {})
      .filter(([, v]) => v.archetype !== 'controller')
      .map(([k]) => k);
    const hint = navigable.length > 0 ? `Available: ${navigable.join(', ')}` : 'No navigable children.';
    printError(`"${segment}" is not a navigable child. ${hint}`);
    return;
  }
  navigateInto(session, segment, target);
}

// ─── Collection commands ──────────────────────────────────────────────────────

async function cmdIndex(session: Session): Promise<void> {
  const url = currentUrl(session);
  const result = await request('GET', url);
  if (!result.ok) {
    displayError(result, session.outputMode);
    return;
  }

  display(result.data, session.outputMode);

  // Cache document IDs for tab completion and ls
  const data = result.data as Record<string, unknown>;
  const items = data['items'];
  if (Array.isArray(items)) {
    const ids: string[] = [];
    for (const item of items) {
      const doc = item as Record<string, unknown>;
      const identifiers = doc['identifiers'] as Record<string, unknown> | undefined;
      const uid = identifiers?.['uid'];
      if (typeof uid === 'string') ids.push(uid);
    }
    session.knownIds.set(url, ids);
  }
}

async function cmdCreate(rl: RlInterface, session: Session): Promise<void> {
  const collectionNode = session.currentNode as CollectionConfig;
  const fields = collectionNode.dynamicChild.fields;
  const body = await promptFields(rl, fields ?? {});
  const envelope = { data: { archetype: 'document', body } };
  const result = await request('POST', currentUrl(session), envelope);
  if (result.ok) {
    displaySuccess('Document created.');
    display(result.data, session.outputMode);
  } else {
    displayError(result, session.outputMode);
  }
}

// ─── Document commands ────────────────────────────────────────────────────────

async function cmdShow(session: Session): Promise<void> {
  const result = await request('GET', currentUrl(session));
  if (result.ok) {
    display(result.data, session.outputMode);
  } else {
    displayError(result, session.outputMode);
  }
}

async function cmdUpdate(rl: RlInterface, session: Session): Promise<void> {
  const docNode = session.currentNode as DocumentConfig;
  const fields = docNode.fields;

  // Fetch current document to pre-populate prompts
  const fetchResult = await request('GET', currentUrl(session));
  if (!fetchResult.ok) {
    displayError(fetchResult, session.outputMode);
    return;
  }

  const responseBody =
    fetchResult.data !== null &&
    typeof fetchResult.data === 'object' &&
    !Array.isArray(fetchResult.data)
      ? ((fetchResult.data as Record<string, unknown>)['body'] as Record<string, unknown> | undefined) ?? {}
      : {};

  const body = await promptFields(rl, fields ?? {}, responseBody);
  const envelope = { data: { archetype: 'document', body } };
  const result = await request('PUT', currentUrl(session), envelope);
  if (result.ok) {
    displaySuccess('Document updated.');
    display(result.data, session.outputMode);
  } else {
    displayError(result, session.outputMode);
  }
}

async function cmdDelete(rl: RlInterface, session: Session): Promise<void> {
  const url = currentUrl(session);
  const answer = await ask(rl, `Delete ${ansi.bold(url)}? This cannot be undone. [y/N]: `);
  if (answer.trim().toLowerCase() !== 'y') {
    console.log('Cancelled.');
    return;
  }

  const result = await request('DELETE', url);
  if (result.ok) {
    displaySuccess('Deleted.');
    navigateUp(session);
  } else {
    displayError(result, session.outputMode);
  }
}

// ─── invoke ───────────────────────────────────────────────────────────────────

async function cmdInvoke(args: string[], rl: RlInterface, session: Session): Promise<void> {
  const controllerName = args[0];
  if (!controllerName) {
    printError('invoke: missing controller name. Usage: invoke <controller-name>');
    return;
  }

  const controllers = invokableControllers(session);
  const controller: ControllerConfig | undefined = controllers[controllerName];
  if (controller === undefined) {
    const available = Object.keys(controllers).join(', ') || 'none';
    printError(`No controller "${controllerName}". Available: ${available}`);
    return;
  }

  if (controller.requiresConfirmation) {
    const answer = await ask(rl, `Invoke ${ansi.bold(controllerName)}? [y/N]: `);
    if (answer.trim().toLowerCase() !== 'y') {
      console.log('Cancelled.');
      return;
    }
  }

  const params = await promptFields(rl, controller.fields ?? {});
  const envelope = { data: params };
  const url = `${currentUrl(session)}/${controllerName}`;
  const result = await request('POST', url, envelope);
  if (result.ok) {
    displaySuccess(`"${controllerName}" invoked.`);
    if (result.data !== null && result.status !== 204) {
      display(result.data, session.outputMode);
    }
  } else {
    displayError(result, session.outputMode);
  }
}

// ─── Field prompting ──────────────────────────────────────────────────────────

async function promptFields(
  rl: RlInterface,
  fields: Record<string, FieldConfig>,
  currentValues: Record<string, unknown> = {},
): Promise<Record<string, unknown>> {
  const body: Record<string, unknown> = {};

  for (const [key, fieldConfig] of Object.entries(fields)) {
    const label = fieldConfig.label ?? key;
    const hint = fieldConfig.description ? ` (${fieldConfig.description})` : '';
    const current = currentValues[key];
    const defaultHint = current !== undefined ? ` [${current}]` : '';
    const prompt = `${label}${hint}${defaultHint}: `;
    const value = await ask(rl, prompt);
    body[key] = value !== '' ? value : (current ?? '');
  }

  return body;
}
