#!/usr/bin/env bash
# Mutation ciblée d'une commande / d'un service à base de données (hors périmètre
# de infection.json5, réservé aux services purs).
#   tests/Mutation/Commands/generate.sh Console/Commands/AuditCoverage tests/Feature/Commands/AuditCoverageCommandTest.php [autres tests…]
#   VFF_TEST_DB=vff_cov_test bin/php vendor/bin/infection -c tests/Mutation/Commands/infection.json5 --threads=2 --only-covering-test-cases
# Écrit phpunit.xml et infection.json5 à côté de ce script (fichiers générés).
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
target="$1"; shift
files=""
for t in "$@"; do files+="            <file>../../../$t</file>"$'\n'; done
sed -e '/<testsuites>/,/<\/source>/d' "$DIR/../phpunit.xml" \
  | sed -e 's#\.\./\.\./vendor#../../../vendor#g' > "$DIR/phpunit.xml.tmp"
python3 -I - "$DIR" "$target" "$files" <<'PY'
import sys,re
d,target,files=sys.argv[1:]
s=open(d+'/phpunit.xml.tmp').read()
suite=f"""    <testsuites>
        <testsuite name="Cible">
{files}        </testsuite>
    </testsuites>
    <source>
        <include>
            <file>../../../app/{target}.php</file>
        </include>
    </source>
"""
s=s.replace('    <php>',suite+'    <php>',1)
open(d+'/phpunit.xml','w').write(s)
name=target.split('/')[-1]
open(d+'/infection.json5','w').write(f'''{{
    "source": {{"directories": ["../../../app"], "excludes": ["#^(?!{re.escape(target)}\\\\.php$)#"]}},
    "phpUnit": {{"configDir": "."}},
    "initialTestsPhpOptions": "-d pcov.enabled=1",
    "timeout": 60,
    "tmpDir": "../../../storage/app/reports/infection/tmp-cov",
    "logs": {{"text": "../../../storage/app/reports/agents/couverture-tests/inf-{name}.log",
             "json": "../../../storage/app/reports/agents/couverture-tests/inf-{name}.json"}},
    "mutators": {{"@default": true, "ProtectedVisibility": false}}
}}
''')
PY
rm -f "$DIR/phpunit.xml.tmp"
