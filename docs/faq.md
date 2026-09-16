## FAQ

__How to install this plugin__

This plugin is available in the official marketplace of Matomo. You have to install the same way as other plugins

- Go to the administration panel
- Look for the Marketplace section and select "Plugins" in the dropdown
- Then search for "**Hidden Reports**", install and activate the plugin.
- Go to __Administration > Websites > Hidden reports__ or __Administration > System > Hidden reports__.

__Who can hide reports ?__

Users with an admin access on a website hide reports for this website. Super users can also hide reports for all websites at once.

__Who no longer sees a hidden report ?__

Everybody, super users included. Only the Hidden reports pages keep listing it.

__Is the data of a hidden report deleted ?__

No. The report is still archived, and remains available through the HTTP API, exports, scheduled reports and dashboard widgets. Show it again at any time to get it back in the menu with its full history.

__Why can't I show a report on the website page ?__

It was hidden for all websites by a super user. Only a super user can show it again, from __Administration > System > Hidden reports__.

__Why are goals, custom dimensions and custom reports missing from the System page ?__

These reports belong to a single website. Hide them from the __Hidden reports__ page of the website.
