#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

# Box drops dotfiles; php-scoper's composer.json includes classmap vendor-hotfix/.
# That directory is usually empty except for .gitkeep, which can disappear in Box's temp build dir.
# Create a real file so composer dump-autoload doesn't fail during compilation.
KEEP_FILE="vendor/humbug/php-scoper/vendor-hotfix/keep.php"
CLEANUP=0

if [ ! -f "$KEEP_FILE" ]; then
  mkdir -p "$(dirname "$KEEP_FILE")"
  echo "<?php // keep" > "$KEEP_FILE"
  CLEANUP=1
fi

php /home/nckrtl/projects/orbit/packages/cli/box.phar compile

if [ "$CLEANUP" = "1" ]; then
  rm -f "$KEEP_FILE"
fi

echo "Built: $(pwd)/builds/oracle.phar"
