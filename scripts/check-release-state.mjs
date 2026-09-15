import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

const state = JSON.parse(fs.readFileSync('config/release-state.json', 'utf8'));
const failures = [];
const check = (label, ok) => {
  console.log(`[${ok ? 'PASS' : 'FAIL'}] ${label}`);
  if (!ok) failures.push(label);
};
const sha40 = (value) => /^[0-9a-f]{40}$/.test(String(value || ''));
const sha256 = (value) => /^[0-9a-f]{64}$/.test(String(value || ''));

check('release-state schema is supported', state.schema === 1);
check('release id is explicit', typeof state.release_id === 'string' && state.release_id.length > 0);
check('authority PR is explicit', Number.isInteger(state.authority_pr) && state.authority_pr > 0);
check('coordination issue is explicit', Number.isInteger(state.coordination_issue) && state.coordination_issue > 0);
check('candidate commit is exact SHA', sha40(state.candidate?.commit));
check('candidate tree is exact SHA', sha40(state.candidate?.tree));
check('ZIP digest is SHA-256', sha256(state.artifact?.zip_sha256));
check('runtime TAR digest is SHA-256', sha256(state.artifact?.runtime_tar_sha256));
check('artifact id is explicit', Number.isInteger(state.artifact?.id) && state.artifact.id > 0);
check('managed runtime file count is positive', Number.isInteger(state.artifact?.managed_runtime_files) && state.artifact.managed_runtime_files > 0);
check('staging status is supported', ['not_deployed', 'deployed', 'accepted'].includes(state.staging?.status));
check('production status is supported', ['not_authorized', 'awaiting_explicit_approval', 'authorized', 'verified', 'rolled_back'].includes(state.production?.status));
check('production authorization boolean is explicit', typeof state.production?.authorized === 'boolean');
check('authorization/status are consistent', state.production?.authorized === (state.production?.status === 'authorized' || state.production?.status === 'verified'));

if (sha40(state.candidate?.commit)) {
  try {
    execFileSync('git', ['cat-file', '-e', `${state.candidate.commit}^{commit}`], { stdio: 'ignore' });
    const actualTree = execFileSync('git', ['rev-parse', `${state.candidate.commit}^{tree}`], { encoding: 'utf8' }).trim();
    check('recorded candidate tree matches Git object', actualTree === state.candidate.tree);

    const runtimeRaw = execFileSync('git', ['show', `${state.candidate.commit}:config/production-runtime.json`], { encoding: 'utf8' });
    const runtime = JSON.parse(runtimeRaw);
    check('recorded runtime file count matches candidate packaging contract', Number(runtime.expected_file_count) === Number(state.artifact.managed_runtime_files));
  } catch (error) {
    check('candidate commit is available locally (git fetch origin if needed)', false);
  }
}

const currentRelease = fs.readFileSync('docs/CURRENT_RELEASE.md', 'utf8');
for (const [label, value] of [
  ['candidate commit', state.candidate?.commit],
  ['candidate tree', state.candidate?.tree],
  ['artifact id', String(state.artifact?.id || '')],
  ['ZIP digest', state.artifact?.zip_sha256],
  ['runtime TAR digest', state.artifact?.runtime_tar_sha256],
]) {
  check(`CURRENT_RELEASE mirrors ${label}`, Boolean(value) && currentRelease.includes(value));
}

if (failures.length) {
  console.error(`\nRelease identity FAILED (${failures.length}).`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log(`\nPASS release identity ${state.release_id}: ${state.candidate.commit} / artifact ${state.artifact.id}`);
