#!/usr/bin/env python3
"""Create the redistributable nine-nutrient subset from the official BLS 4.0 workbook.

Usage: python tools/build_bls.py /path/to/BLS_4_0_Daten_2025_DE.xlsx
Requires openpyxl for maintainers only. No Python dependency in WordPress.
"""
import hashlib
import json
import sys
from collections import defaultdict
from pathlib import Path
from openpyxl import load_workbook

source = Path(sys.argv[1])
target = Path(__file__).resolve().parents[1] / 'data' / 'bls'
target.mkdir(parents=True, exist_ok=True)
rows = iter(load_workbook(source, read_only=True, data_only=True).active.values)
headers = next(rows)
codes = {'energy_kj': 'ENERCJ', 'energy_kcal': 'ENERCC', 'fat': 'FAT',
         'saturates': 'FASAT', 'carbs': 'CHO', 'sugars': 'SUGAR',
         'fibre': 'FIBT', 'protein': 'PROT625', 'salt': 'NACL'}
positions = {key: next(i for i, h in enumerate(headers) if h and h.split()[0] == code)
             for key, code in codes.items()}
groups, index = defaultdict(dict), {}
unknown = 0
for row in rows:
    if not row[0]:
        continue
    nutrients = {}
    for key, pos in positions.items():
        value = row[pos]
        # '-', <LOD and <LOQ are missing/limits, never numerical zero.
        if isinstance(value, (int, float)) and not isinstance(value, bool):
            nutrients[key] = value
        else:
            nutrients[key] = None
            unknown += 1
    index[row[0]] = row[1]
    groups[row[0][0]][row[0]] = {'name': row[1], 'nutrients': nutrients}
for letter, data in groups.items():
    (target / f'{letter}.json').write_text(json.dumps(data, ensure_ascii=False, separators=(',', ':')) + '\n')
(target / 'index.json').write_text(json.dumps(index, ensure_ascii=False, separators=(',', ':')) + '\n')
manifest = {'source': 'Max Rubner-Institut (2025): Bundeslebensmittelschlüssel (BLS), Version 4.0 – Deutsche Nährstoffdatenbank. Karlsruhe.',
            'doi': '10.25826/Data20251217-134202-0', 'license': 'CC BY 4.0',
            'source_file': source.name, 'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
            'records': len(index), 'unknown_nutrient_values': unknown, 'nutrient_codes': codes,
            'changes': 'Auswahl von neun Nährwerten und deutschem Namen; Umformatierung in JSON; nichtnumerische Nachweisgrenzen und fehlende Werte als null. Numerische Werte unverändert. Keine Allergendaten.'}
(target / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
print(f'{len(index)} BLS foods; {unknown} unknown/limit values; {len(groups)} data files')
