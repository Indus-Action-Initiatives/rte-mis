CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation


INTRODUCTION
------------

This undertaking aims to construct an online Management Information System (MIS) in a web application format. This system will oversee the comprehensive implementation of the social welfare policy specified in Section 12(1)(c) of the Right of Children to Free and Compulsory Education Act, 2009, by any state government in India. The objective is to shape this product as a digital public asset, ensuring its open-source nature to promote widespread adoption and foster community-led development and sustained maintenance in the future.

 * Links:
    - Live site: ``
    - Dev site: ``


INSTALLATION
------------

Before we begin with the site setup, Make sure that the following tools are
installed in your system.

 1. Lando ( https://docs.lando.dev/getting-started/installation.html )

The steps to setup the Local Instance of the site:

 1. git clone `git@github.com:innoraft/scooter_vitamins.git` or use
    `https://github.com/innoraft/scooter_vitamins.git` (if ssh is not setup in your system).
 2. `cd scooter_vitamins`
 3. `lando start`
 4. `lando composer install`
 5. `lando drush si scooter_vitamins --site-name="Scooter Vitamins" --db-url=mysql://drupal10:drupal10@database/drupal10 --account-pass=admin -y`

NOTE: If you have to re-install the site after doing some changes in the profile then make sure that you remove the `settings.php` file before executing the site-install command mentioned above in `Step 5`.
