<?php

namespace Drupal\forseti_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access check for authenticated users only.
 */
class AuthenticatedUserAccess implements AccessInterface {

  /**
   * Checks if user is authenticated.
   */
  public function access(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden('User must be authenticated to access this route.');
    }
    return AccessResult::allowed();
  }

}
