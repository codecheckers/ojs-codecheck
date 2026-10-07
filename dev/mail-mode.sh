#!/bin/sh
# Switch an OJS config.inc.php into or out of mail mode, for the specs under
# cypress/tests/mail/ (see CLAUDE.md, "Mail tests"). Used by `make mail-up`,
# `make mail-down` and CI.
#
#   dev/mail-mode.sh on  <config.inc.php> [smtp_port]   send to Mailpit on 127.0.0.1
#   dev/mail-mode.sh off <config.inc.php>               back to sandbox mode
#
# sed -i replaces the file rather than writing into it, as a throwaway's
# hard-linked tree requires.
set -eu

mode=$1
config=$2
port=${3:-1025}

# Fails unless every line was set: a line the config spells differently is
# left as it was by sed.
expect() {
	for line in "$@"; do
		grep -q "$line" "$config" || { echo "$config: no line matching $line after switching $mode" >&2; exit 1; }
	done
}

case "$mode" in
on)
	sed -i \
		-e 's|^sandbox = .*|sandbox = Off|' \
		-e 's|^job_runner = .*|job_runner = Off|' \
		-e 's|^task_runner = .*|task_runner = Off|' \
		-e "/^\[email\]/,/^\[/{s|^default = .*|default = smtp|;s|^;\? *smtp_server = .*|smtp_server = 127.0.0.1|;s|^;\? *smtp_port = .*|smtp_port = $port|}" \
		"$config"
	expect '^sandbox = Off$' '^job_runner = Off$' '^task_runner = Off$' '^default = smtp$' \
		'^smtp_server = 127.0.0.1$' "^smtp_port = $port\$"
	;;
off)
	sed -i \
		-e 's|^sandbox = .*|sandbox = On|' \
		-e 's|^job_runner = .*|job_runner = On|' \
		-e 's|^task_runner = .*|task_runner = On|' \
		-e '/^\[email\]/,/^\[/{s|^default = .*|default = sendmail|;s|^smtp_server = |; smtp_server = |;s|^smtp_port = |; smtp_port = |}' \
		"$config"
	expect '^sandbox = On$' '^job_runner = On$' '^task_runner = On$' '^default = sendmail$'
	;;
*)
	echo "usage: $0 on|off <config.inc.php> [smtp_port]" >&2
	exit 2
	;;
esac
