<?php
$domainname1= "ali.lab";
$user1 = "administrator@".$domainname1;
$pass1 = "123123Aa";
$server1 = 'ldaps://192.168.1.68';
$port1="636";
$binddn1 = "DC=ali,DC=lab";
$ldap1 = ldap_connect($server1);
ldap_set_option($ldap1, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap1, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
ldap_set_option($ldap1, LDAP_OPT_REFERRALS, 0);

$bind1=ldap_bind($ldap1, $user1, $pass1);
if (!$bind1) {
    exit('Binding failed');
}

$domainname2= "bugra.lab";
$user2 = "administrator@".$domainname2;
$pass2 = "123123Aa";
$server2 = 'ldaps://192.168.1.69';
$port2="636";
$binddn2 = "DC=bugra,DC=lab";
$ldap2 = ldap_connect($server2);
ldap_set_option($ldap2, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap2, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
ldap_set_option($ldap2, LDAP_OPT_REFERRALS, 0);

$bind2=ldap_bind($ldap2, $user2, $pass2);
if (!$bind2) {
    exit('Binding failed');
}

function find_groups($ldap,$binddn,$domainname){
    //Distribution gruplari ve memberlarini ceker, bir array halinde doner.
    $filter = "(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))";
    $result = ldap_search($ldap, $binddn, $filter);
    $entries = ldap_get_entries($ldap,$result);

    $data['count'] = $entries['count'];
    $data['domainName'] = $domainname;

    $numberOfGroups = $entries['count'];
    for($i=0 ; $i<$numberOfGroups ; $i++){
    
        $name = $entries[$i]['name'][0];
        $data[$i] = $name;
        $data[$name] = array();
        if (array_key_exists('member', $entries[$i]) ){
            $numberOfMembers = $entries[$i]['member']['count'];
        }
        else{
            $numberOfMembers = 0; 
        }
    
        for($j=0 ; $j<$numberOfMembers ; $j++ ){

            $member = $entries[$i]['member'][$j];
            $pos = strpos($member, ',');
            $member = substr($member,0,$pos);
            array_push($data[$name], $member);
        }

    }
    return $data;
}
function sync($data1,$data2){

    //find_groups'un ciktisi olan iki dist. group listesini karsilastirir
    //ve gerekli islemlerin ne olduguna karar verir.

    for($i=0 ; $i<$data1['count']; $i++){

        $groupName = $data1[$i];
        //2. domainde eksik grup varsa; grubu ac ve uyelerini doldur.
        if (!in_array($groupName, $data2)) {
            create_group($groupName);
            add_members($groupName,$data1,$data2);  
        }
        //ortak grup varsa; 2. domaindeki gruba eksik uyeleri ekle.
        else{
            add_members($groupName,$data1,$data2);  
        }

    }
    print_r("\n");
    
    for($i=0 ; $i<$data2['count']; $i++){

        $groupName = $data2[$i];
        //2. domainde fazladan grup varsa; grubu sil
        if (!in_array($groupName, $data1)) {
            delete_group($groupName);
        }
        //ortak grup varsa; 2. domaindeki gruptan falza uyeleri sil.
        else{
            remove_members($groupName,$data1,$data2);
        }
        
    }
}

function create_group($groupName){

    $ldap2 = $GLOBALS["ldap2"];

    $group_info["objectClass"] = "top";
    $group_info["objectClass"] = "group";
    $group_info["groupType"] = 2;
    $group_info["instanceType"] = 4;
    $group_info["name"] = $groupName;

    $dn = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
    ldap_add($ldap2, $dn,$group_info);

}
function delete_group($groupName){

    $ldap2 = $GLOBALS["ldap2"];
    $group = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
    ldap_delete($ldap2, $group);

}
function add_members($groupName,$data1,$data2){

    $ldap2 = $GLOBALS["ldap2"];
    //data2'nin guncellemesi gerekiyor cunku yeni grup eklendi.
    $data2 = find_groups($GLOBALS["ldap2"],$GLOBALS["binddn2"],$GLOBALS["domainname2"]);
    
    $numberOfMembers = count($data1[$groupName]);
            
    for($j=0 ; $j<$numberOfMembers; $j++){

        $memberName = $data1[$groupName][$j];

        if (!in_array($memberName, $data2[$groupName])){
            $dn = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
            $group_info['member'] = $memberName.',CN=Users,DC=bugra,DC=lab';
            ldap_mod_add($ldap2,$dn,$group_info);
        }
    }
}
function remove_members($groupName,$data1,$data2){
    
    $ldap2 = $GLOBALS["ldap2"];

    $data2 = find_groups($GLOBALS["ldap2"],$GLOBALS["binddn2"],$GLOBALS["domainname2"]);
    
    $numberOfMembers = count($data2[$groupName]);

    for($j=0 ; $j<$numberOfMembers; $j++){
        $memberName = $data2[$groupName][$j];

        if(!in_array($memberName, $data1[$groupName])){
            $group = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
            $group_info['member'] = $memberName.',CN=Users,DC=bugra,DC=lab';
            ldap_mod_del($ldap2, $group, $group_info);  
        }
    }
}


$data1 = find_groups($ldap1,$binddn1,$domainname1);
$data2 = find_groups($ldap2,$binddn2,$domainname2);
print_r("\n ### Before the synchronization ###");
print_r($data1);
print_r("\n");
print_r($data2);

sync($data1,$data2);
print_r("\n");
print_r("\n ### After synchronization ###");
print_r("\n");

$data1 = find_groups($ldap1,$binddn1,$domainname1);
$data2 = find_groups($ldap2,$binddn2,$domainname2);
print_r("\n");
print_r($data1);
print_r("\n");
print_r($data2);


print_r("\n");
ldap_close($ldap1);
ldap_close($ldap2);
