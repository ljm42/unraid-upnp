#!/bin/bash
DIR="$(dirname "$(readlink -f ${BASH_SOURCE[0]})")"
tmpdir=/tmp/tmp.$(( $RANDOM * 19318203981230 + 40 ))
plugin=$(basename ${DIR})
archive="$(dirname $(dirname ${DIR}))/archive"
plgfile="$(dirname $(dirname ${DIR}))/plugins/${plugin}.plg"
version=$(date +"%Y.%m.%d")$1

mkdir -p $tmpdir
cp --parents -f $(find . -type f ! \( -iname "pkg_build.sh" -o -iname "sftp-config.json"  \) ) $tmpdir/
cd $tmpdir
makepkg -l y -c y ${archive}/${plugin}-${version}-x86_64-1.txz
rm -rf $tmpdir

md5=`md5sum ${archive}/${plugin}-${version}-x86_64-1.txz | cut -f 1 -d ' '`
echo "MD5: ${md5}"

# update plg file
sed -i "s#ENTITY version   \".*\"#ENTITY version   \"${version}\"#g" ${plgfile}
sed -i "s#ENTITY md5       \".*\"#ENTITY md5       \"${md5}\"#g" ${plgfile}
if [ -z "$1" ]
then
  # add changelog for major versions
  sed -i "/<CHANGES>/a ###${version}\n" ${plgfile}
fi
