#! /usr/bin/bash

# Copies all files and folders necessary for an app release into a separate
# folder in ./releases/x.y.z (x.y.z given as first and only parameter) leaving
# out development-only folders such as .git.

if [ -z "$1" ]
then
    echo "First parameter must not be empty and must be the version of the release which is used as a folder name, e.g. '1.5.1'."
else
    TARGET_DIRECTORY="./releases/${1}/user_backend_sql_raw"
    mkdir --parents $TARGET_DIRECTORY
    cp --archive appinfo img lib CHANGELOG.md LICENSE README.md $TARGET_DIRECTORY
fi
