#!/usr/bin/env python3
"""Build the install/update archive with the stable plugin directory name."""
import re
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parents[1]
version = re.search(r'^ \* Version: (.+)$', (root / 'patsch9-productsettings.php').read_text(), re.M)[1]
out = root / 'dist' / f'patsch9-productsettings-{version}.zip'
out.parent.mkdir(exist_ok=True)
paths = [root / 'patsch9-productsettings.php', root / 'readme.txt', root / 'LICENSE', root / 'README.md']
for name in ('assets', 'includes', 'data'):
    paths.extend(p for p in (root / name).rglob('*') if p.is_file())
with ZipFile(out, 'w', compression=ZIP_DEFLATED, compresslevel=9) as z:
    for p in sorted(paths):
        z.write(p, 'patsch9-productsettings/' + str(p.relative_to(root)))
with ZipFile(out) as z:
    assert all(n.startswith('patsch9-productsettings/') for n in z.namelist())
    assert 'patsch9-productsettings/patsch9-productsettings.php' in z.namelist()
print(out)
