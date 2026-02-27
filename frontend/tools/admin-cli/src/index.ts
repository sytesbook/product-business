#!/usr/bin/env node
import { loadConfig } from './config.js';
import { createSession } from './session.js';
import { startRepl } from './repl.js';

const args = process.argv.slice(2);
const configPath = args[0];

if (!configPath) {
  console.error('Usage: sytes-admin <config-file>');
  console.error('Example: sytes-admin ./configs/content-service.yaml');
  process.exit(1);
}

const config = loadConfig(configPath);
const session = createSession(config);
await startRepl(session);
