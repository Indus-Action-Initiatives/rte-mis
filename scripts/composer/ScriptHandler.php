<?php

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Script Hanlder class.
 */
class ScriptHandler {

  /**
   * Helper function to create the required folders.
   */
  public static function createRequiredFolders(PackageEvent $event) {
    $fs = new Filesystem();
    $dirs = [
      'profiles',
      'profiles/contrib',
      'profiles/contrib/rte-mis',
      'profiles/contrib/rte-mis/patches',
    ];

    // Required for unit testing.
    foreach ($dirs as $dir) {
      if (!$fs->exists('docroot/' . $dir)) {
        $fs->mkdir('docroot/' . $dir);
      }
    }
    // Move patches to desired location so that the patch applies properly.
    $fs->mirror('patches', 'docroot/profiles/contrib/rte-mis/patches');
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
    }
  }

}
