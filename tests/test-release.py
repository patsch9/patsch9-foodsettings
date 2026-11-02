"""Regression tests for release validation and installable package boundaries."""
import hashlib
import importlib.util
import os
from pathlib import Path
import shutil
import sys
import tempfile
import unittest
from zipfile import ZipFile

SOURCE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SOURCE / 'tools'))
from build_zip import build, REQUIRED, SLUG
from release_metadata import validate


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        for name in REQUIRED | {'CHANGELOG.md'}:
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(SOURCE / name, target)
        # Keep fixtures independent of future project version bumps.
        (self.root / f'{SLUG}.php').write_text("<?php\n/**\n * Version: 2026.11.1\n */\ndefine( 'P9PS_VERSION', '2026.11.1' );\n")
        (self.root / 'readme.txt').write_text('Stable tag: 2026.11.1\n== Changelog ==\n= 2026.11.1 =\n')
        (self.root / 'CHANGELOG.md').write_text('## 2026.11.1\n')

    def replace(self, name, old, new):
        path = self.root / name
        path.write_text(path.read_text().replace(old, new), encoding='utf-8')

    def test_matching_stable_tag(self):
        self.assertEqual(validate(self.root, 'v2026.11.1'), {'version': '2026.11.1', 'prerelease': False})

    def test_prerelease_keeps_previous_stable(self):
        for name in (f'{SLUG}.php', 'CHANGELOG.md', 'readme.txt'):
            self.replace(name, '2026.11.1', '2026.11.2-beta.1')
        self.replace('readme.txt', 'Stable tag: 2026.11.2-beta.1', 'Stable tag: 2026.11.1')
        self.assertTrue(validate(self.root, 'v2026.11.2-beta.1')['prerelease'])

    def test_rejects_mismatched_or_malicious_tags(self):
        for tag in ('2026.11.1', 'v2026.11.2', 'v2026.11.1\nnext=evil', 'v2026.11.1;id'):
            with self.subTest(tag=tag), self.assertRaises(ValueError):
                validate(self.root, tag)

    def test_rejects_constant_mismatch(self):
        self.replace(f'{SLUG}.php', "'2026.11.1'", "'2026.11.2'")
        with self.assertRaises(ValueError):
            validate(self.root)

    def test_rejects_stable_mismatch(self):
        self.replace('readme.txt', 'Stable tag: 2026.11.1', 'Stable tag: 2026.11.0')
        with self.assertRaises(ValueError):
            validate(self.root)

    def test_rejects_missing_changelogs(self):
        for name, old in (('CHANGELOG.md', '## 2026.11.1'), ('readme.txt', '= 2026.11.1 =')):
            with self.subTest(name=name):
                original = (self.root / name).read_text()
                self.replace(name, old, 'removed')
                with self.assertRaises(ValueError):
                    validate(self.root)
                (self.root / name).write_text(original)

    def test_rejects_invalid_version_scheme(self):
        for value in ('0.1.0', '2026.011.1', '2026.13.1', '2026.11.01', '2026.11.1-beta1', '2026.11.1-rc.0'):
            with self.subTest(version=value):
                original = (self.root / f'{SLUG}.php').read_text()
                self.replace(f'{SLUG}.php', '2026.11.1', value)
                with self.assertRaises(ValueError):
                    validate(self.root)
                (self.root / f'{SLUG}.php').write_text(original)

    def test_runtime_only_and_valid_checksum(self):
        for name in ('tests/private.txt', '.github/workflows/release.yml', '.env', 'README.md'):
            path = self.root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('must not be shipped')
        out = build(self.root)
        with ZipFile(out) as archive:
            self.assertEqual(set(archive.namelist()), {f'{SLUG}/{name}' for name in REQUIRED})
            self.assertIsNone(archive.testzip())
        expected = f'{hashlib.sha256(out.read_bytes()).hexdigest()}  {out.name}\n'
        self.assertEqual(out.with_suffix('.zip.sha256').read_text(), expected)

    def test_reproducible_despite_file_timestamps(self):
        first = build(self.root).read_bytes()
        os.utime(self.root / f'{SLUG}.php', (1234567890, 1234567890))
        self.assertEqual(first, build(self.root).read_bytes())

    def test_rejects_symlink_and_hidden_runtime_file(self):
        link = self.root / 'assets/secret.js'
        link.symlink_to(SOURCE / 'README.md')
        with self.assertRaises(ValueError):
            build(self.root)
        link.unlink()
        (self.root / 'assets/.secret.js').write_text('secret')
        with self.assertRaises(ValueError):
            build(self.root)

    def test_rejects_unexpected_runtime_extension(self):
        (self.root / 'assets/backup.sql').write_text('private backup')
        with self.assertRaises(ValueError):
            build(self.root)

    def test_rejects_missing_runtime_file(self):
        (self.root / 'includes/class-admin.php').unlink()
        with self.assertRaises(ValueError):
            build(self.root)


if __name__ == '__main__':
    unittest.main()
