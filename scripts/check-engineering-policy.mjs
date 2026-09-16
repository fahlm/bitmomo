import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const failures = [];
const warnings = [];
const policy = JSON.parse(fs.readFileSync(path.join(root, 'config/engineering-policy.json'), 'utf8'));

const read = (rel) => fs.readFileSync(path.join(root, rel), 'utf8');
const exists = (rel) => fs.existsSync(path.join(root, rel));
function check(label, ok) {
  console.log(`[${ok ? 'PASS' : 'FAIL'}] ${label}`);
  if (!ok) failures.push(label);
}
function warn(label, condition) {
  if (!condition) return;
  warnings.push(label);
  console.log(`[WARN] ${label}`);
}

check('Engineering policy schema is supported', policy.schema === 1);
check('Repository operates in zero-cost local-first mode', policy.mode === 'zero-cost-local-first');
check('Hosted Actions policy is locked', policy.hosted_actions === 'locked');
check('Maximum normal PR stack depth is <= 2', Number(policy.max_pr_stack_depth) <= 2);
check('Active PR target is bounded', Number(policy.target_active_open_prs) > 0 && Number(policy.target_active_open_prs) <= 5);
check('Active branch target is bounded', Number(policy.target_active_branches) > 0 && Number(policy.target_active_branches) <= 15);
check('Runtime equivalence command is canonical', /bitmomo-check\.sh equivalence/.test(policy.canonical_commands?.equivalence || ''));
check('Release candidates are declared immutable', policy.release?.accepted_candidate_immutable === true);
check('Release requires exact artifact identity', policy.release?.exact_artifact_required === true);
check('Production requires explicit owner authorization', policy.release?.production_requires_explicit_owner_authorization === true);

const required = [
  'docs/CURRENT_RELEASE.md',
  'docs/ENGINEERING_OPERATING_MODEL.md',
  'docs/ARCHITECTURE_OWNERSHIP.md',
  'docs/PRODUCTION_PIPELINE.md',
  '.github/pull_request_template.md',
  '.githooks/pre-push',
  'scripts/setup-engineer.sh',
  'scripts/bitmomo-check.sh',
  'scripts/production-monitor-local.sh',
  'scripts/check-engineering-policy.mjs',
  'scripts/check-release-state.mjs',
  'scripts/check-runtime-equivalence.py',
  'scripts/audit-frontend-debt.mjs',
  'config/engineering-policy.json',
  'config/release-state.json',
];
for (const rel of required) check(`Required engineering contract exists: ${rel}`, exists(rel));

const workflowDir = path.join(root, '.github/workflows');
const workflows = fs.readdirSync(workflowDir).filter((name) => /\.ya?ml$/.test(name)).sort();
check('Workflow stubs remain visible', workflows.length > 0);
for (const name of workflows) {
  const source = fs.readFileSync(path.join(workflowDir, name), 'utf8');
  check(`${name}: no automatic pull_request trigger`, !/^\s{2}pull_request\s*:/m.test(source));
  check(`${name}: no automatic push trigger`, !/^\s{2}push\s*:/m.test(source));
  check(`${name}: no scheduled trigger`, !/^\s{2}schedule\s*:/m.test(source));
  check(`${name}: manual entry remains visible`, /^\s{2}workflow_dispatch\s*:/m.test(source));

  const jobsPart = source.split(/^jobs:\s*$/m)[1] || '';
  const blocks = jobsPart.split(/\n(?=  [A-Za-z0-9_-]+:\s*\n)/).filter(Boolean);
  let runnableJobs = 0;
  for (const block of blocks) {
    if (!/\bruns-on\s*:/.test(block)) continue;
    runnableJobs += 1;
    const header = block.match(/^  ([A-Za-z0-9_-]+):/m)?.[1] || 'job';
    check(`${name}/${header}: hosted runner is hard-locked`, /^\s{4}if:\s*false\s*$/m.test(block));
  }
  if (runnableJobs === 0) check(`${name}: contains a hard-lock marker`, /^\s{4}if:\s*false\s*$/m.test(source));
}

const hook = read('.githooks/pre-push');
check('Pre-push guard evaluates remote destination refs', hook.includes('remote_ref'));
check('Pre-push guard protects main destination', hook.includes('refs/heads/main'));
check('Pre-push guard protects release destinations', hook.includes('refs/heads/release/*'));
check('Pre-push guard protects immutable RC destinations', hook.includes('refs/heads/rc-*'));

const customCss = 'website/wp-content/themes/bitmomo-child-v3/custom.css';
if (exists(customCss)) {
  const bytes = fs.statSync(path.join(root, customCss)).size;
  check(`Legacy custom.css does not grow past ${policy.legacy_custom_css_max_bytes} bytes`, bytes <= policy.legacy_custom_css_max_bytes);
  warn(`Legacy custom.css is at ${bytes}/${policy.legacy_custom_css_max_bytes} bytes; new styles belong to canonical modules`, bytes >= policy.legacy_custom_css_max_bytes * 0.95);
}

const tracked = execFileSync('git', ['ls-files'], { cwd: root, encoding: 'utf8' }).split(/\r?\n/).filter(Boolean);
const secretLike = tracked.filter((file) => {
  const base = path.basename(file);
  if (base === '.env.example') return false;
  return base === '.env' || base.startsWith('.env.') || /\.(?:pem|key)$/i.test(base);
});
check('No secret/private-key filename is tracked', secretLike.length === 0);
if (secretLike.length) for (const file of secretLike) console.error(`  tracked sensitive filename: ${file}`);

const prTemplate = read('.github/pull_request_template.md');
check('PR template requires local test command', prTemplate.includes('bash scripts/bitmomo-check.sh test'));
const operating = read('docs/ENGINEERING_OPERATING_MODEL.md');
check('Operating model defines zero-cost validation', /zero-cost validation model/i.test(operating));
check('Operating model defines machine-enforced contract', /machine-enforced engineering contract/i.test(operating));
check('Operating model points to machine policy', operating.includes('config/engineering-policy.json'));
const currentRelease = read('docs/CURRENT_RELEASE.md');
check('Current release records exact commit', /- Commit:\s*`[0-9a-f]{40}`/.test(currentRelease));
check('Current release records artifact identity', /- Artifact ID:\s*`?\d+`?/.test(currentRelease));
check('Current release records production state', /Production:/i.test(currentRelease));

if (failures.length) {
  console.error(`\nEngineering policy FAILED (${failures.length}).`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log(`\nPASS engineering policy (${workflows.length} workflow stubs, ${warnings.length} warning(s)).`);
