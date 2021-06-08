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


function listGroupAndMembers($ldap,$binddn){
    //distribution group
    $filter = "(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))";
    $result = ldap_search($ldap, $binddn, $filter);
    $entries = ldap_get_entries($ldap,$result);
    
    $numberOfGroups = $entries["count"];
    $data = [];
    $data["count"] = $numberOfGroups;
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
$data1 = listGroupAndMembers($ldap1,$binddn1);
$data2 = listGroupAndMembers($ldap2,$binddn2);
print_r($domainname1."\n");
print_r($data1);
print_r("\n\n$domainname2\n");
print_r($data2);


for($i=0 ; $i<$data1["count"]; $i++){
    $name = $data1[$i];

    if (in_array($name, $data2)) {
        print_r("\nikisinde de ".$name." var");
        $numberOfMembers = count($data1[$name]);
        for($j=0 ; $j<$numberOfMembers; $j++){
            $memberName = $data1[$name][$j];
            if (in_array($memberName, $data2[$name])){
                print_r("\n\t ".$memberName." ortak");
            }
            else{
                print_r("\n\t ".$memberName." eklenmeli");
            }
        }
    }
    else{
        print_r("\n".$domainname2."'a ".$name." eklenmeli");

    }
}
print_r("\n");

for($i=0 ; $i<$data2["count"]; $i++){
    $name = $data2[$i];

    if (in_array($name, $data1)) {
        print_r("\nikisinde de ".$name." var");
        $numberOfMembers = count($data2[$name]);
        for($j=0 ; $j<$numberOfMembers; $j++){
            $memberName = $data2[$name][$j];
            if (in_array($memberName, $data1[$name])){
                print_r("\n\t ".$memberName." ortak");
            }
            else{
                print_r("\n\t ".$memberName." silinmeli");
            }
        }
    }
    else{
        print_r("\n".$domainname2."'dan ".$name." silinmeli");

    }
}


print_r("\n");
ldap_close($ldap1);
ldap_close($ldap2);
