import { createInterface, type CompleterResult } from 'node:readline';
import type { Session } from './types.js';
import { nodeKind, currentUrl, invokableControllers } from './session.js';
import { dispatch } from './commands.js';
import { ansi } from './output.js';

function buildPrompt(session: Session): string {
  const path = session.path.length === 0 ? '/' : `/${session.path.join('/')}`;
  return `${path} > `;
}

function completer(line: string, session: Session): CompleterResult {
  const cdPrefix = 'cd ';
  const invokePrefix = 'invoke ';

  if (line.startsWith(cdPrefix)) {
    const partial = line.slice(cdPrefix.length);
    const kind = nodeKind(session);
    let candidates: string[] = [];

    if (kind === 'root') {
      candidates = Object.keys(session.config.resources);
    } else if (kind === 'collection') {
      candidates = session.knownIds.get(currentUrl(session)) ?? [];
    } else {
      // document — complete with non-controller static children
      const node = session.currentNode;
      if (node?.archetype === 'document') {
        candidates = Object.entries(node.staticChildren ?? {})
          .filter(([, v]) => v.archetype !== 'controller')
          .map(([k]) => k);
      }
    }

    const hits = candidates.filter((c) => c.startsWith(partial));
    const completions = (hits.length > 0 ? hits : candidates).map((c) => cdPrefix + c);
    return [completions, line];
  }

  if (line.startsWith(invokePrefix)) {
    const partial = line.slice(invokePrefix.length);
    const controllers = Object.keys(invokableControllers(session));
    const hits = controllers.filter((c) => c.startsWith(partial));
    const completions = (hits.length > 0 ? hits : controllers).map((c) => invokePrefix + c);
    return [completions, line];
  }

  // Complete top-level commands
  const kind = nodeKind(session);
  const commands = ['help', 'ls', 'pwd', 'clear', 'cd ', 'output ', 'exit', 'quit'];
  if (kind === 'collection') commands.push('index', 'create', 'invoke ');
  if (kind === 'document') commands.push('show', 'update', 'delete', 'invoke ');

  const hits = commands.filter((c) => c.startsWith(line));
  return [hits.length > 0 ? hits : commands, line];
}

export async function startRepl(session: Session): Promise<void> {
  const rl = createInterface({
    input: process.stdin,
    output: process.stdout,
    completer: (line: string): CompleterResult => completer(line, session),
  });

  rl.on('close', () => {
    console.log('\nGoodbye!');
    process.exit(0);
  });

  // Startup banner
  console.log(`\n${ansi.bold(session.config.name)}`);
  console.log(ansi.dim(session.config.baseUrl));
  console.log(`Type ${ansi.bold('help')} for available commands, ${ansi.bold('ls')} to see resources.\n`);

  while (true) {
    const line = await new Promise<string>((resolve) => rl.question(buildPrompt(session), resolve));
    const trimmed = line.trim();
    if (trimmed === '') continue;
    await dispatch(trimmed, rl, session);
  }
}
