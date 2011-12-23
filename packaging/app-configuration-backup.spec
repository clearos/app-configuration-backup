
Name: app-configuration-backup
Version: 6.2.0.beta3
Release: 1%{dist}
Summary: Configuration Backup
License: GPLv3
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = %{version}-%{release}
Requires: app-base

%description
The configuration backup and restore app allows an administrator to take a snapshot (archive file) of all configuration settings of the system, allowing easy restoration in the event data is lost.

%package core
Summary: Configuration Backup - APIs and install
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-network-core

%description core
The configuration backup and restore app allows an administrator to take a snapshot (archive file) of all configuration settings of the system, allowing easy restoration in the event data is lost.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/configuration_backup
cp -r * %{buildroot}/usr/clearos/apps/configuration_backup/

install -d -m 0755 %{buildroot}/var/clearos/configuration_backup
install -d -m 775 %{buildroot}/var/clearos/configuration_backup/upload

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
%dir /var/clearos/configuration_backup
%dir %attr(775,root,webconfig) /var/clearos/configuration_backup/upload
/usr/clearos/apps/configuration_backup/deploy
/usr/clearos/apps/configuration_backup/language
/usr/clearos/apps/configuration_backup/libraries
