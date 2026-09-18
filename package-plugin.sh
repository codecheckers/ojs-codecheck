#!/bin/sh

set -e

FORMAT=""
VERSION=""

# ---------------------------
# Parse arguments
# ---------------------------
while [ $# -gt 0 ]; do
  case "$1" in
    --format)
      FORMAT="$2"
      shift 2
      ;;
    --version)
      VERSION="$2"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1"
      exit 1
      ;;
  esac
done

# ---------------------------
# Validate arguments
# ---------------------------
if [ -z "$FORMAT" ] || [ -z "$VERSION" ]; then
  echo "[Error] Please specify the file format and release version like the following:\n\nsh $0 --format zip|tar.gz --version x.y.z.0"
  exit 1
fi

case "$FORMAT" in
  zip|tar.gz) ;;
  *)
    echo "Invalid format: $FORMAT"
    echo "Allowed values: zip, tar.gz"
    exit 1
    ;;
esac

TAG="v$VERSION"

# OJS extracts the archive and looks for a single directory holding version.xml;
# a flat archive is refused with manager.plugins.invalidPluginArchive. The name
# has to match <application> in version.xml, which is where the plugin is
# installed. See lib/pkp/classes/plugins/PluginHelper.php.
DIR="codecheck"
NAME="$DIR-$VERSION"

# ---------------------------
# Check what cannot be derived from the tag
# ---------------------------
# public/build is generated and gitignored, so it is taken from the working tree
# and there is nothing tying it to $TAG. Build it from the same revision.
if [ ! -f public/build/build.iife.js ] || [ ! -f public/build/build.css ]; then
  echo "[Error] public/build is missing. Run 'npm run build' first."
  exit 1
fi

# ---------------------------
# Stage the package
# ---------------------------
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$DIR"

echo "Packaging ojs-codecheck plugin $TAG as $FORMAT..."
echo "------"

# Tracked files at the tag, minus everything .gitattributes marks export-ignore.
git archive --format=tar "$TAG" | tar -x -C "$STAGE/$DIR"

# The bundle the backend UI loads (CodecheckPlugin::addAssets).
mkdir -p "$STAGE/$DIR/public"
cp -R public/build "$STAGE/$DIR/public/"

# Runtime dependencies. Three classes require vendor/autoload.php at file scope,
# so a package without vendor/ fatals on the first request that touches the API.
# --prefer-dist matters: installing from source leaves a .git directory in every
# package, which was 27 of them and 39M of vendor/ for 4M of actual code.
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --working-dir="$STAGE/$DIR"

# Dependencies ship their own tests, docs and examples. The plugin guide asks for
# them to be left out: they are dead weight, and demos have been a source of
# vulnerabilities in installed plugin directories.
find "$STAGE/$DIR/vendor" -type d \
  \( -name test -o -name tests -o -name Tests -o -name doc -o -name docs -o -name examples -o -name .git \) \
  -prune -exec rm -rf {} + 2>/dev/null || true

# ---------------------------
# Archive
# ---------------------------
rm -f "$NAME.zip" "$NAME.tar.gz"

case "$FORMAT" in
  zip)
    ( cd "$STAGE" && zip -qr - "$DIR" ) > "$NAME.zip"
    ARCHIVE="$NAME.zip"
    ;;
  tar.gz)
    tar -czf "$NAME.tar.gz" -C "$STAGE" "$DIR"
    ARCHIVE="$NAME.tar.gz"
    ;;
esac

echo "------"
echo "Successfully created: $ARCHIVE"

# The Plugin Gallery entry records the md5 of the published package, and the
# package can never be changed afterwards without breaking it.
if command -v md5sum >/dev/null 2>&1; then
  echo "md5: $(md5sum "$ARCHIVE" | cut -d' ' -f1)"
elif command -v md5 >/dev/null 2>&1; then
  echo "md5: $(md5 -q "$ARCHIVE")"
fi
