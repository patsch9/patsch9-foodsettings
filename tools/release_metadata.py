#!/usr/bin/env python3
"""Validate source metadata and release tags."""
import argparse
import json
import os
import re
from pathlib import Path

SLUG = 'patsch9-foodsettings'
VERSION_PATTERN = re.compile(r'\d{4}\.(0[1-9]|1[0-2])\.([1-9]\d*)(?:-(alpha|beta)([1-9]\d*))?')
STABLE_PATTERN = re.compile(r'\d{4}\.(0[1-9]|1[0-2])\.([1-9]\d*)')


def required(pattern, text, label):
    match = re.search(pattern, text, re.MULTILINE)
    if not match:
        raise ValueError(f'Could not read {label}.')
    return match.group(1).strip()


def parse_release_version(value):
    match = VERSION_PATTERN.fullmatch(value)
    if not match:
        raise ValueError('Version must follow YYYY.MM.N, YYYY.MM.N-alphaN or YYYY.MM.N-betaN.')
    return {'version': value, 'prerelease': match.group(3) is not None}


def validate(root, tag=None):
    plugin = (root / f'{SLUG}.php').read_text(encoding='utf-8')
    readme = (root / 'readme.txt').read_text(encoding='utf-8')
    version = required(r'^\s*\*\s*Version:\s*([^\s]+)', plugin, 'plugin version')
    constant = required(r"define\(\s*'PATSCH9FS_VERSION'\s*,\s*'([^']+)'\s*\)", plugin, 'PATSCH9FS_VERSION')
    stable = required(r'^Stable tag:\s*([^\s]+)\s*$', readme, 'Stable tag')

    result = parse_release_version(version)
    if version != constant:
        raise ValueError('Plugin header and PATSCH9FS_VERSION differ.')

    if tag is not None:
        parse_release_version(tag)
        if tag != version:
            raise ValueError('Release tag must exactly match the generated plugin version.')
        if stable != version:
            raise ValueError('Generated release package must use the release tag as Stable tag.')
    else:
        if not STABLE_PATTERN.fullmatch(stable):
            raise ValueError('Source Stable tag must be a stable YYYY.MM.N version.')
        if not result['prerelease'] and stable != version:
            raise ValueError('Stable tag and stable plugin version differ.')
        if result['prerelease']:
            stable_tuple = tuple(map(int, stable.split('.')))
            base_tuple = tuple(map(int, version.split('-', 1)[0].split('.')))
            if stable_tuple >= base_tuple:
                raise ValueError('Source prereleases must retain an earlier stable tag.')

        changelog = (root / 'CHANGELOG.md').read_text(encoding='utf-8')
        if not re.search(rf'^##\s+{re.escape(version)}(?:\s|$)', changelog, re.MULTILINE):
            raise ValueError('CHANGELOG.md has no entry for the current source version.')
        if not re.search(rf'^= {re.escape(version)} =\s*$', readme, re.MULTILINE):
            raise ValueError('readme.txt has no changelog entry for the current source version.')

    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag')
    parser.add_argument('--tag-only')
    parser.add_argument('--github-output', action='store_true')
    args = parser.parse_args()
    try:
        if args.tag_only:
            result = parse_release_version(args.tag_only)
        else:
            result = validate(Path(__file__).resolve().parents[1], args.tag)
    except (OSError, ValueError) as error:
        parser.exit(1, f'Release metadata invalid: {error}\n')
    if args.github_output:
        with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
            output.write(f"version={result['version']}\nprerelease={str(result['prerelease']).lower()}\n")
    print(json.dumps(result))
