# Hidden Reports

Hide reports from the Matomo reporting menu, for one website or for all websites at once, without losing any data.

## Features

- **Two management pages**:
  - Administration > Websites > Hidden reports: website admins hide reports for the selected website.
  - Administration > System > Hidden reports: super users hide reports for all websites at once.
- **Everything the reporting menu shows can be hidden**: core and plugin reports, Custom Reports, Custom Dimensions, and widgets that are not reports, such as Transitions or the real-time map.
- **Fast to manage**: collapsible categories and subcategories, a search field, a switch per report, and a switch to hide or show a whole category. Changes are saved immediately.
- **No data loss**: a hidden report is only removed from the reporting menu. It is still archived and stays available through the HTTP API, exports, scheduled reports and dashboard widgets. Show it again at any time, its full history is there.
- **Clear precedence**: a report hidden for all websites by a super user is locked on the website pages. A subcategory whose reports are all hidden disappears from the menu.
- **HTTP API**: `HiddenReports.getReports`, `HiddenReports.getGlobalReports`, `HiddenReports.getHiddenReports`, `HiddenReports.setReportHidden`, `HiddenReports.setReportsHidden`, `HiddenReports.setReportHiddenGlobally`, `HiddenReports.setReportsHiddenGlobally`.
- Translated into 12 languages.

## Requirements

- Matomo 5.0.0 or later, up to Matomo 6 excluded (`>=5.0.0,<6.0.0-b1`)
- On Matomo 6, install Hidden Reports 6.x instead.

## Installation / Configuration

1. Install Hidden Reports from the Marketplace (Administration > Platform > Marketplace) and activate it.
2. Go to Administration > Websites > Hidden reports (admin access on the website) or Administration > System > Hidden reports (super user).
3. Switch reports to "Hidden".

Hidden reports are hidden for every user of the website, super users included. Only the Hidden reports pages keep listing them.

## Privacy and data

The list of hidden reports is stored in the Matomo option table. It is cleaned when a website is deleted and when the plugin is uninstalled. The plugin sends no data to third parties.

## Need help with Matomo?

Openmost is an official Matomo Implementation Partner. To go further than a cleaner menu, we build [custom Matomo dashboards and KPI reports](https://openmost.com/matomo/services/dashboard-build?utm_source=matomo_marketplace&utm_medium=referral&utm_campaign=services&utm_content=hiddenreports) tailored to each audience, with KPIs defined with you and maintained over time.

## Support

- Email: ronan@openmost.com
- Homepage: https://openmost.com/matomo/extensions/hidden-reports
- Issues: https://github.com/openmost/HiddenReports/issues

## Screenshots

Screenshots of the website and System management pages are available in the `screenshots` folder and on the Marketplace.

## License

GPL v3 or later
