#!/usr/bin/env python3
"""Build a deterministic installable ZIP and checksum using a runtime allowlist."""
import hashlib
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
from release_metadata import SLUG, validate

REQUIRED = {
    f'{SLUG}.php', 'readme.txt', 'LICENSE', 'assets/admin.css', 'assets/admin.js',
    'data/bls/manifest.json', 'data/bls/index.json',
    *(f'includes/class-{name}.php' for name in ('admin', 'frontend', 'nutrition', 'nutriscore', 'product-data', 'recipe', 'sources', 'editor-draft')),
}
RUNTIME_DIRS = {'assets': {'.css', '.js'}, 'includes': {'.php'}, 'data': {'.json'}}


def runtime_paths(root):
    paths = [root / name for name in (f'{SLUG}.php', 'readme.txt', 'LICENSE')]
    for directory, extensions in RUNTIME_DIRS.items():
        parent = root / directory
        if parent.is_symlink() or not parent.is_dir():
            raise ValueError(f'Invalid runtime directory: {directory}')
        for path in sorted(parent.rglob('*')):
            relative = path.relative_to(root)
            if path.is_symlink() or any(part.startswith('.') for part in relative.parts):
                raise ValueError(f'Unsafe runtime path: {relative}')
            if path.is_dir():
                continue
            if not path.is_file() or path.suffix not in extensions:
                raise ValueError(f'Unexpected runtime file: {relative}')
            paths.append(path)
    for path in paths:
        if path.is_symlink() or not path.is_file():
            raise ValueError(f'Invalid runtime file: {path.relative_to(root)}')
    missing = REQUIRED - {path.relative_to(root).as_posix() for path in paths}
    if missing:
        raise ValueError(f'Missing runtime files: {sorted(missing)}')
    return sorted(paths)


def build(root):
    version = validate(root)['version']
    paths = runtime_paths(root)
    out = root / 'dist' / f'{SLUG}-{version}.zip'
    out.parent.mkdir(exist_ok=True)
    with ZipFile(out, 'w', compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for path in paths:
            info = ZipInfo(f'{SLUG}/{path.relative_to(root).as_posix()}', date_time=(1980, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = ZIP_DEFLATED
            archive.writestr(info, path.read_bytes(), compresslevel=9)
    with ZipFile(out) as archive:
        expected = {f'{SLUG}/{path.relative_to(root).as_posix()}' for path in paths}
        if set(archive.namelist()) != expected or archive.testzip() is not None:
            raise ValueError('ZIP integrity check failed.')
    out.with_suffix('.zip.sha256').write_text(f'{hashlib.sha256(out.read_bytes()).hexdigest()}  {out.name}\n', encoding='utf-8')
    return out


if __name__ == '__main__':
    print(build(Path(__file__).resolve().parents[1]))

