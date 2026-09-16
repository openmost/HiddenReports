<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\UserPreferences;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function manage()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        Piwik::checkUserHasAdminAccess($idSite);

        return $this->renderTemplate('manage', [
            'scope'  => 'site',
            'idSite' => $idSite,
            'title'  => Piwik::translate('HideReports_SiteTitle'),
        ]);
    }

    public function manageGlobal()
    {
        Piwik::checkUserHasSuperUserAccess();

        $default = (new UserPreferences())->getDefaultWebsiteId();
        $idSite  = Common::getRequestVar('idSite', $default, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        return $this->renderTemplate('manage', [
            'scope'  => 'global',
            'idSite' => $idSite,
            'title'  => Piwik::translate('HideReports_GlobalTitle'),
        ]);
    }
}
