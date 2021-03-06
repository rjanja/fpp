<?

function SetTimeZone($timezone)
{
    if (file_exists("/.dockerenv")) {
        exec("sudo ln -s -f /usr/share/zoneinfo/$timezone /etc/localtime");
        exec("sudo bash -c \"echo $timezone > /etc/timezone\"");
        exec("sudo dpkg-reconfigure -f noninteractive tzdata");
    } else if (file_exists('/usr/bin/timedatectl')) {
        exec("sudo timedatectl set-timezone $timezone");
    } else {
        exec("sudo bash -c \"echo $timezone > /etc/timezone\"");
        exec("sudo dpkg-reconfigure -f noninteractive tzdata");
    }
}

function SetHWClock()
{
    global $settings;

    $rtcDevice = "/dev/rtc0";
    if ($settings['Platform'] == "BeagleBone Black") {
        if (file_exists("/sys/class/rtc/rtc0/name")) {
            $rtcname = file_get_contents("/sys/class/rtc/rtc0/name");
            if (strpos($rtcname, "omap_rtc") !== false) {
                $rtcDevice = "/dev/rtc1";
            }
        }
    }
    exec("sudo hwclock -w -f $rtcDevice");
}

function SetDate($date)
{
    // Need to pass in the current time or it gets reset to 00:00:00
    exec("sudo date +\"%Y-%m-%d %H:%M:%S\" -s \"$date \$(date +%H:%M:%S)\"");

    SetHWClock();
}

function SetTime($time)
{
    exec("sudo date +%k:%M:%S -s \"$time\"");

    SetHWClock();
}

function SetRTC($rtc)
{
    global $fppDir;

    exec("sudo $fppDir/scripts/piRTC set");
}

function RestartNTPD()
{
    exec("sudo service ntp restart");
}

function SetNTP($value)
{
    if ($value == "1"){
        exec("sudo systemctl enable ntp");
        exec("sudo systemctl start ntp");
    } else if ($value == "0"){
        exec("sudo systemctl stop ntp");
        exec("sudo systemctl disable ntp");
    }
}

function SetNTPServer($value)
{
    $ntp = ReadSettingFromFile('ntp');

    if ($value != '') {
        exec("sudo sed -i '/^server.*/d' /etc/ntp.conf ; sudo sed -i '\$s/\$/\\nserver $value iburst/' /etc/ntp.conf");
    } else {
        exec("sudo sed -i '/^server.*/d' /etc/ntp.conf ; sudo sed -i '\$s/\$/\\nserver 0.debian.pool.ntp.org iburst\\nserver 1.debian.pool.ntp.org iburst\\nserver 2.debian.pool.ntp.org iburst\\nserver 3.debian.pool.ntp.org iburst\\n/' /etc/ntp.conf");
    }

    if ($ntp == "1")
        RestartNTPD();
}

function SetupHtaccess($enablePW)
{
    global $settings;
    $filename = $settings['mediaDirectory'] . "/config/.htaccess";

    if (file_exists($filename))
        unlink($filename);

    $data = $settings['htaccessContents'];
    if ($enablePW) {
        $data .= "AuthUserFile " . $settings['mediaDirectory'] . "/config/.htpasswd\nAuthType Basic\nAuthName \"Falcon Player\"\nRequire local\nRequire valid-user\n";
    }

    file_put_contents($filename, $data);
}

function EnableUIPassword($value)
{
    global $settings;

    if ($value == '0') {
        SetupHtaccess(0);
    } else if ($value == '1') {
        $password = ReadSettingFromFile('password');

        SetUIPassword($password);
        SetupHtaccess(1);
    }
}

function SetUIPassword($value)
{
    global $settings;

    if ($value == '')
        $value = 'falcon';

    // Write a new password file, replacing odl one if exists. 
    // users fpp and admin
    // BCRYPT requires apache 2.4+
    $encrypted_password = password_hash($value, PASSWORD_BCRYPT);
    $data = "admin:$encrypted_password\nfpp:$encrypted_password\n";
    $filename =  $settings['mediaDirectory'] . "/config/.htpasswd";

    // Old file may have been ownedby root so file_put_contents will fail.
    if (file_exists($filename)) {
	    unlink($filename);
    }

    file_put_contents($filename, $data);
}

function SetForceHDMI($value)
{
    
    if (strpos(file_get_contents("/boot/config.txt"), "hdmi_force_hotplug:1") == false) {
        exec("sudo sed -i -e 's/hdmi_force_hotplug=\(.*\)$/hdmi_force_hotplug=\\1\\nhdmi_force_hotplug:1=0/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^hdmi_force_hotplug:1/#hdmi_force_hotplug:1/' /boot/config.txt", $output, $return_val);
    }
    if ($value == '1') {
        exec("sudo sed -i -e 's/#hdmi_force_hotplug/hdmi_force_hotplug/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/#hdmi_force_hotplug/hdmi_force_hotplug/' /boot/config.txt", $output, $return_val);
    } else {
        exec("sudo sed -i -e 's/^hdmi_force_hotplug/#hdmi_force_hotplug/' /boot/config.txt", $output, $return_val);
    }
}
function SetForceHDMIResolution($value, $postfix)
{
    $parts = explode(":", $value);

    if (strpos(file_get_contents("/boot/config.txt"), "hdmi_group:1") == false) {
        exec("sudo sed -i -e 's/hdmi_group=\(.*\)$/hdmi_group=\\1\\nhdmi_group:1=0/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/hdmi_mode=\(.*\)$/hdmi_mode=\\1\\nhdmi_mode:1=0/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^hdmi_group:1/#hdmi_group:1/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^hdmi_mode:1/#hdmi_mode:1/' /boot/config.txt", $output, $return_val);
    }
    
    if ($parts[0] == '0') {
        exec("sudo sed -i -e 's/^hdmi_group".$postfix."=/#hdmi_group".$postfix."=/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^hdmi_mode".$postfix."=/#hdmi_mode".$postfix."=/' /boot/config.txt", $output, $return_val);
    } else {
        exec("sudo sed -i -e 's/^#hdmi_group".$postfix."=/hdmi_group".$postfix."=/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^#hdmi_mode".$postfix."=/hdmi_mode".$postfix."=/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^hdmi_group".$postfix."=.*/hdmi_group".$postfix."=".$parts[0]."/' /boot/config.txt", $output, $return_val);
        exec("sudo sed -i -e 's/^hdmi_mode".$postfix."=.*/hdmi_mode".$postfix."=".$parts[1]."/' /boot/config.txt", $output, $return_val);
    }
}

function SetWifiDrivers($value) {
    if ($value == "Kernel") {
        exec("sudo rm -f /etc/modprobe.d/blacklist-native-wifi.conf", $output, $return_val );
        exec("sudo rm -f /etc/modprobe.d/rtl8723bu-blacklist.conf", $output, $return_val );
    } else {
        exec("sudo cp /opt/fpp/etc/blacklist-native-wifi.conf /etc/modprobe.d", $output, $return_val );
        exec("sudo rm -f /etc/modprobe.d/blacklist-8192cu.conf", $output, $return_val );
    }
}

/////////////////////////////////////////////////////////////////////////////
function ApplySetting($setting, $value) {
    switch ($setting) {
        case 'ClockDate':       SetDate($value);              break;
        case 'ClockTime':       SetTime($value);              break;
        case 'ntp':             SetNTP($value);               break;
        case 'ntpServer':       SetNTPServer($value);         break;
        case 'passwordEnable':  EnableUIPassword($value);     break;
        case 'password':        SetUIPassword($value);        break;
        case 'piRTC':           SetRTC($value);               break;
        case 'TimeZone':        SetTimeZone($value);          break;
        case 'ForceHDMI':       SetForceHDMI($value);         break;
        case 'ForceHDMIResolution':  SetForceHDMIResolution($value, "");         break;
        case 'ForceHDMIResolutionPort2':  SetForceHDMIResolution($value, ":1");         break;
        case 'wifiDrivers':     SetWifiDrivers($value);       break;
    }
}

?>
