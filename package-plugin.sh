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

# The package is installed, and compared with the Plugin Gallery entry, by the
# version.xml inside it, so the tag must say the version being packaged. A tag
# made before version.xml was moved (v1.0.0.0 says 0.0.0.0) is refused here.
TAG_VERSION_XML=$(git show "$TAG:version.xml") || {
  echo "[Error] No version.xml at $TAG. Create and push the tag first."
  exit 1
}
TAG_RELEASE=$(printf '%s\n' "$TAG_VERSION_XML" | sed -n 's:.*<release>\(.*\)</release>.*:\1:p')
TAG_DATE=$(printf '%s\n' "$TAG_VERSION_XML" | sed -n 's:.*<date>\(.*\)</date>.*:\1:p')
if [ "$TAG_RELEASE" != "$VERSION" ]; then
  echo "[Error] version.xml at $TAG says release $TAG_RELEASE, not $VERSION."
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
MD5=""
if command -v md5sum >/dev/null 2>&1; then
  MD5=$(md5sum "$ARCHIVE" | cut -d' ' -f1)
elif command -v md5 >/dev/null 2>&1; then
  MD5=$(md5 -q "$ARCHIVE")
fi

if [ -n "$MD5" ]; then
  echo "md5: $MD5"
elif [ "$FORMAT" = "tar.gz" ]; then
  echo "[Error] Neither md5sum nor md5 is available, so the Plugin Gallery entry cannot be printed."
  exit 1
fi

# The entry to append to plugins.xml, so that none of its values is copied by
# hand into an entry that can never be edited once published. Version and date
# are version.xml's at the tag, so the listing agrees with the package. Only the
# description is left to write. The gallery lists tar.gz packages alone.
if [ "$FORMAT" = "tar.gz" ]; then
  echo "------"
  echo "Append to plugins.xml once $ARCHIVE is uploaded to the $TAG release:"
  echo
  cat <<RELEASE
		<release date="$TAG_DATE" version="$VERSION" md5="$MD5">
			<package>https://github.com/codecheckers/ojs-codecheck/releases/download/$TAG/$ARCHIVE</package>
			<compatibility application="ojs2">
				<version>~3.5.0.0</version>
			</compatibility>
			<description locale="en">DESCRIBE THIS RELEASE IN ONE LINE</description>
		</release>
RELEASE
fi
