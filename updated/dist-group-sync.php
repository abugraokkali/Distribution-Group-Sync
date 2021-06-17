<?php
// Samba domain credentials
define("SAMBA_USERNAME", "administrator");
define("SAMBA_PASSWORD", "123123Aa");
define("SAMBA_SERVER_IP", "192.168.1.69");
define("SAMBA_SERVER_LDAP_PORT", 636);
define("SAMBA_DOMAIN_DN", "DC=bugra,DC=lab");
define("SAMBA_SEARCH_DN", "DC=bugra,DC=lab");

define("SAMBA_DEFAULT_GROUP_DN", "CN=Users,DC=bugra,DC=lab");


// Ad domain credentials
define("AD_USERNAME", "administrator");
define("AD_PASSWORD", "123123Aa");
define("AD_SERVER_IP", "192.168.1.68");
define("AD_SERVER_LDAP_PORT", 636);
define("AD_DOMAIN_DN", "DC=ali,DC=lab");
define("AD_SEARCH_DN", "DC=ali,DC=lab");

//Counters
$created_groups = 0;
$deleted_groups = 0;
$added_members = 0;
$removed_members = 0;
$created_users = 0;

// files
if(!is_dir("./logs/")){
    mkdir("./logs/");
}
define("LOG_FILE", "./logs/".date(DATE_RFC2822));
if(!is_dir(LOG_FILE)){
    mkdir(LOG_FILE);
}

$f_detailed_log = fopen(LOG_FILE."/detailed_log.csv","w");
$f_summary = fopen(LOG_FILE."/summary.csv","w");
$f_sid_diff = fopen(LOG_FILE."/sid_diff.txt","w");

// Samba connection
$samba = new LdapConnection(SAMBA_SERVER_IP, SAMBA_USERNAME, SAMBA_PASSWORD, true, SAMBA_DOMAIN_DN, SAMBA_SERVER_LDAP_PORT);
$ad = new LdapConnection(AD_SERVER_IP, AD_USERNAME, AD_PASSWORD, true, AD_DOMAIN_DN, AD_SERVER_LDAP_PORT);


// Counters
list($count,$samba_users) = $samba->search("(&(objectClass=person))",[
    "stopOn" => "-1",
    "page" => "-1",
    "searchOn" => SAMBA_DOMAIN_DN,
    "attributeList" => [
        "sAMAccountName"
    ]
]);

 // Counters
list($count,$ad_users) = $ad->search("(&(objectClass=person))",[
    "stopOn" => "-1",
    "page" => "-1",
    "searchOn" => AD_DOMAIN_DN,
    "attributeList" => [
        "sAMAccountName"
    ]
]);

$ad_users = array_combine(array_column(array_column($ad_users,"samaccountname"),0),array_column($ad_users,"dn"));
$samba_users = array_combine(array_column(array_column($samba_users,"samaccountname"),0),array_column($samba_users,"dn"));


// Counters
list($count,$samba_groups) = $samba->search("(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))",[
    "stopOn" => "-1",
    "page" => "-1",
    "searchOn" => SAMBA_DOMAIN_DN,
    "attributeList" => [
        "objectSid","sAMAccountName","member","name"
    ]
]);

 // Counters
list($count,$ad_groups) = $ad->search("(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))",[
    "stopOn" => "-1",
    "page" => "-1",
    "searchOn" => AD_DOMAIN_DN,
    "attributeList" => [
        "objectSid","sAMAccountName","member","name"
    ]
]);

unset($samba_groups["count"]);
unset($ad_groups["count"]);

$ad_conversion = (convertGroup($ad_groups,$ad_users));
print_r($ad_conversion);

$samba_conversion = (convertGroup($samba_groups,$samba_users));
print_r($samba_conversion);

print_r($ad_users);
print_r($samba_users);

function sync($samba_conversion){

    $ad_conversion = $GLOBALS['ad_conversion'];
    $samba = $GLOBALS['samba'];
    $f_detailed_log =  $GLOBALS['f_detailed_log'];

    foreach ($ad_conversion[2] as $key => $value){
        
        $groupName = $key;
        if (!array_key_exists($groupName, $samba_conversion[2])){
        //If there is a missing group in the 2nd domain; Create the group and fill its members.

            $dn = 'CN='.$groupName.','.SAMBA_DEFAULT_GROUP_DN;
            $samba->addObject($dn,[
                "objectClass" => "top",
                "objectClass" => "group",
                "groupType" => 2,
                "instanceType" => 4,
                "sAMAccountName" => $groupName
            ]);
            $samba_conversion[0][$groupName] = $dn;
            $samba_conversion[1][$groupName] = null;
            $samba_conversion[2][$groupName] = array();

            fwrite($f_detailed_log , $groupName." grubu başarıyla oluşturuldu.");
            $GLOBALS["created_groups"]++;
        }
        
        $samba_conversion = fill_members($groupName,$samba_conversion);

        
    }
    foreach ($samba_conversion[2] as $key => $value){

        $groupName = $key;
        //If there is an extra group in the 2nd domain; Delete the group
        if (!array_key_exists($groupName, $ad_conversion[2])){

            $GLOBALS["deleted_groups"]++;
            //NOTE: This group can be deleted.
        }
        //If there is a common group; Remove the extra members from the group in the 2nd domain.
        else{
            $samba_conversion = remove_members($groupName,$samba_conversion);
        }
    }

    return $samba_conversion;
}

function fill_members($groupName,$samba_conversion){

    $ad_conversion = $GLOBALS['ad_conversion'];
    $samba_users = $GLOBALS['samba_users'];
    $samba = $GLOBALS['samba'];
    $f_detailed_log =  $GLOBALS['f_detailed_log'];

    foreach ($ad_conversion[2][$groupName] as $member){

        if (!in_array($member, $samba_conversion[2][$groupName])){
        //If a user in a group in 1st domain does not exist in the same group in 2nd domain
    
            if(array_key_exists($member, $samba_users) ){
            //If the 2nd domain has the user
                $dn = $samba_conversion[0][$groupName];
                $samba->addAttribute($dn,[
                    "member" => $samba_users[$member]
                ]);
                fwrite($f_detailed_log , $groupName." grubuna ".$member." kullanıcısı  başarıyla eklendi.\n");
                $GLOBALS["added_members"]++;
                
            }
            else{
            //If the 2nd domain does not has the user
                fwrite($f_detailed_log ,$groupName." grubuna ".$member." kullanıcısı  eklenemedi !!!");
                fwrite($f_detailed_log ,"\tÇünkü ".$member." 2. domainde tanımlı bir kullanıcı değil.\n");
                $GLOBALS["created_users"]++;
                //NOTE: A user can be created.
            }
        }
        
    }
    return $samba_conversion;
}

function remove_members($groupName,$samba_conversion){

    $ad_conversion = $GLOBALS['ad_conversion'];
    $samba_users = $GLOBALS['samba_users'];
    $samba = $GLOBALS['samba'];
    $f_detailed_log =  $GLOBALS['f_detailed_log'];

    foreach ($samba_conversion[2][$groupName] as $member){

        if (!in_array($member, $ad_conversion[2][$groupName])){
        //If a user in a group in 2nd domain does not exist in the same group in 1st domain

            $dn = $samba_conversion[0][$groupName];
            $samba->deleteAttribute($dn,[
                "member" => $samba_users[$member]
            ]);
            fwrite($f_detailed_log , $groupName." grubundan ".$member." kullanıcısı  başarıyla çıkarıldı.\n");
            $GLOBALS["removed_members"]++;

        }
    }
    return $samba_conversion;
}

function write_summary(){

    $f_summary = $GLOBALS["f_summary"];
    fwrite($f_summary ,"Detaylar\n");
    fwrite($f_summary ,"Oluşturulan grup sayısı : ".$GLOBALS["created_groups"]."\n");
    fwrite($f_summary ,"Silinmesi gereken grup sayısı : ".$GLOBALS["deleted_groups"]."\n");
    fwrite($f_summary ,"Gruplara eklenen üye sayısı : ".$GLOBALS["added_members"]."\n");
    fwrite($f_summary ,"Gruplardan çıkarılan üye sayısı : ".$GLOBALS["removed_members"]."\n");
    fwrite($f_summary ,"Fazladan üye sayısı : ".$GLOBALS["created_users"]."\n");

}

function write_sid_diff($ad_conversion,$samba_conversion){
    $f_sid_diff = $GLOBALS["f_sid_diff"];
    $result = array_diff_assoc($ad_conversion[1],$samba_conversion[1]);
    foreach ($result as $key => $value){
        fwrite($f_sid_diff , $key." => ".$value."\n");

    }

}

print_r("\n");
$samba_conversion = sync($samba_conversion);
write_summary();
print_r("\n");
print_r("\n");
print_r("\n");
write_sid_diff($ad_conversion,$samba_conversion);




function convertGroup($ldap_search_resutl,$users) {
    $sams = array_column($ldap_search_resutl, 'samaccountname');
    $samaccountname_list = array_column($sams, 0);

    $sids = array_column($ldap_search_resutl, 'objectsid');
    $objectsid_list = array_column($sids, 0);
    array_walk($objectsid_list,function(&$id){$id = bin_to_str_sid($id);});
    $sid_mapping = array_combine($samaccountname_list,$objectsid_list);
    
    $dns = array_column($ldap_search_resutl, 'dn');
    $group_dn_mapping = array_combine($samaccountname_list,$dns);
    
    $grp_members = [];
    foreach ($ldap_search_resutl as $grp) {
        $grp_members[$grp["samaccountname"][0]] = [];
        if (array_key_exists("member", $grp)){
            unset($grp["member"]["count"]);
            array_walk($grp["member"],function(&$member) use($users){$member = array_search($member,$users);});
            $grp_members[$grp["samaccountname"][0]]=$grp["member"];
        }
    }

    return [$group_dn_mapping,$sid_mapping,$grp_members];
}

function bin_to_str_sid($binary_sid) {

    $sid = NULL;
    /* 64bt PHP */
    if(strlen(decbin(~0)) == 64)
    {
        // Get revision, indentifier, authority 
        $parts = unpack('Crev/x/nidhigh/Nidlow', $binary_sid);
        // Set revision, indentifier, authority 
        $sid = sprintf('S-%u-%d',  $parts['rev'], ($parts['idhigh']<<32) + $parts['idlow']);
        // Translate domain
        $parts = unpack('x8/V*', $binary_sid);
        // Append if parts exists
        if ($parts) $sid .= '-';
        // Join all
        $sid.= join('-', $parts);
    }
    /* 32bit PHP */
    else
    {   
        $sid = 'S-';
        $sidinhex = str_split(bin2hex($binary_sid), 2);
        // Byte 0 = Revision Level
        $sid = $sid.hexdec($sidinhex[0]).'-';
        // Byte 1-7 = 48 Bit Authority
        $sid = $sid.hexdec($sidinhex[6].$sidinhex[5].$sidinhex[4].$sidinhex[3].$sidinhex[2].$sidinhex[1]);
        // Byte 8 count of sub authorities - Get number of sub-authorities
        $subauths = hexdec($sidinhex[7]);
        //Loop through Sub Authorities
        for($i = 0; $i < $subauths; $i++) {
            $start = 8 + (4 * $i);
            // X amount of 32Bit (4 Byte) Sub Authorities
            $sid = $sid.'-'.hexdec($sidinhex[$start+3].$sidinhex[$start+2].$sidinhex[$start+1].$sidinhex[$start]);
        }
    }
    if(preg_match('/S-*/', $sid)) // Outputs 1
        return $sid;
    else 
        return "NULL";
}

function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

class LdapConnection
{
    private $connection;
    private $dn = "";
    private $domain = "";
    private $fqdn = "";
    private $ip = null;
    private $username = null;
    private $password = null;
    private $ssl = false;
    private $port = 389;

    public function __construct($ip, $username, $password, $ssl = false, $domain, $port)
    {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
        $this->domain = $domain;
        $this->ssl = $ssl;
        $this->port = $port;

        if (substr($this->username, 0, 2) == "cn" || substr($this->username, 0, 2) == "CN") {
            $this->dn = $this->username;
        } else {
            $this->dn = $this->username . "@" . $this->getDomain();
        }

        $this->connection = $this->initWindows();
    }

    public function read($dn)
    {
        $object = ldap_read($this->connection, $this->escape($dn), "(objectclass=*)");
        //dd($object,$dn,$this->escape($dn));
        $entries = ldap_get_entries($this->connection, $object)[0];

        foreach ($entries as $key => $value) {
            if (is_int($key)) {
                unset($entries[$key]);
            }
        }
        unset($entries["count"]);
        return $entries;
    }

    private function initWindows()
    {
        // Create Ldap Connection Object
        if ($this->ssl) {
            $ldap_connection = ldap_connect('ldaps://' . $this->ip, $this->port);
        } else {
            $ldap_connection = ldap_connect('ldap://' . $this->ip, $this->port);
        }

        // Set Protocol Version
        ldap_set_option($ldap_connection, 17, 3);

        ldap_set_option($ldap_connection, 24582, 0);

        ldap_set_option($ldap_connection, 8, 0);
        // Try to Bind Ldap
        try {
            $flag = ldap_bind($ldap_connection, $this->dn, $this->password);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        if($flag == false){
            die($this->ip . " LDAP Baglantisi kurulamadi!");
        }

        // Return Object to use it later.
        return $ldap_connection;
    }

    public function check($dn){
        echo $dn . "\n";
		return ldap_read($this->connection,$dn,"(objectclass=*)");
    }
    
    public function escape($query)
    {
        $query = rawurldecode($query);
        $query = html_entity_decode($query);
        $query = ldap_escape(rawurldecode($query), "", LDAP_ESCAPE_FILTER);
        return $query;
    }

    public function search($filter, $options = [])
    {
        $searchOn = (array_key_exists("searchOn", $options) && $options["searchOn"] != null) ? $options["searchOn"] : $this->domain;
        $page = (array_key_exists("page", $options)) ? $options["page"] : "1";
        $perPage = (array_key_exists("perPage", $options)) ? $options["perPage"] : "500";
        $attributeList = (array_key_exists("attributeList", $options)) ? $options["attributeList"] : ["dn"];
        $stopOn = (array_key_exists("stopOn", $options)) ? $options["stopOn"] : "-1";

        $filter = html_entity_decode($filter);
        $searchOn = html_entity_decode($searchOn);

        // Set Variables
        $cookie = "";
        $size = 0;
        $entries = [];
        $loop = 0;

        // First, retrieve real size of search.
        do {

            // Break If that's enough
            if ($stopOn != "-1" && $size > $stopOn) {
                break;
            }

            // First Increase Loop Count
            $loop++;

            // Limit Search for each loop.
            ldap_control_paged_result($this->connection, intval($perPage), true, $cookie);

            // Make Search
            $search = ldap_search($this->connection, $searchOn, $filter, $attributeList);
            // Retrieve Entries if specified
            if ($loop == intval($page) || $page == "-1") {
                $entries = array_merge(ldap_get_entries($this->connection, $search), $entries);
            }

            // Count Results and sum with total size.
            $size += ldap_count_entries($this->connection, $search);

            // Update Cookie
            ldap_control_paged_result_response($this->connection, $search, $cookie);
        } while ($cookie !== null && $cookie != '');

        // Return what we have.
        return [$size, $entries];
    }

    public function addObject($cn, $data)
    {
        $flag = ldap_add($this->connection, $cn, $data);
        return $flag ? true : ldap_error($this->connection);
    }

    public function getAttributes($cn)
    {
        $cn = html_entity_decode($cn);
        $cn = ldap_escape($cn);
        $search = ldap_search($this->connection, $this->domain, '(distinguishedname=' . $cn . ')');
        $first = ldap_first_entry($this->connection, $search);
        return ldap_get_attributes($this->connection, $first);
    }

    public function convertTime($ldapTime)
    {
        $secsAfterADEpoch = $ldapTime / 10000000;
        $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
        return intval($secsAfterADEpoch - $ADToUnixConverter);
    }

    public function countSearch($query)
    {
        $search = ldap_search($this->connection, $this->domain, $query);

        return ldap_count_entries($this->connection, $search);
    }

    public function updateAttributes($cn, $array)
    {
        $toUpdate = [];
        $toDelete = [];
        foreach ($array as $key => $item) {
            if ($item == null) {
                $toDelete[$key] = array();
                continue;
            }
            $toUpdate[$key] = $item;
        }
        $flagUpdate = true;
        $flagDelete = true;
        if (count($toUpdate)) {
            $flagUpdate = ldap_mod_replace($this->connection, $cn, $toUpdate);
        }

        if (count($toDelete)) {
            $flagDelete = ldap_modify($this->connection, $cn, $toDelete);
        }

        return $flagUpdate && $flagDelete;
    }

    public function removeObject($cn)
    {
        return ldap_delete($this->connection, $cn);
    }

    public function addAttribute($cn, $array)
    {
        $cn = html_entity_decode($cn);
        try {
            return ldap_mod_add($this->connection, $cn, $array);
        } catch (Exception $exception) {
            return false;
        }
    }

    public function deleteAttribute($ou, $array)
    {
        $ou = html_entity_decode($ou);
        return ldap_mod_del($this->connection, $ou, $array);
    }

    public function getDomain()
    {
        $domain = $this->domain;
        $domain = str_replace("dc=", "", strtolower($domain));
        return str_replace(",", ".", $domain);
    }

    public function getDC()
    {
        return $this->domain;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function changePassword($ou, $password)
    {
        return $this->updateAttributes($ou, ["unicodepwd" => mb_convert_encoding("\"" . $password . "\"", "UTF-16LE")]);
    }

    public function renameOU($dn, $ou, $cn)
    {
        $flag = ldap_rename($this->connection, $dn, $cn, $ou, true);
        return $flag ? true : ldap_error($this->connection);
    }

    public function getFQDN()
    {
        return $this->fqdn;
    }

    public function list2($searchDN, $filter = "distinguishedName=*", $attributes = ["dn", "objectClass"])
    {
        $objects = ldap_list($this->connection, $searchDN, $filter, $attributes);
        return ldap_get_entries($this->connection, $objects);
    }
}
