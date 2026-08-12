#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

"$ROOT/.github/scripts/build-plugin.sh" "$WORK/netgiro-payment-gateway-for-woocommerce"
chmod -R ugo+rwX "$WORK/netgiro-payment-gateway-for-woocommerce/languages"

docker run --rm \
	-v "$WORK/netgiro-payment-gateway-for-woocommerce:/plugin" \
	wordpress:cli \
	wp i18n make-pot /plugin /plugin/languages/netgiro-payment-gateway-for-woocommerce.pot \
		--slug=netgiro-payment-gateway-for-woocommerce \
		--ignore-domain

python3 - "$WORK/netgiro-payment-gateway-for-woocommerce/languages/netgiro-payment-gateway-for-woocommerce.pot" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
text = path.read_text()
text = re.sub(
    r'"POT-Creation-Date: .*?\\n"',
    '"POT-Creation-Date: YEAR-MO-DA HO:MI+ZONE\\\\n"',
    text,
)
path.write_text(text)
PY

cp "$WORK/netgiro-payment-gateway-for-woocommerce/languages/netgiro-payment-gateway-for-woocommerce.pot" \
	"$ROOT/languages/netgiro-payment-gateway-for-woocommerce.pot"
