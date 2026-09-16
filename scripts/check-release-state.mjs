import fs from 'node:fs';
import { execFileSync, spawnSync } from 'node:child_process';

const state = JSON.parse(fs.readFileSync('config/release-state.json', 'utf8'));
const failures = [];
const remote = process.env.BITMOMO_REMOTE || 'origin';
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
check('candidate ref is immutable rc-* identity', /^rc-[A-Za-z0-9._/-]+$/.test(String(state.candidate?.ref || '')));
check('candidate commit is exact SHA', sha40(state.candidate?.commit));
check('candidate tree is exact SHA', sha40(state.candidate?.tree));
check('ZIP digest is SHA-256', sha256(state.artifact?.zip_sha256));
check('runtime TAR digest is SHA-256', sha256(state.artifact?.runtime_tar_sha256));
check('artifact id is explicit', Number.isInteger(state.artifact?.id) && state.artifact.id > 0);
check('artifact name binds exact candidate commit', state.artifact?.name === `bitmomo-runtime-${state.candidate?.commit || ''}`);
check('managed runtime file count is positive', Number.isInteger(state.artifact?.managed_runtime_files) && state.artifact.managed_runtime_files > 0);
check('staging status is supported', ['not_deployed', 'deployed', 'accepted'].includes(state.staging?.status));
check('production status is supported', ['not_authorized', 'awaiting_explicit_approval', 'authorized', 'verified', 'rolled_back'].includes(state.production?.status));
check('production authorization boolean is explicit', typeof state.production?.authorized === 'boolean');
check('authorization/status are consistent', state.production?.authorized === (state.production?.status === 'authorized' || state.production?.status === 'verified'));
check('launch checkout state is explicit', typeof state.launch_profile?.checkout_enabled === 'boolean');
check('launch WhatsApp state is explicit', typeof state.launch_profile?.whatsapp_enabled === 'boolean');
check('launch mail transport is explicit', typeof state.launch_profile?.mail_transport === 'string' && state.launch_profile.mail_transport.length > 0);

const post = state.post_production || {};
check('post-production convergence flag is explicit', typeof post.must_converge_to_main === 'boolean');
check('post-production release retirement flag is explicit', typeof post.retire_release_line === 'boolean');
check('post-production ref retirement state is explicit', typeof post.refs_retired === 'boolean');
check('runtime equivalence state is explicit', typeof post.runtime_equivalence_verified === 'boolean');
check('main convergence SHA is null or exact SHA', post.main_converged_sha === null || sha40(post.main_converged_sha));

if (post.refs_retired === true) {
  check('RC/release refs retire only after production verification', state.production?.status === 'verified');
  check('retired release has converged main SHA', sha40(post.main_converged_sha));
  check('retired release has runtime equivalence proof', post.runtime_equivalence_verified === true);
}
if (post.runtime_equivalence_verified === true) {
  check('runtime equivalence proof identifies converged main SHA', sha40(post.main_converged_sha));
}

if (sha40(state.candidate?.commit)) {
  try {
    execFileSync('git', ['cat-file', '-e', `${state.candidate.commit}^{commit}`], { stdio: 'ignore' });
    const actualTree = execFileSync('git', ['rev-parse', `${state.candidate.commit}^{tree}`], { encoding: 'utf8' }).trim();
    check('recorded candidate tree matches Git object', actualTree === state.candidate.tree);

    const runtimeRaw = execFileSync('git', ['show', `${state.candidate.commit}:config/production-runtime.json`], { encoding: 'utf8' });
    const runtime = JSON.parse(runtimeRaw);
    check('recorded runtime file count matches candidate packaging contract', Number(runtime.expected_file_count) === Number(state.artifact.managed_runtime_files));
  } catch (error) {
    check(`candidate commit is available locally (run git fetch --prune ${remote})`, false);
  }
}

// During an active release verify the fetched remote-tracking RC identity rather
// than a possibly missing/stale local branch. setup-engineer.sh refreshes this
// ref first. After a verified release is converged and refs_retired=true, the
// temporary RC ref may disappear only because canonical main retains ancestry.
if (post.refs_retired !== true && state.candidate?.ref && sha40(state.candidate?.commit)) {
  const remoteRef = `refs/remotes/${remote}/${state.candidate.ref}`;
  try {
    const refCommit = execFileSync('git', ['rev-parse', `${remoteRef}^{commit}`], { encoding: 'utf8' }).trim();
    check(`fetched ${remote}/${state.candidate.ref} points to recorded candidate commit`, refCommit === state.candidate.commit);
  } catch (error) {
    check(`fetched ${remote}/${state.candidate.ref} exists (run git fetch --prune ${remote})`, false);
  }
}

if (sha40(post.main_converged_sha)) {
  try {
    execFileSync('git', ['cat-file', '-e', `${post.main_converged_sha}^{commit}`], { stdio: 'ignore' });
    check('recorded converged main commit is available locally', true);

    if (sha40(state.candidate?.commit)) {
      const ancestry = spawnSync('git', ['merge-base', '--is-ancestor', state.candidate.commit, post.main_converged_sha], { stdio: 'ignore' });
      check('accepted release commit is retained in converged trunk ancestry', ancestry.status === 0);
    }
  } catch (error) {
    check('recorded converged main commit is available locally', false);
  }
}

const currentRelease = fs.readFileSync('docs/CURRENT_RELEASE.md', 'utf8');
for (const [label, value] of [
  ['candidate ref', state.candidate?.ref],
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

const refState = post.refs_retired === true ? 'retired' : 'active';
console.log(`\nPASS release identity ${state.release_id}: ${state.candidate.ref} (${refState}) -> ${state.candidate.commit} / artifact ${state.artifact.id}`);
