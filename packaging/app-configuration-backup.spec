
Name: app-configuration-backup
Group: ClearOS/Apps
Version: 5.9.9.4
Release: 2%{dist}
Summary: Configuration Backup/Restore
License: GPLv3
Packager: ClearFoundation
Vendor: ClearFoundation
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = %{version}-%{release}
Requires: app-base

%description
Translation missing (configuration_backup_app_long_description)

%package core
Summary: Configuration Backup/Restore - APIs and install
Group: ClearOS/Libraries
License: LGPLv3
Requires: app-base-core

%description core
Translation missing (configuration_backup_app_long_description)

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/configuration_backup
cp -r * %{buildroot}/usr/clearos/apps/configuration_backup/

install -d -m 755 %{buildroot}/var/clearos/configuration_backup
install -d -m 755 %{buildroot}/var/clearos/configuration_backup/upload
install -D -m 0644 packaging/backup.conf %{buildroot}/etc/backup.conf

%post
logger -p local6.notice -t installer 'app-configuration-backup - installing'

%post core
logger -p local6.notice -t installer 'app-configuration-backup-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/configuration_backup/deploy/install ] && /usr/clearos/apps/configuration_backup/deploy/install
fi

[ -x /usr/clearos/apps/configuration_backup/deploy/upgrade ] && /usr/clearos/apps/configuration_backup/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-configuration-backup - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-configuration-backup-core - uninstalling'
    [ -x /usr/clearos/apps/configuration_backup/deploy/uninstall ] && /usr/clearos/apps/configuration_backup/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/configuration_backup/controllers
/usr/clearos/apps/configuration_backup/htdocs
/usr/clearos/apps/configuration_backup/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/configuration_backup/packaging
%exclude /usr/clearos/apps/configuration_backup/tests
%dir /usr/clearos/apps/configuration_backup
%dir %attr(755,webconfig,webconfig) /var/clearos/configuration_backup
%dir %attr(755,webconfig,webconfig) /var/clearos/configuration_backup/upload
/usr/clearos/apps/configuration_backup/deploy
/usr/clearos/apps/configuration_backup/language
/usr/clearos/apps/configuration_backup/libraries
%attr(0644,webconfig,webconfig) %config(noreplace) /etc/backup.conf
