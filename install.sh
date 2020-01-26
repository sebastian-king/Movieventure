#!/bin/bash

replace_json_config() {
	var=$2
	new_val=$3

	sed -i "/$var/c\	\\\"$var\\\" : \\\"$new_val\\\"," "$1"
}

replace_quoted_config() {
	var=$2
	new_val=$3

	sed -i "s@^$var=\".*\"@$var=\"$new_val\"@g" "$1"
}

replace_spaced_config() {
	var=$2
	new_val=$3

	sed -e "s/^$var *= *.*/$var = $new_val/; s/^$var [^=]*$/$var $new_val/" "$1" | grep $var
}

apt-get install apache2 php mysql-server mysql-client certbot libapache2-mod-php python3-certbot-apache
apt-get install ingest-daemon mediainfo bc jq

# set up apache
# set up letsencrypt
# set up ingest

# INGEST INSTALL START
echo
echo
echo "Updating ingest-daemon config directory"
OLD_INGEST_DIR="/var/lib/ingest-daemon"
NEW_INGEST_DIR="/home/ingest-daemon"

replace_quoted_config /etc/default/ingest-daemon CONFIG_DIR "${NEW_INGEST_DIR}/info"
if [ -d "${OLD_INGEST_DIR}" ]; then
	service ingest-daemon stop
	echo "Moving ingest-daemon home directory"
	mv "${OLD_INGEST_DIR}" "${NEW_INGEST_DIR}"

	# download dir
	# pass
	# username
	# port
	# exec script
	# incomplete dir
	# whitelist?
	jq '."rpc-password" = "test"' "${NEW_INGEST_DIR}/info/settings.json" > /tmp/jq-edit
	cat /tmp/jq-edit > "${NEW_INGEST_DIR}/info/settings.json"
	jq '."rpc-port" = 9091' "${NEW_INGEST_DIR}/info/settings.json" > /tmp/jq-edit
	jq '."rpc-password" = "test"' "${NEW_INGEST_DIR}/info/settings.json" > /tmp/jq-edit
	service ingest-daemon start
fi


# INGEST INSTALL END
