# Hide Reports

Hide reports from the Matomo reporting menu, for one website or for all websites at once, without losing any data.

## Description

Some reports are not relevant for every team or every website. With Hide Reports, you choose which reports appear in the reporting menu, from two dedicated pages:

- __Administration > Websites > Hidden reports__: website admins hide reports for the selected website.
- __Administration > System > Hidden reports__: super users hide reports for all websites at once.

Everything the reporting menu shows is listed, grouped in collapsible categories and subcategories, with a switch to hide or show each item, or a whole category at once:

- Core and plugin reports, including Custom Reports and Custom Dimensions
- Widgets that are not reports, such as Transitions, the real-time map or Media real-time

Hiding a report only removes it from the reporting menu:

- The report is still archived, so no data is lost
- The report stays available through the HTTP API, exports and scheduled reports
- Dashboard widgets of this report keep working, and the report can still be added to a dashboard
- Show the report again at any time, its full history is immediately available

A report hidden for all websites by a super user is locked on the website pages.

HTTP API: `HideReports.getReports`, `HideReports.getGlobalReports`, `HideReports.getHiddenReports`, `HideReports.setReportHidden`, `HideReports.setReportsHidden`, `HideReports.setReportHiddenGlobally`, `HideReports.setReportsHiddenGlobally`.

__Thank you for installing !__

## Want more ?

If you want to have your own plugin or want to develop a plugin for your customers, please contact me using my email in the marketplace official page or go to https://openmost.com
