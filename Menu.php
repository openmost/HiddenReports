<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Piwik\Common;
use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\UserPreferences;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        $userPreferences = new UserPreferences();
        $default         = $userPreferences->getDefaultWebsiteId();
        $idSite          = Common::getRequestVar('idSite', $default, 'int');

        if (!empty($idSite) && Piwik::isUserHasAdminAccess($idSite)) {
            $menu->addMeasurableItem('HideReports_MenuSite', $this->urlForAction('manage'), $order = 42);
        }

        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addSystemItem('HideReports_MenuGlobal', $this->urlForAction('manageGlobal'), $order = 42);
        }
    }
}
