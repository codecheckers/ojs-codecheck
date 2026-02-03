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
  echo "[Error] Please scpecify the file format and release version like the following:\n\nsh $0 --format zip|tar.gz --version x.y.z.0"
  exit 1
fi

TAG="v$VERSION"
NAME="codecheck-$VERSION"

# ---------------------------
# Package
# ---------------------------
case "$FORMAT" in
  zip)
    echo "Packaging ojs-codecheck plugin $TAG as ZIP..."
    echo "------"
    git archive --format=zip --output="$NAME.zip" "$TAG"
    zip -r "$NAME.zip" public/
    zip -d "$NAME.zip" 'resources/*'
    echo "------"
    echo "Successfully created: $NAME.zip"
    ;;

  tar.gz)
    echo "Packaging ojs-codecheck plugin $TAG as TAR.GZ..."
    echo "------"
    git archive --format=tar "$TAG" > "$NAME.tar"
    tar -rf "$NAME.tar" public/
    tar --delete -f "$NAME.tar" resources
    gzip "$NAME.tar"
    echo "Successfully created: $NAME.tar.gz"
    ;;

  *)
    echo "Invalid format: $FORMAT"
    echo "Allowed values: zip, tar.gz"
    exit 1
    ;;
esac