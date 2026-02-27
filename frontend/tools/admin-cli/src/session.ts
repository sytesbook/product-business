import type {
  Config,
  CollectionConfig,
  DocumentConfig,
  ControllerConfig,
  NavigableConfig,
  Session,
} from './types.js';

export function createSession(config: Config): Session {
  return {
    config,
    path: [],
    configStack: [],
    currentNode: null,
    knownIds: new Map(),
    outputMode: 'human',
  };
}

export type NodeKind = 'root' | 'collection' | 'document';

export function nodeKind(session: Session): NodeKind {
  if (session.currentNode === null) return 'root';
  return session.currentNode.archetype as NodeKind;
}

export function currentUrl(session: Session): string {
  const base = session.config.baseUrl;
  if (session.path.length === 0) return base;
  return `${base}/${session.path.join('/')}`;
}

/**
 * Returns child nodes the user can `cd` into (non-controller archetypes).
 * At root: top-level resources.
 * At collection: empty (only dynamic document IDs, resolved at runtime).
 * At document: non-controller staticChildren.
 */
export function navigableChildren(session: Session): Record<string, NavigableConfig> {
  if (session.currentNode === null) {
    const result: Record<string, NavigableConfig> = {};
    for (const [key, value] of Object.entries(session.config.resources)) {
      result[key] = value;
    }
    return result;
  }

  if (session.currentNode.archetype === 'collection') {
    return {};
  }

  const doc = session.currentNode as DocumentConfig;
  const result: Record<string, NavigableConfig> = {};
  for (const [key, value] of Object.entries(doc.staticChildren ?? {})) {
    if (value.archetype !== 'controller') {
      result[key] = value as NavigableConfig;
    }
  }
  return result;
}

/**
 * Returns controller children that can be invoked from the current node.
 * Works for both collection nodes (staticChildren) and document nodes (staticChildren).
 */
export function invokableControllers(session: Session): Record<string, ControllerConfig> {
  if (session.currentNode === null) return {};

  const children =
    session.currentNode.archetype === 'collection'
      ? ((session.currentNode as CollectionConfig).staticChildren ?? {})
      : ((session.currentNode as DocumentConfig).staticChildren ?? {});

  const result: Record<string, ControllerConfig> = {};
  for (const [key, value] of Object.entries(children)) {
    if (value.archetype === 'controller') {
      result[key] = value as ControllerConfig;
    }
  }
  return result;
}

/** Navigate into a child node by name or dynamic ID. Returns an error string on failure. */
export function navigateInto(
  session: Session,
  segment: string,
  resolvedConfig: NavigableConfig,
): void {
  session.path.push(segment);
  session.configStack.push(resolvedConfig);
  session.currentNode = resolvedConfig;
}

/** Navigate to parent. No-op at root. */
export function navigateUp(session: Session): void {
  if (session.path.length === 0) return;
  session.path.pop();
  session.configStack.pop();
  session.currentNode = session.configStack[session.configStack.length - 1] ?? null;
}

/** Navigate to root. */
export function navigateRoot(session: Session): void {
  session.path = [];
  session.configStack = [];
  session.currentNode = null;
}
