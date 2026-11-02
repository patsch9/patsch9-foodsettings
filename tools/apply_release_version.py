#!/usr/bin/env python3
"""Apply a validated release tag to the temporary release working tree."""
import argparse
import re
from pathlib import Path

from release_metadata import SLUG, STABLE_PATTERN, parse_release_version


def replace_once(text, pattern, replacement, label, flags=0):
    updated, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count != 1:
        raise ValueError(f'Could not update {label} exactly once.')
    return updated


def apply(root, version):
    result = parse_release_version(version)
    plugin_path = root / f'{SLUG}.php'
    readme_path = root / 'readme.txt'

    plugin = plugin_path.read_text(encoding='utf-8')
    plugin = replace_once(
        plugin,
        r'(^\s*\*\s*Version:\s*)[^\s]+',
        lambda match: match.group(1) + version,
        'plugin header Version',
        re.MULTILINE,
    )
    plugin = replace_once(
        plugin,
        r"(define\(\s*'P9PS_VERSION'\s*,\s*')[^']+('\s*\)\s*;)",
        lambda match: match.group(1) + version + match.group(2),
        'P9PS_VERSION',
    )
    plugin_path.write_text(plugin, encoding='utf-8')

    readme = readme_path.read_text(encoding='utf-8')
    stable_match = re.search(r'^Stable tag:\s*([^\s]+)\s*$', readme, re.MULTILINE)
    if not stable_match:
        raise ValueError('Could not read Stable tag.')

    if result['prerelease']:
        stable = stable_match.group(1)
        if not STABLE_PATTERN.fullmatch(stable):
            raise ValueError('Prerelease requires a valid previous Stable tag.')
        stable_tuple = tuple(map(int, stable.split('.')))
        base_tuple = tuple(map(int, version.split('-', 1)[0].split('.')))
        if stable_tuple >= base_tuple:
            raise ValueError('Prerelease Stable tag must refer to an earlier stable release.')
    else:
        readme = replace_once(
            readme,
            r'(^Stable tag:\s*)[^\s]+',
            lambda match: match.group(1) + version,
            'Stable tag',
            re.MULTILINE,
        )
        readme_path.write_text(readme, encoding='utf-8')

    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('version')
    args = parser.parse_args()
    try:
        result = apply(Path(__file__).resolve().parents[1], args.version)
    except (OSError, ValueError) as error:
        parser.exit(1, f'Could not apply release version: {error}\n')
    print(result['version'])
