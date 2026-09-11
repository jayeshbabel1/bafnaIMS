import sys

def apply_patches(patches, allow_missing=False):
    """patches: list of (file, find, replace) tuples. Exits non-zero on any
    not-found or multi-match to avoid silently corrupting a file."""
    errors = []
    applied = []
    for path, find, replace in patches:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        count = content.count(find)
        if count == 0:
            errors.append(f"NOT FOUND in {path}:\n---\n{find[:200]}\n---")
            continue
        if count > 1:
            errors.append(f"{count} MATCHES (expected 1) in {path}:\n---\n{find[:200]}\n---")
            continue
        new_content = content.replace(find, replace, 1)
        with open(path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        applied.append(path)
        print(f"OK  {path}")
    if errors:
        print("\n=== PATCH ERRORS — nothing further should be committed until fixed ===")
        for e in errors:
            print(e)
            print()
        sys.exit(1)
    print(f"\nAll {len(applied)} patches applied cleanly.")
