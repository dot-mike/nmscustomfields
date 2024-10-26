#!/bin/bash

LIBRENMS_VERSION=${LIBRENMS_VERSION:-23.8.1}

# env LIBRENMS_FOLDER is the folder where librenms is installed
LIBRENMS_FOLDER_BASE=$(dirname $(realpath $LIBRENMS_FOLDER))

cd $LIBRENMS_FOLDER_BASE || exit 1

echo "Cloning librenms version $LIBRENMS_VERSION into $LIBRENMS_FOLDER"

git clone https://github.com/librenms/librenms.git --branch $LIBRENMS_VERSION --depth 1 --single-branch librenms

cd librenms || exit 1

php ./scripts/composer_wrapper.php install --no-dev --prefer-dist --no-interaction --no-progress --no-suggest

cp config.php.default config.php

echo "DB_HOST=127.0.0.1" | tee -a .env
echo "DB_DATABASE=$DB_NAME" | tee -a .env
echo "DB_USER=$DB_USER" | tee -a .env
echo "DB_PASSWORD=$DB_PASSWORD" | tee -a .env
echo "LIBRENMS_USER=vscode" | tee -a .env
echo "APP_URL=/" | tee -a .env
# I could not get output to stderr to work
# for outputing in the terminal when running "artisan sevrve"
echo "LOG_CHANNEL=stack" | tee -a .env
echo "LOG_LEVEL=debug" | tee -a .env
echo "APP_DEBUG=true" | tee -a .env
sed -i '/INSTALL=true/d' .env

echo \$config[\'db_host\'] = \'127.0.0.1\'\; | tee -a config.php
echo \$config[\'db_user\'] = \'$DB_USER\'\; | tee -a config.php
echo \$config[\'db_pass\'] = \'$DB_PASSWORD\'\; | tee -a config.php
echo \$config[\'db_name\'] = \'$DB_NAME\'\; | tee -a config.php

echo "Setting up librenms"

# seed the database
php artisan db:seed --force

# load .env as environment variables
# .env does not work for some reason
export $(grep -v '^#' .env | xargs)
php lnms --force -n migrate || true
php lnms -n user:add -p librenms -r admin -n librenms >/dev/null || true # add user if not exists
php lnms -n user:add -p admin -r admin -n admin >/dev/null || true # add user if not exists

echo "Adding snmpsim device"
php lnms -n device:add -r 1161 -2 -c demo -- snmpsim >/dev/null || true # add device if not exists
php lnms -n device:add -r 1161 -2 -c testing -- snmpsim2 >/dev/null || true # add device if not exists

composer config repositories.local '{"type": "path", "url": "'"${LOCAL_WORKSPACE_FOLDER}"'", "options": {"symlink": true}}' --file composer.json

if [ -f "${LOCAL_WORKSPACE_FOLDER}/composer.json" ]; then
    PACKAGE_NAME=$(jq -r '.name' "${LOCAL_WORKSPACE_FOLDER}/composer.json")
    if [ -n "$PACKAGE_NAME" ]; then
        echo "Adding $PACKAGE_NAME to librenms"
        FORCE=1 composer require "$PACKAGE_NAME"
    fi
fi

php artisan route:clear

echo -e "Environment setup done. Librenms installed in ${LIBRENMS_FOLDER}.\nHappy coding!"

php artisan vendor:publish --provider="DotMike\Devicefields\Providers\DeviceFieldsProvider" --force
php lnms --force -n migrate || true

#php artisan cache:clear
#php artisan route:clear
#php artisan view:clear
