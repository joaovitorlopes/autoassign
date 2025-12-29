# Autoassign GLPI plugin

This plugin automatically assigns technicians to a ticket in a way that doesn't overload those who already have multiple tickets on their list, based on the group automatically assigned through rules.

## Configuration page

Each features are configurable from the main config page.

![config](screenshots/config.png)

# How to install the plugin:

## On the GLPI server, access the /tmp folder:
cd /tmp

## Download the plugin:
wget https://github.com/joaovitorlopes/autoassign/releases/download/1.0.0/autoassign.tar.bz2

## Install bzip2 to extract the contents:
apt install bzip2 -y

## Unpack the autoassign.tar.bz2 file:
tar -jxvf autoassign.tar.bz2

## Move the plugin to the GLPI plugins folder:
mv autoassign /var/www/.../glpi/plugins/

## Now grant permission to the plugin folder:
chown -R www-data:www-data /var/www/.../glpi/plugins/autoassign/
chmod -R 755 /var/www/.../glpi/plugins/autoassign/

## Install and activate the plugin from the glpi plugins menu:
![plugininstall](screenshots/plugin-install.png)
