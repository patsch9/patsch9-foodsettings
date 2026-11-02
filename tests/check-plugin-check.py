"""Fail closed on checker failures, findings, empty or unknown raw CLI output."""
import json
import re
import sys
from pathlib import Path


def clean_result(text, status):
    if status != 0:
        return False
    text = re.sub(r"\x1b\[[0-9;]*m", "", text)
    text = text.strip()
    success = "Success: Checks complete. No errors found."
    if text == success:
        return True
    # With --ignore-codes, Plugin Check emits an empty strict-json array after
    # completing checks whose only results were explicitly ignored policies.
    try:
        return json.loads(text) == []
    except (ValueError, TypeError):
        return False


if __name__ == "__main__":
    if not clean_result(Path(sys.argv[1]).read_text(), int(sys.argv[2])):
        sys.exit("Plugin Check failed, produced findings or did not report successful completion.")
    print("Plugin Check completed successfully without errors or warnings.")
