#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
DEST=${1:-"$ROOT/build/netgiro-payment-gateway-for-woocommerce"}

rm -rf "$DEST"
mkdir -p "$DEST"

for path in index.php readme.txt LICENSE includes assets languages; do
	if [ ! -e "$ROOT/$path" ]; then
		echo "Missing production path: $path" >&2
		exit 1
	fi
	cp -a "$ROOT/$path" "$DEST/"
done