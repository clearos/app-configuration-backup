
Name: app-configuration-backup
Epoch: 1
Version: 1.6.5
Release: 1%{dist}
Summary: Configuration Backup
License: GPLv3
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-registration

%description
The configuration backup and restore app allows an administrator to take a snapshot of all configuration settings of the system, allowing easy restoration in the event of data loss.

%package core
Summary: Configuration Backup - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-base-core >= 1:1.5.32
Requires: app-network-core
Requires: app-tasks-core

%description core
The configuration backup and restore app allows an administrator to take a snapshot of all configuration settings of the system, allowing easy restoration in the event of data loss.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/configuration_backup
cp -r * %{buildroot}/usr/clearos/apps/configuration_backup/

install -d -m 0755 %{buildroot}/var/clearos/configuration_backup
install -d -m 775 %{buildroot}/var/clearos/configuration_backup/upload
install -D -m 0644 packaging/app-configuration-backup.cron %{buildroot}/etc/cron.d/app-configuration-backup
install -D -m 0755 packaging/configuration-restore %{buildroot}/usr/sbin/configuration-restore

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
%dir /usr/clearos/apps/configuration_backup
%dir /var/clearos/configuration_backup
%dir %attr(775,root,webconfig) /var/clearos/configuration_backup/upload
/usr/clearos/apps/configuration_backup/deploy
/usr/clearos/apps/configuration_backup/language
/usr/clearos/apps/configuration_backup/libraries
/etc/cron.d/app-configuration-backup
/usr/sbin/configuration-restore
