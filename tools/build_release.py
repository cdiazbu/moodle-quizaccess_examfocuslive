#!/usr/bin/env python3
"""Build the Moodle ZIP from the tracked release files."""

import argparse
import hashlib
from pathlib import Path
import re
import subprocess
import zipfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("output_dir", type=Path)
    args = parser.parse_args()
    root = Path(__file__).resolve().parent.parent
    version = re.search(r"\$plugin->release = '([0-9.]+)';", (root / "version.php").read_text(encoding="utf-8"))
    if not version:
        raise ValueError("The release must be declared in version.php")
    args.output_dir.mkdir(parents=True, exist_ok=True)
    output = args.output_dir / f"CD-Live-Exam-Control-{version[1]}.zip"
    files = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode("utf-8").split("\0")
    release_files = [path for path in files if path and not path.startswith((".github/", "tests/", "tools/"))]
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(release_files):
            entry = zipfile.ZipInfo(f"examfocuslive/{path}")
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, (root / path).read_bytes(), compresslevel=9)
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None:
            raise ValueError("The release ZIP failed its integrity check")
    print(f"{output.name} {hashlib.sha256(output.read_bytes()).hexdigest()}")


if __name__ == "__main__":
    main()
