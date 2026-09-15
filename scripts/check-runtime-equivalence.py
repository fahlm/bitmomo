#!/usr/bin/env python3
import fnmatch
import json
import subprocess
import sys
from pathlib import PurePosixPath


def git(*args, text=True):
    return subprocess.check_output(['git', *args], text=text).strip() if text else subprocess.check_output(['git', *args])


def exists(ref, path):
    return subprocess.run(['git', 'cat-file', '-e', f'{ref}:{path}'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0


def show_text(ref, path):
    return git('show', f'{ref}:{path}')


def blob_sha(ref, path):
    try:
        return git('rev-parse', f'{ref}:{path}')
    except subprocess.CalledProcessError:
        return None


def match(rel, pattern):
    p = PurePosixPath(rel)
    return p.match(pattern) or fnmatch.fnmatchcase(rel, pattern)


def included(rel, includes, excludes):
    return any(match(rel, pat) for pat in includes) and not any(match(rel, pat) for pat in excludes)


def list_component_files(ref, source):
    try:
        raw = git('ls-tree', '-r', '--name-only', ref, '--', source)
    except subprocess.CalledProcessError:
        return []
    prefix = source.rstrip('/') + '/'
    result = []
    for path in raw.splitlines():
        if path.startswith(prefix):
            result.append((path, path[len(prefix):]))
    return result


def die(message):
    print(f'ERROR {message}', file=sys.stderr)
    raise SystemExit(2)


if len(sys.argv) != 3:
    die('usage: check-runtime-equivalence.py <accepted-ref> <candidate-ref>')

accepted, candidate = sys.argv[1:]
for ref in (accepted, candidate):
    try:
        git('cat-file', '-e', f'{ref}^{{commit}}')
    except subprocess.CalledProcessError:
        die(f'unknown Git ref/commit: {ref}')

config_path = 'config/production-runtime.json'
accepted_config_raw = show_text(accepted, config_path)
candidate_config_raw = show_text(candidate, config_path)
if accepted_config_raw != candidate_config_raw:
    print('FAIL production-runtime packaging definition changed between refs', file=sys.stderr)
    print(f'  accepted={accepted}', file=sys.stderr)
    print(f'  candidate={candidate}', file=sys.stderr)
    raise SystemExit(1)

config = json.loads(accepted_config_raw)
includes = config.get('include', [])
excludes = config.get('exclude', [])
components = config.get('components', [])

runtime_paths = set()
component_by_path = {}
for component in components:
    source = component['source'].rstrip('/')
    accepted_files = list_component_files(accepted, source)
    candidate_files = list_component_files(candidate, source)
    for full, rel in accepted_files + candidate_files:
        if included(rel, includes, excludes):
            runtime_paths.add(full)
            component_by_path[full] = component['name']

mismatches = []
for path in sorted(runtime_paths):
    a = blob_sha(accepted, path)
    b = blob_sha(candidate, path)
    if a != b:
        mismatches.append((component_by_path.get(path, '?'), path, a or 'MISSING', b or 'MISSING'))

accepted_runtime_count = sum(1 for path in runtime_paths if blob_sha(accepted, path))
candidate_runtime_count = sum(1 for path in runtime_paths if blob_sha(candidate, path))
expected_count = int(config.get('expected_file_count', 0))

print(f'accepted_ref={accepted} commit={git("rev-parse", accepted)}')
print(f'candidate_ref={candidate} commit={git("rev-parse", candidate)}')
print(f'accepted_runtime_files={accepted_runtime_count}')
print(f'candidate_runtime_files={candidate_runtime_count}')
print(f'expected_runtime_files={expected_count}')

if accepted_runtime_count != expected_count:
    print(f'FAIL accepted runtime inventory count {accepted_runtime_count} != packaging contract {expected_count}', file=sys.stderr)
    raise SystemExit(1)
if candidate_runtime_count != expected_count:
    print(f'FAIL candidate runtime inventory count {candidate_runtime_count} != packaging contract {expected_count}', file=sys.stderr)
    raise SystemExit(1)

if mismatches:
    print(f'FAIL runtime equivalence: {len(mismatches)} packaged file(s) differ', file=sys.stderr)
    for component, path, a, b in mismatches[:100]:
        print(f'- [{component}] {path}\n    accepted={a}\n    candidate={b}', file=sys.stderr)
    if len(mismatches) > 100:
        print(f'... {len(mismatches) - 100} more', file=sys.stderr)
    raise SystemExit(1)

print(f'PASS runtime equivalence: all {expected_count} packaged files are byte-identical and packaging definition is unchanged')
