import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];

function read(relative) {
  return fs.readFileSync(path.join(root, relative), 'utf8');
}

function check(label, condition) {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
}

function hasTopLevelTrigger(source, trigger) {
  return new RegExp(`^  ${trigger}:`, 'm').test(source);
}

function hasCancelInProgress(source) {
  return /concurrency:\s*[\s\S]*?cancel-in-progress:\s*true/.test(source);
}

function maxTimeout(source) {
  const values = [...source.matchAll(/timeout-minutes:\s*(\d+)/g)].map((match) => Number(match[1]));
  return values.length ? Math.max(...values) : Infinity;
}

function hasDraftGuard(source) {
  return /github\.event_name != 'pull_request' \|\| github\.event\.pull_request\.draft == false/.test(source);
}

function hasReadOnlyContents(source) {
  return /permissions:\s*\n\s*contents:\s*read/.test(source);
}

const release = read('.github/workflows/release-safety.yml');
const production = read('.github/workflows/production-synthetic-monitor.yml');
const theme = read('.github/workflows/theme-safety.yml');
const authority = read('.github/workflows/authority-surface-safety.yml');
const regime = read('.github/workflows/regime-safety.yml');
const browser = read('.github/workflows/ui-browser-safety.yml');
const governance = read('.github/workflows/ci-governance-safety.yml');

check('Full Release is manual-only',
  hasTopLevelTrigger(release, 'workflow_dispatch') &&
  !hasTopLevelTrigger(release, 'pull_request') &&
  !hasTopLevelTrigger(release, 'push') &&
  !hasTopLevelTrigger(release, 'schedule')
);
check('Full Release requires an exact candidate SHA input',
  /candidate_sha:[\s\S]*?required:\s*true/.test(release) &&
  /EXPECTED_CANDIDATE_SHA/.test(release) &&
  /actual_sha=.*git rev-parse HEAD/.test(release) &&
  /actual_sha.*EXPECTED_CANDIDATE_SHA/.test(release)
);
check('Full Release is restricted to release branches or main',
  /refs\/heads\/release\/\*\|refs\/heads\/main/.test(release)
);
check('Full Release cancels superseded work and has a hard timeout <= 12m',
  hasCancelInProgress(release) && maxTimeout(release) <= 12
);

check('Production monitor cannot be triggered by PR or push',
  hasTopLevelTrigger(production, 'schedule') &&
  hasTopLevelTrigger(production, 'workflow_dispatch') &&
  !hasTopLevelTrigger(production, 'pull_request') &&
  !hasTopLevelTrigger(production, 'push')
);
check('Production monitor cancels duplicate work and is capped at 5m',
  hasCancelInProgress(production) && maxTimeout(production) <= 5
);

for (const [name, source] of [
  ['Theme', theme],
  ['Authority', authority],
  ['Regime', regime],
]) {
  check(`${name} safety is path-scoped PR feedback`,
    hasTopLevelTrigger(source, 'pull_request') && /paths:/.test(source)
  );
  check(`${name} safety skips draft PRs`, hasDraftGuard(source));
  check(`${name} safety cancels superseded runs`, hasCancelInProgress(source));
  check(`${name} safety has a hard timeout <= 5m`, maxTimeout(source) <= 5);
}

check('Browser audit is operational/manual, never PR-triggered',
  hasTopLevelTrigger(browser, 'workflow_dispatch') &&
  hasTopLevelTrigger(browser, 'schedule') &&
  !hasTopLevelTrigger(browser, 'pull_request') &&
  !hasTopLevelTrigger(browser, 'push')
);
check('Browser audit cancels superseded runs and is capped at 20m',
  hasCancelInProgress(browser) && maxTimeout(browser) <= 20
);

check('CI governance runs only for CI-policy changes or manual audit',
  hasTopLevelTrigger(governance, 'pull_request') &&
  hasTopLevelTrigger(governance, 'workflow_dispatch') &&
  /paths:[\s\S]*?\.github\/workflows\/\*\*/.test(governance) &&
  /scripts\/check-ci-governance\.mjs/.test(governance) &&
  !hasTopLevelTrigger(governance, 'push') &&
  !hasTopLevelTrigger(governance, 'schedule')
);
check('CI governance is intentionally draft-active, cancelable, and capped at 2m',
  !hasDraftGuard(governance) &&
  hasCancelInProgress(governance) &&
  maxTimeout(governance) <= 2
);

for (const [name, source] of [
  ['Full Release', release],
  ['Production monitor', production],
  ['Theme safety', theme],
  ['Authority safety', authority],
  ['Regime safety', regime],
  ['Browser safety', browser],
  ['CI governance', governance],
]) {
  check(`${name} uses least-privilege contents: read`, hasReadOnlyContents(source));
}

if (failures.length) {
  console.error(`CI governance contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS CI governance contract: expensive work is explicit, scoped, bounded, and cancelable.');
