<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HiddenReports;

use Piwik\Piwik;
use Piwik\Plugins\UsersManager\UserPreferences;
use Piwik\Request;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function manage()
    {
        $idSite = Request::fromRequest()->getIntegerParameter('idSite', 0);
        Piwik::checkUserHasAdminAccess($idSite);

        return $this->renderTemplate('manage', [
            'scope'  => 'site',
            'idSite' => $idSite,
            'title'  => Piwik::translate('HiddenReports_SiteTitle'),
        ]);
    }

    public function manageGlobal()
    {
        Piwik::checkUserHasSuperUserAccess();

        $default = (new UserPreferences())->getDefaultWebsiteId();
        $idSite  = Request::fromRequest()->getIntegerParameter('idSite', (int) $default);
        Piwik::checkUserHasViewAccess($idSite);

        return $this->renderTemplate('manage', [
            'scope'  => 'global',
            'idSite' => $idSite,
            'title'  => Piwik::translate('HiddenReports_GlobalTitle'),
        ]);
    }
}
