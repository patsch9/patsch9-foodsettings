#!/usr/bin/env python3
"""Validate identical release metadata locally, on pull requests and on tags."""
import argparse
import json
import os
import re
from pathlib import Path

SLUG = 'patsch9-productsettings'
VERSION_PATTERN = re.compile(r'\d{4}\.([1-9]|1[0-2])\.(0|[1-9]\d*)(?:-(alpha|beta|rc)\.([1-9]\d*))?')
STABLE_PATTERN = re.compile(r'\d{4}\.([1-9]|1[0-2])\.(0|[1-9]\d*)')


def required(pattern, text, label):
    match = re.search(pattern, text, re.MULTILINE)
    if not match:
        raise ValueError(f'Could not read {label}.')
    return match.group(1).strip()


def validate(root, tag=None):
    plugin = (root / f'{SLUG}.php').read_text(encoding='utf-8')
    readme = (root / 'readme.txt').read_text(encoding='utf-8')
    version = required(r'^\s*\*\s*Version:\s*([^\s]+)', plugin, 'plugin version')
    constant = required(r"define\(\s*'P9PS_VERSION'\s*,\s*'([^']+)'\s*\)", plugin, 'P9PS_VERSION')
    stable = required(r'^Stable tag:\s*([^\s]+)\s*$', readme, 'Stable tag')
    if not VERSION_PATTERN.fullmatch(version):
        raise ValueError('Plugin version must follow YYYY.M.N[-alpha.N|-beta.N|-rc.N].')
    if version != constant:
        raise ValueError('Plugin header and P9PS_VERSION differ.')
    if tag is not None and tag != f'v{version}':
        raise ValueError('Release tag must be v followed by the exact plugin version.')
    prerelease = '-' in version
    if not STABLE_PATTERN.fullmatch(stable):
        raise ValueError('Stable tag must be a stable YYYY.M.N version.')
    if not prerelease and stable != version:
        raise ValueError('Stable tag and stable plugin version differ.')
    if prerelease and tuple(map(int, stable.split('.'))) >= tuple(map(int, version.split('-')[0].split('.'))):
        raise ValueError('Prereleases must retain an earlier stable tag.')
    changelog = (root / 'CHANGELOG.md').read_text(encoding='utf-8')
    if not re.search(rf'^##\s+{re.escape(version)}(?:\s|$)', changelog, re.MULTILINE):
        raise ValueError('CHANGELOG.md has no entry for the current version.')
    if not re.search(rf'^= {re.escape(version)} =\s*$', readme, re.MULTILINE):
        raise ValueError('readme.txt has no changelog entry for the current version.')
    return {'version': version, 'prerelease': prerelease}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag')
    parser.add_argument('--github-output', action='store_true')
    args = parser.parse_args()
    try:
        result = validate(Path(__file__).resolve().parents[1], args.tag)
    except (OSError, ValueError) as error:
        parser.exit(1, f'Release metadata invalid: {error}\n')
    if args.github_output:
        with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
            output.write(f"version={result['version']}\nprerelease={str(result['prerelease']).lower()}\n")
    print(json.dumps(result))
