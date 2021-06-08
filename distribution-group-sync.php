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


function list_groups($ldap,$binddn,$domainname){
    //distribution group
    $filter = "(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))";
    $result = ldap_search($ldap, $binddn, $filter);
    $entries = ldap_get_entries($ldap,$result);
    //print_r($entries);
    
    $numberOfGroups = $entries["count"];
    $data = [];
    $data["count"] = $numberOfGroups;
    $data["domainName"] = $domainname;
    for($i=0 ; $i<$numberOfGroups ; $i++){
    
        $name = $entries[$i]["cn"][0];

        if (array_key_exists("member", $entries[$i]) )
            $numberOfMembers = $entries[$i]["member"]["count"];
        else{
            $numberOfMembers = 0;
        }
        
        $data[$name] = [];
        $data[$i] = $name;
        for($j=0 ; $j<$numberOfMembers ; $j++ ){
    
            $member = $entries[$i]["member"][$j];
            $comma_position = strpos($member, ',');
            $member = substr($member, 0, $comma_position);
            array_push($data[$name], $member);
        }
    
    }
    return $data;
}

function sync($ldap1,$ldap2,$data1,$data2){
    $domainName1 = $data1["domainName"];
    $domainName2 = $data2["domainName"];

    for($i=0 ; $i<$data1["count"]; $i++){
        $groupName = $data1[$i];
    
        if (in_array($groupName, $data2)) {

            print_r("\nikisinde de ".$groupName." var");
            $numberOfMembers = count($data1[$groupName]);

            for($j=0 ; $j<$numberOfMembers; $j++){

                $memberName = $data1[$groupName][$j];

                if (in_array($memberName, $data2[$groupName])){
                    print_r("\n\t ".$memberName." ortak");
                }
                else{
                    print_r("\n\t ".$domainName2."'a ".$memberName." eklenmeli");
                }

            }
        }
        else{
            print_r("\n".$domainName2."'a ".$groupName." eklenmeli");
            add_group($ldap2,$groupName);
        }
    }
    print_r("\n");
    
    for($i=0 ; $i<$data2["count"]; $i++){
        $groupName = $data2[$i];
    
        if (in_array($groupName, $data1)) {

            print_r("\nikisinde de ".$groupName." var");
            $numberOfMembers = count($data2[$groupName]);

            for($j=0 ; $j<$numberOfMembers; $j++){
                $memberName = $data2[$groupName][$j];
                if (in_array($memberName, $data1[$groupName])){
                    print_r("\n\t ".$memberName." ortak");
                }
                else{
                    print_r("\n\t ".$domainName2."'dan ".$memberName." silinmeli");
                    delete_user($ldap2,$groupName,$memberName);
                    
                }
            }
        }
        else{
            print_r("\n".$domainName2."'dan ".$groupName." silinmeli");
            delete_group($ldap2,$groupName);
        }
    }
}

function delete_user($ldap,$groupName,$memberName){

    $group = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
    $group_info['member'] = $memberName.',CN=Users,DC=bugra,DC=lab';
    ldap_mod_del($ldap, $group, $group_info);

}

function delete_group($ldap,$groupName){

    $group = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
    ldap_delete($ldap, $group);

}
function add_group($ldap,$groupName){

    $info["objectClass"] = "top";
    $info["objectClass"] = "group";
    $info["groupType"] = 2;
    $info["instanceType"] = 4;
    $info["name"] = $groupName;

    $dn = 'CN='.$groupName.',CN=Users,DC=bugra,DC=lab';
    ldap_add($ldap, $dn,$info);

}



$data1 = list_groups($ldap1,$binddn1,$domainname1);
$data2 = list_groups($ldap2,$binddn2,$domainname2);
print_r($data1);
print_r($data2);
sync($ldap1,$ldap2,$data1,$data2);

print_r("\n");

$data1 = list_groups($ldap1,$binddn1,$domainname1);
$data2 = list_groups($ldap2,$binddn2,$domainname2);
print_r($data1);
print_r($data2);
sync($ldap1,$ldap2,$data1,$data2);


print_r("\n");
ldap_close($ldap1);
ldap_close($ldap2);
