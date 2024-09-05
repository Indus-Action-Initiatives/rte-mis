#!/bin/bash

# Function to display usage instructions
usage() {
    echo "Usage: $0 [environment] <drupal-root> <site-uri> <item-limit>"
    echo "Environment should be 'lando' or 'server'. If not provided, defaults to 'server'."
    exit 1
}

# Check if the correct number of arguments is provided and specify which parameter is missing
if [ -z "$1" ]; then
    echo "Error: Missing drupal-root parameter."
    usage
elif [ -z "$2" ]; then
    echo "Error: Missing site-uri parameter."
    usage
elif [ -z "$3" ]; then
    echo "Error: Missing item-limit parameter."
    usage
fi

# Determine if the environment parameter is provided
if [ "$#" -eq 3 ]; then
    ENVIRONMENT="server"
    ROOT="$1"
    URI="$2"
    ITEM_LIMIT="$3"
else
    ENVIRONMENT="$1"
    ROOT="$2"
    URI="$3"
    ITEM_LIMIT="$4"
fi

# Set Drush path based on environment
if [ "$ENVIRONMENT" = "lando" ]; then
    DRUSH_PATH="/app/vendor/bin/drush"
elif [ "$ENVIRONMENT" = "server" ]; then
    # Change the directory and set the drush path to vendor/bin/drush
    cd $ROOT
    DRUSH_PATH="../vendor/bin/drush"
else
    echo "Error: Invalid environment parameter. Should be 'lando' or 'server'."
    usage
fi

# Check if Drush is installed at the specified path
if [ ! -x "$DRUSH_PATH" ]; then
    echo "Drush is not installed at the specified path ($DRUSH_PATH). Please check the path and try again."
    exit 1
fi

# Run the Drush command
$DRUSH_PATH --root="$ROOT" --uri="$URI" queue:run student_tracking_queue --time-limit="$ITEM_LIMIT"
