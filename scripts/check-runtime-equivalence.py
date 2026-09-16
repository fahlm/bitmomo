#!/usr/bin/env python3
import fnmatch
import json
import subprocess
import sys


def git(*args, text=True):
    return subprocess.check_output(['git', *args], text=text).strip() if text else subprocess.check_output(['git', *args])


def show_text(ref, path):
    return git('show', f'{ref}:{path}')


def blob_sha(ref, path):
    try:
        return git('rev-parse', f'{ref}:{path}')
    except subprocess.CalledProcessError:
        return None


def matches(rel, patterns):
    # Keep this intentionally identical to scripts/build-production-artifact.py:
    # artifact membership is defined by fnmatch.fnmatchcase on the path relative
    # to each component root. The equivalence gate must never invent a second
    # interpretation of production packaging.
    return any(fnmatch.fnmatchcase(rel, pattern) for pattern in patterns)


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


def inventory(ref, config):
    includes = config.get('include', [])
    excludes = config.get('exclude', [])
    packaged = {}
    unexpected = []
    component_counts = {}

    for component in config.get('components', []):
        source = component['source'].rstrip('/')
        included_rel = []
        for full, rel in list_component_files(ref, source):
            if matches(rel, excludes):
                continue
            if not matches(rel, includes):
                unexpected.append(full)
                continue
            included_rel.append(rel)
            packaged[full] = component['name']

        missing = sorted(set(component.get('required', [])) - set(included_rel))
        if missing:
            print(
                f"FAIL {ref}: {component['name']} missing required runtime files: {', '.join(missing)}",
                file=sys.stderr,
            )
            raise SystemExit(1)

        expected_component_count = int(component.get('expected_file_count', 0))
        actual_component_count = len(included_rel)
        component_counts[component['name']] = actual_component_count
        if actual_component_count != expected_component_count:
            print(
                f"FAIL {ref}: {component['name']} runtime files={actual_component_count} expected={expected_component_count}",
                file=sys.stderr,
            )
            raise SystemExit(1)

    if unexpected:
        print(f'FAIL {ref}: unexpected managed source files would make production packaging fail', file=sys.stderr)
        for path in unexpected[:100]:
            print(f'- {path}', file=sys.stderr)
        if len(unexpected) > 100:
            print(f'... {len(unexpected) - 100} more', file=sys.stderr)
        raise SystemExit(1)

    expected_total = int(config.get('expected_file_count', 0))
    if len(packaged) != expected_total:
        print(f'FAIL {ref}: runtime inventory={len(packaged)} expected={expected_total}', file=sys.stderr)
        raise SystemExit(1)

    return packaged, component_counts


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
accepted_inventory, accepted_counts = inventory(accepted, config)
candidate_inventory, candidate_counts = inventory(candidate, config)

accepted_paths = set(accepted_inventory)
candidate_paths = set(candidate_inventory)
if accepted_paths != candidate_paths:
    added = sorted(candidate_paths - accepted_paths)
    removed = sorted(accepted_paths - candidate_paths)
    print('FAIL runtime path inventory differs between refs', file=sys.stderr)
    for path in removed[:100]:
        print(f'- REMOVED {path}', file=sys.stderr)
    for path in added[:100]:
        print(f'- ADDED {path}', file=sys.stderr)
    raise SystemExit(1)

mismatches = []
for path in sorted(accepted_paths):
    a = blob_sha(accepted, path)
    b = blob_sha(candidate, path)
    if a != b:
        mismatches.append((accepted_inventory.get(path, '?'), path, a or 'MISSING', b or 'MISSING'))

expected_count = int(config.get('expected_file_count', 0))
print(f'accepted_ref={accepted} commit={git("rev-parse", accepted)}')
print(f'candidate_ref={candidate} commit={git("rev-parse", candidate)}')
print(f'accepted_runtime_files={len(accepted_paths)}')
print(f'candidate_runtime_files={len(candidate_paths)}')
print(f'expected_runtime_files={expected_count}')
for name in sorted(accepted_counts):
    print(f'component={name} accepted={accepted_counts[name]} candidate={candidate_counts.get(name, 0)}')

if mismatches:
    print(f'FAIL runtime equivalence: {len(mismatches)} packaged file(s) differ', file=sys.stderr)
    for component, path, a, b in mismatches[:100]:
        print(f'- [{component}] {path}\n    accepted={a}\n    candidate={b}', file=sys.stderr)
    if len(mismatches) > 100:
        print(f'... {len(mismatches) - 100} more', file=sys.stderr)
    raise SystemExit(1)

print(
    f'PASS runtime equivalence: all {expected_count} packaged files are byte-identical, '
    'component inventories match the production packager, and packaging definition is unchanged'
)
