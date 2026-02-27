import { readFileSync } from 'node:fs';
import { parse as parseYaml } from 'yaml';
import type {
  Config,
  CollectionConfig,
  DocumentConfig,
  ControllerConfig,
  FieldConfig,
  NavigableConfig,
  AnyResourceConfig,
} from './types.js';

export function loadConfig(filePath: string): Config {
  let raw: string;
  try {
    raw = readFileSync(filePath, 'utf-8');
  } catch {
    configError(`Cannot read config file: ${filePath}`);
  }

  let parsed: unknown;
  if (filePath.endsWith('.yaml') || filePath.endsWith('.yml')) {
    try {
      parsed = parseYaml(raw);
    } catch (e) {
      configError(`Failed to parse YAML: ${(e as Error).message}`);
    }
  } else {
    try {
      parsed = JSON.parse(raw);
    } catch (e) {
      configError(`Failed to parse JSON: ${(e as Error).message}`);
    }
  }

  return validateConfig(parsed);
}

function validateConfig(raw: unknown): Config {
  assertObject(raw, 'config root');

  const obj = raw as Record<string, unknown>;

  if (typeof obj['name'] !== 'string' || !obj['name']) {
    configError('Config must have a non-empty "name" string field');
  }

  if (typeof obj['baseUrl'] !== 'string' || !obj['baseUrl']) {
    configError('Config must have a non-empty "baseUrl" string field');
  }

  try {
    new URL(obj['baseUrl'] as string);
  } catch {
    configError(`"baseUrl" is not a valid URL: ${obj['baseUrl']}`);
  }

  if (!isPlainObject(obj['resources'])) {
    configError('"resources" must be an object (map of resource nodes)');
  }

  const resources: Record<string, CollectionConfig | DocumentConfig> = {};
  for (const [key, value] of Object.entries(obj['resources'] as Record<string, unknown>)) {
    resources[key] = validateNavigableNode(value, key);
  }

  return {
    name: obj['name'] as string,
    baseUrl: (obj['baseUrl'] as string).replace(/\/$/, ''),
    resources,
  };
}

function validateNavigableNode(raw: unknown, path: string): NavigableConfig {
  assertObject(raw, path);
  const obj = raw as Record<string, unknown>;
  const archetype = obj['archetype'];

  if (archetype === 'collection') return validateCollectionNode(obj, path);
  if (archetype === 'document') return validateDocumentNode(obj, path);

  configError(
    `Node at "${path}" has unknown or missing archetype "${archetype}". ` +
      'Expected "collection" or "document" at the top level.',
  );
}

function validateAnyNode(raw: unknown, path: string): AnyResourceConfig {
  assertObject(raw, path);
  const obj = raw as Record<string, unknown>;
  const archetype = obj['archetype'];

  if (archetype === 'collection') return validateCollectionNode(obj, path);
  if (archetype === 'document') return validateDocumentNode(obj, path);
  if (archetype === 'controller') return validateControllerNode(obj, path);

  configError(`Node at "${path}" has unknown or missing archetype "${archetype}"`);
}

function validateCollectionNode(obj: Record<string, unknown>, path: string): CollectionConfig {
  if (!obj['dynamicChild']) {
    configError(`Collection at "${path}" must define a "dynamicChild" document node`);
  }

  assertObject(obj['dynamicChild'], `${path}.dynamicChild`);
  const dynamicChild = validateDocumentNode(
    obj['dynamicChild'] as Record<string, unknown>,
    `${path}.dynamicChild`,
  );

  const staticChildren: Record<string, ControllerConfig> = {};
  if (obj['staticChildren'] !== undefined) {
    if (!isPlainObject(obj['staticChildren'])) {
      configError(`"${path}.staticChildren" must be an object`);
    }
    for (const [key, value] of Object.entries(obj['staticChildren'] as Record<string, unknown>)) {
      const child = validateAnyNode(value, `${path}.staticChildren.${key}`);
      if (child.archetype !== 'controller') {
        configError(
          `"${path}.staticChildren.${key}" must be a controller ` +
            '(collection staticChildren can only contain controllers)',
        );
      }
      staticChildren[key] = child as ControllerConfig;
    }
  }

  return {
    archetype: 'collection',
    description: stringOrUndefined(obj['description']),
    dynamicChild,
    ...(Object.keys(staticChildren).length > 0 ? { staticChildren } : {}),
  };
}

function validateDocumentNode(obj: Record<string, unknown>, path: string): DocumentConfig {
  const fields =
    obj['fields'] !== undefined ? validateFieldMap(obj['fields'], `${path}.fields`) : undefined;

  const staticChildren: Record<string, CollectionConfig | DocumentConfig | ControllerConfig> = {};
  if (obj['staticChildren'] !== undefined) {
    if (!isPlainObject(obj['staticChildren'])) {
      configError(`"${path}.staticChildren" must be an object`);
    }
    for (const [key, value] of Object.entries(obj['staticChildren'] as Record<string, unknown>)) {
      staticChildren[key] = validateAnyNode(value, `${path}.staticChildren.${key}`);
    }
  }

  return {
    archetype: 'document',
    description: stringOrUndefined(obj['description']),
    ...(fields !== undefined ? { fields } : {}),
    ...(Object.keys(staticChildren).length > 0 ? { staticChildren } : {}),
  };
}

function validateControllerNode(obj: Record<string, unknown>, path: string): ControllerConfig {
  const fields =
    obj['fields'] !== undefined ? validateFieldMap(obj['fields'], `${path}.fields`) : undefined;

  return {
    archetype: 'controller',
    description: stringOrUndefined(obj['description']),
    requiresConfirmation: obj['requiresConfirmation'] === true,
    ...(fields !== undefined ? { fields } : {}),
  };
}

function validateFieldMap(raw: unknown, path: string): Record<string, FieldConfig> {
  if (!isPlainObject(raw)) {
    configError(`"${path}" must be an object (map of field configs)`);
  }

  const result: Record<string, FieldConfig> = {};
  for (const [key, value] of Object.entries(raw as Record<string, unknown>)) {
    assertObject(value, `${path}.${key}`);
    const fobj = value as Record<string, unknown>;
    result[key] = {
      label: stringOrUndefined(fobj['label']),
      description: stringOrUndefined(fobj['description']),
      required: fobj['required'] === true,
    };
  }
  return result;
}

function isPlainObject(val: unknown): val is Record<string, unknown> {
  return typeof val === 'object' && val !== null && !Array.isArray(val);
}

function assertObject(val: unknown, path: string): asserts val is Record<string, unknown> {
  if (!isPlainObject(val)) {
    configError(`"${path}" must be an object, got ${typeof val}`);
  }
}

function stringOrUndefined(val: unknown): string | undefined {
  return typeof val === 'string' ? val : undefined;
}

function configError(message: string): never {
  console.error(`Config error: ${message}`);
  process.exit(1);
}
