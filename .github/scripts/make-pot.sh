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

cp "$WORK/netgiro-payment-gateway-for-woocommerce/languages/netgiro-payment-gateway-for-woocommerce.pot" \
	"$ROOT/languages/netgiro-payment-gateway-for-woocommerce.pot"
