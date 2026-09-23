#!/usr/bin/env node
/**
 * Print the e2e specs in a seeded random order, as the comma-separated list
 * `cypress run --spec` expects.
 *
 * The point is order independence: several specs share submission fixtures and
 * each is supposed to restore what it changed, and only a different order can
 * show that one of them does not. `make test-e2e-reverse` checks the one other
 * order that costs nothing to reproduce; this checks the rest — but only ever
 * from a seed, which is printed and can be passed back in, so a failure is a
 * bug with a repro rather than a flake nobody can reproduce.
 *
 *   node dev/shuffle-specs.mjs <seed> [glob-directory]
 *
 * The chosen order goes to stderr; only the --spec list goes to stdout.
 */
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

const seedArg = process.argv[2];
const directory = process.argv[3] || 'cypress/tests/e2e';

if (!seedArg || !/^\d+$/.test(seedArg)) {
  console.error('Usage: node dev/shuffle-specs.mjs <seed> [directory]  (seed must be a non-negative integer)');
  process.exit(1);
}

/** mulberry32 — small, seeded, and identical on every platform and Node version. */
function makeRandom(seed) {
  let state = seed >>> 0;
  return () => {
    state = (state + 0x6d2b79f5) >>> 0;
    let t = state;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const specs = readdirSync(directory)
  .filter((name) => name.endsWith('.cy.js'))
  .sort()
  .map((name) => join(directory, name));

if (specs.length === 0) {
  console.error(`No *.cy.js specs found in ${directory}`);
  process.exit(1);
}

const random = makeRandom(Number(seedArg));
for (let i = specs.length - 1; i > 0; i--) {
  const j = Math.floor(random() * (i + 1));
  [specs[i], specs[j]] = [specs[j], specs[i]];
}

console.error(`Spec order for seed ${seedArg}:`);
specs.forEach((spec, index) => console.error(`  ${index + 1}. ${spec}`));

process.stdout.write(specs.join(','));
