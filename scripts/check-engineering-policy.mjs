import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const warnings = [];
const policyPath = path.join(root, 'config/engineering-policy.json');
const policy = JSON.parse(fs.readFileSync(policyPath, 'utf8'));

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}
function exists(rel) {
  return fs.existsSync(path.join(root, rel));
}
function check(label, ok) {
  console.log(`[${ok ? 'PASS' : 'FAIL'}] ${label}`);
  if (!ok) failures.push(label);
}
function warn(label, condition) {
  if (condition) {
    console.log(`[WARN] ${label}`);
    warnings.push(label);
  }
}

check('Engineering policy schema is supported', policy.schema === 1);
check('Repository operates in zero-cost local-first mode', policy.mode === 'zero-cost-local-first');
check('Hosted Actions policy is locked', policy.hosted_actions === 'locked');
check('Maximum normal PR stack depth is <= 2', Number(policy.max_pr_stack_depth) <= 2);

const required = [
  'docs/CURRENT_RELEASE.md',
  'docs/ENGINEERING_OPERATING_MODEL.md',
  '.github/pull_request_template.md',
  'scripts/bitmomo-check.sh',
  'scripts/production-monitor-local.sh',
  'scripts/check-engineering-policy.mjs',
  'config/engineering-policy.json',
];
for (const rel of required) check(`Required engineering contract exists: ${rel}`, exists(rel));

const workflowDir = path.join(root, '.github/workflows');
const workflows = fs.readdirSync(workflowDir).filter((name) => /\.ya?ml$/.test(name)).sort();
check('At least one workflow stub exists for explicit visibility', workflows.length > 0);
for (const name of workflows) {
  const source = fs.readFileSync(path.join(workflowDir, name), 'utf8');
  check(`${name}: no automatic pull_request trigger`, !/^\s{2}pull_request\s*:/m.test(source));
  check(`${name}: no automatic push trigger`, !/^\s{2}push\s*:/m.test(source));
  check(`${name}: no scheduled trigger`, !/^\s{2}schedule\s*:/m.test(source));
  check(`${name}: manual entry remains visible`, /^\s{2}workflow_dispatch\s*:/m.test(source));

  const jobs = source.split(/^jobs:\s*$/m)[1] || '';
  const runnableJobHeaders = [...jobs.matchAll(/^  ([A-Za-z0-9_-]+):\s*$([\s\S]*?)(?=^  [A-Za-z0-9_-]+:\s*$|\z)/gm)];
  if (runnableJobHeaders.length === 0) {
    check(`${name}: contains a hard-lock marker`, /^\s{4}if:\s*false\s*$/m.test(source));
  } else {
    for (const match of runnableJobHeaders) {
      if (!/runs-on\s*:/.test(match[2])) continue;
      check(`${name}/${match[1]}: hosted runner is hard-locked`, /^\s{4}if:\s*false\s*$/m.test(match[2]));
    }
  }
}

const customCss = 'website/wp-content/themes/bitmomo-child-v3/custom.css';
if (exists(customCss)) {
  const bytes = fs.statSync(path.join(root, customCss)).size;
  check(`Legacy custom.css does not grow past ${policy.legacy_custom_css_max_bytes} bytes`, bytes <= policy.legacy_custom_css_max_bytes);
  warn(`Legacy custom.css is at ${bytes}/${policy.legacy_custom_css_max_bytes} bytes; new styles belong to canonical modules`, bytes >= policy.legacy_custom_css_max_bytes * 0.95);
}

const prTemplate = read('.github/pull_request_template.md');
check('PR template requires local test command', prTemplate.includes('bash scripts/bitmomo-check.sh test'));
const operating = read('docs/ENGINEERING_OPERATING_MODEL.md');
check('Operating model defines zero-cost validation', /zero-cost validation model/i.test(operating));
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
