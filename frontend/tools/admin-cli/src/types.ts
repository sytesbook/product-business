export interface Config {
  name: string;
  baseUrl: string;
  resources: Record<string, CollectionConfig | DocumentConfig>;
}

export interface CollectionConfig {
  archetype: 'collection';
  description?: string;
  // Collection-level controller actions (e.g. bulk operations).
  staticChildren?: Record<string, ControllerConfig>;
  // Template config for document items accessed by dynamic ID.
  dynamicChild: DocumentConfig;
}

export interface DocumentConfig {
  archetype: 'document';
  description?: string;
  // Body fields for create (via parent collection) and update operations.
  // Keyed by field name (the key in the request body object).
  fields?: Record<string, FieldConfig>;
  // Named child resources: sub-collections, named sub-documents, or controller actions.
  staticChildren?: Record<string, CollectionConfig | DocumentConfig | ControllerConfig>;
}

export interface ControllerConfig {
  archetype: 'controller';
  description?: string;
  requiresConfirmation?: boolean;
  // Invocation parameters. Keyed by parameter name.
  fields?: Record<string, FieldConfig>;
}

export interface FieldConfig {
  label?: string;
  description?: string;
  required?: boolean;
}

export type NavigableConfig = CollectionConfig | DocumentConfig;

export type AnyResourceConfig = CollectionConfig | DocumentConfig | ControllerConfig;

export interface Session {
  config: Config;
  // URL path segments, e.g. ['sites', 'abc-123', 'domains'].
  path: string[];
  // configStack[i] is the NavigableConfig for path[i].
  // For a dynamic document ID segment the collection's dynamicChild DocumentConfig is used.
  // Length always equals path.length.
  configStack: NavigableConfig[];
  currentNode: NavigableConfig | null;
  // collection URL → known document IDs (populated by the index command)
  knownIds: Map<string, string[]>;
  outputMode: 'human' | 'json';
}

export interface HttpResult {
  ok: boolean;
  status: number;
  data: unknown;
}
